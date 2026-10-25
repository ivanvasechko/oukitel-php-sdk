<?php

use Http\Client\Exception\NetworkException;
use Http\Client\Exception\RequestException;
use Http\Mock\Client;
use IvanVasechko\Oukitel\Config\ClientOptions;
use IvanVasechko\Oukitel\Config\RegionConfiguration;
use IvanVasechko\Oukitel\Contracts\RandomStringGenerator;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\ApiError;
use IvanVasechko\Oukitel\Exceptions\AuthenticationFailed;
use IvanVasechko\Oukitel\Exceptions\ConfigurationMissing;
use IvanVasechko\Oukitel\Exceptions\MalformedResponse;
use IvanVasechko\Oukitel\Exceptions\SessionExpired;
use IvanVasechko\Oukitel\Exceptions\TransportFailed;
use IvanVasechko\Oukitel\Http\AcceleronixCloudClient;
use IvanVasechko\Oukitel\Http\RetryingHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function protocolCloud(Client $http, ?ClientOptions $options = null, ?Closure $delay = null): AcceleronixCloudClient
{
    $factory = new Psr17Factory;
    $random = new class implements RandomStringGenerator
    {
        public function generate(): string
        {
            return '0123456789ABCDEF';
        }
    };

    return new AcceleronixCloudClient($http, $factory, $factory,
        ['EU' => new RegionConfiguration('https://example.test', '277', '3.7.5', 'example-domain', 'example-public-constant')],
        $options ?? new ClientOptions(retryDelayMilliseconds: 0), $random, delay: $delay);
}

beforeEach(function () {
    $this->http = new Client(new Psr17Factory);
    $this->cloud = protocolCloud($this->http);
    $this->session = new Session(Region::Eu, 'example-access-token', 'example-refresh-token', 2000000000);
    $this->device = new DeviceId('example/device key', 'example/product key');
});

it('sends the exact encrypted form login and common headers', function () {
    $this->http->addResponse(new Response(200, [], '{"code":200,"data":{"accessToken":{"token":"example-access","expirationTime":2000000000},"refreshToken":{"token":"example-refresh"}}}'));
    $session = $this->cloud->login(new Credentials(Region::Eu, 'user@example.test', 'test-password'));
    $request = $this->http->getLastRequest();
    parse_str((string) $request->getBody(), $form);

    expect($session->accessToken)->toBe('example-access')
        ->and($request->getMethod())->toBe('POST')
        ->and((string) $request->getUri())->toBe('https://example.test/v2/enduser/enduserapi/emailPwdLogin')
        ->and($request->getHeaderLine('appid'))->toBe('277')
        ->and($request->getHeaderLine('appversion'))->toBe('3.7.5')
        ->and($request->getHeaderLine('appsystemtype'))->toBe('android')
        ->and($request->getHeaderLine('User-Agent'))->toBe('okhttp/4.9.3')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/x-www-form-urlencoded')
        ->and($request->hasHeader('Authorization'))->toBeFalse()
        ->and($form)->toBe([
            'email' => 'user@example.test', 'pwd' => 'pmYqG0PZl+ol+5uaLYDkqg==',
            'random' => '0123456789ABCDEF', 'userDomain' => 'example-domain',
            'signature' => hash('sha256', 'user@example.testpmYqG0PZl+ol+5uaLYDkqg==0123456789ABCDEFexample-public-constant'),
        ]);
});

it('sends authenticated device and raw telemetry reads without a Bearer prefix', function () {
    $this->http->addResponse(new Response(200, [], '{"code":200,"data":{"list":[]}}'));
    expect($this->cloud->devices($this->session))->toBe([]);
    expect($this->http->getLastRequest()->getUri()->getPath())->toBe('/v2/binding/enduserapi/userDeviceList');
    $payload = '{"code":200,"data":{"deviceData":{"onlineStatus":1},"customizeTslInfo":[{"resourceCode":"battery_percentage","resourceValce":"0075","dataType":"INT"}]}}';
    $this->http->addResponse(new Response(200, [], $payload));
    expect($this->cloud->rawAttributes($this->session, $this->device)[0]->resourceValue)->toBe('0075');
    $request = $this->http->getLastRequest();
    expect($request->getHeaderLine('Authorization'))->toBe('example-access-token')
        ->and($request->getMethod())->toBe('GET')
        ->and($request->getUri()->getPath())->toBe('/v2/binding/enduserapi/getDeviceBusinessAttributes')
        ->and($request->getUri()->getQuery())->toBe('pk=example%2Fproduct%20key&dk=example%2Fdevice%20key');
    $this->http->addResponse(new Response(200, [], $payload));
    expect($this->cloud->telemetry($this->session, $this->device)->batteryPercent)->toBe(75);
});

it('sends exactly the safe wake envelope once', function () {
    $this->http->addResponse(new Response(200, [], '{"code":200}'));
    $this->cloud->wake($this->session, $this->device);
    $request = $this->http->getLastRequest();
    expect($request->getMethod())->toBe('POST')
        ->and($request->getUri()->getPath())->toBe('/v2/binding/enduserapi/batchControlDevice')
        ->and($request->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and(json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR))->toBe([
            'data' => '[{"high_frequency_reporting":3}]',
            'deviceList' => [['deviceKey' => 'example/device key', 'productKey' => 'example/product key']],
            'cacheTime' => 60, 'isCache' => 1, 'isCover' => 1, 'dataFormat' => 0, 'type' => 2,
        ])
        ->and($this->http->getRequests())->toHaveCount(1);
});

it('bounds 5xx attempts, closes failed streams and uses the configured delay', function () {
    $first = new Response(500, [], 'private server failure');
    $this->http->addResponse($first);
    $this->http->addResponse(new Response(502));
    $this->http->addResponse(new Response(200, [], '{"code":200,"data":{"list":[]}}'));
    $delays = [];
    $cloud = protocolCloud($this->http, new ClientOptions(retryDelayMilliseconds: 17), function (int $delay) use (&$delays): void {
        $delays[] = $delay;
    });
    expect($cloud->devices($this->session))->toBe([])
        ->and($delays)->toBe([17, 17])
        ->and($this->http->getRequests())->toHaveCount(3)
        ->and($first->getBody()->isReadable())->toBeFalse();
});

it('retries network failures but sanitizes exhausted transport and invalid request errors', function (string $kind, int $attempts) {
    $request = (new Psr17Factory)->createRequest('GET', 'https://example.test/?private=device');
    $exception = $kind === 'network'
        ? new NetworkException('private token and request', $request)
        : new RequestException('private token and request', $request);
    $this->http->setDefaultException($exception);
    try {
        $this->cloud->devices($this->session);
        test()->fail('Expected transport failure.');
    } catch (TransportFailed $error) {
        expect($error->getPrevious())->toBeNull()->and($error->getMessage())->not->toContain('private');
    }
    expect($this->http->getRequests())->toHaveCount($attempts);
})->with([['network', 3], ['invalid-request', 1]]);

it('does not retry HTTP 4xx, vendor auth failures or malformed responses', function (int $status, string $body, string $exception) {
    $this->http->addResponse(new Response($status, [], $body));
    expect(fn () => $this->cloud->devices($this->session))->toThrow($exception);
    expect($this->http->getRequests())->toHaveCount(1);
})->with([
    [401, '{"code":5032}', ApiError::class],
    [200, '{"code":5032}', SessionExpired::class],
    [200, '{"code":1001,"msg":"private"}', ApiError::class],
    [200, '{broken', MalformedResponse::class],
]);

it('returns the final HTTP failure after the configured total attempts', function () {
    $this->http->setDefaultResponse(new Response(503));
    expect(fn () => $this->cloud->devices($this->session))->toThrow(ApiError::class, 'code 503');
    expect($this->http->getRequests())->toHaveCount(3);
});

it('never retries ambiguous wake failures', function (bool $network) {
    if ($network) {
        $this->http->setDefaultException(new NetworkException('private', (new Psr17Factory)->createRequest('POST', 'https://example.test')));
    } else {
        $this->http->setDefaultResponse(new Response(503));
    }
    expect(fn () => $this->cloud->wake($this->session, $this->device))->toThrow($network ? TransportFailed::class : ApiError::class);
    expect($this->http->getRequests())->toHaveCount(1);
})->with([true, false]);

it('does not retry rejected login credentials', function () {
    $this->http->addResponse(new Response(200, [], '{"code":1001,"msg":"private"}'));
    expect(fn () => $this->cloud->login(new Credentials(Region::Eu, 'example', 'password')))->toThrow(AuthenticationFailed::class);
    expect($this->http->getRequests())->toHaveCount(1);
});

it('rejects missing region configuration before sending a request', function () {
    expect(fn () => $this->cloud->login(new Credentials(Region::Us, 'example', 'password')))->toThrow(ConfigurationMissing::class);
    expect($this->http->getRequests())->toHaveCount(0);
});

it('rewinds consumed login bodies before retrying', function () {
    $http = new class implements ClientInterface
    {
        public array $bodies = [];

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $this->bodies[] = $request->getBody()->getContents();

            return new Response(count($this->bodies) === 1 ? 500 : 200);
        }
    };
    $request = (new Psr17Factory)->createRequest('POST', 'https://example.test')
        ->withBody((new Psr17Factory)->createStream('exact-login-form'));
    (new RetryingHttpClient($http, new ClientOptions(retryDelayMilliseconds: 0)))->sendRequest($request);
    expect($http->bodies)->toBe(['exact-login-form', 'exact-login-form']);
});
