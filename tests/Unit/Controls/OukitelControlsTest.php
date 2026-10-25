<?php

use Http\Mock\Client;
use IvanVasechko\Oukitel\Account;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\ApiError;
use IvanVasechko\Oukitel\Exceptions\ControlsUnavailable;
use IvanVasechko\Oukitel\Exceptions\MalformedResponse;
use IvanVasechko\Oukitel\Exceptions\SessionExpired;
use IvanVasechko\Oukitel\Exceptions\TransportFailed;
use IvanVasechko\Oukitel\OukitelClient;
use IvanVasechko\Oukitel\Testing\FakeOukitelCloud;
use IvanVasechko\Oukitel\Tests\Support\FrozenClock;
use IvanVasechko\Oukitel\Tests\Support\RecordingCache;
use IvanVasechko\Oukitel\Tests\Support\RecordingLogger;
use IvanVasechko\Oukitel\Tests\Support\ScriptedCloud;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

function controlHttpAccount(Client $http, ?FrozenClock $clock = null, ?Closure $delay = null, ?RecordingCache $cache = null): Account
{
    $factory = new Psr17Factory;
    $http->addResponse(new Response(200, [], '{"code":200,"data":{"accessToken":{"token":"example","expirationTime":1800003600},"refreshToken":{"token":"example"}}}'));

    return OukitelClient::create($http, $factory, $factory, cache: $cache,
        clock: $clock ?? new FrozenClock, controlDelay: $delay)
        ->account(new Credentials(Region::Eu, 'user@example.test', 'example-password'));
}

function acObservation(bool $enabled, int|string|null $time): Response
{
    return new Response(200, [], json_encode(['code' => 200, 'data' => ['customizeTslInfo' => [[
        'resourceCode' => 'ac_switch', 'resourceValce' => $enabled ? 'true' : 'false',
        'dataType' => 'BOOL', 'createTime' => $time,
    ]]]], JSON_THROW_ON_ERROR));
}

it('keeps controls construction inert and sends each typed command in its exact envelope', function (string $method, bool|int $value, string $data) {
    $http = new Client(new Psr17Factory);
    $account = controlHttpAccount($http);
    $http->addResponse(new Response(200, [], '{"code":200}'));
    $controls = $account->controls();
    expect($http->getRequests())->toHaveCount(0);

    $controls->$method(new DeviceId('example-device', 'example-product'), $value);

    $request = $http->getLastRequest();
    expect($request->getMethod())->toBe('POST');
    expect($request->getUri()->getPath())->toBe('/v2/binding/enduserapi/batchControlDevice');
    expect(json_decode((string) $request->getBody(), true, flags: JSON_THROW_ON_ERROR))->toBe([
        'data' => $data, 'deviceList' => [['deviceKey' => 'example-device', 'productKey' => 'example-product']],
        'cacheTime' => 60, 'isCache' => 1, 'isCover' => 1, 'dataFormat' => 0, 'type' => 2,
    ]);
    expect($http->getRequests())->toHaveCount(2);
})->with([
    ['setAcOutput', true, '[{"ac_switch":true}]'], ['setAcOutput', false, '[{"ac_switch":false}]'],
    ['setDcOutput', true, '[{"dc_switch":true}]'], ['setDcOutput', false, '[{"dc_switch":false}]'],
    ['setUsbOutput', true, '[{"usb_switch":true}]'], ['setUsbOutput', false, '[{"usb_switch":false}]'],
    ['setAcChargingLimit', 3, '[{"ac_charging_limit":3}]'],
    ['setAcChargingLimit', 50, '[{"ac_charging_limit":50}]'],
    ['setAcChargingLimit', 100, '[{"ac_charging_limit":100}]'],
]);

it('rejects invalid percentages before login, telemetry or control HTTP requests', function (int $percent) {
    $http = new Client(new Psr17Factory);
    $controls = controlHttpAccount($http)->controls();
    $device = new DeviceId('example', 'example');

    expect(fn () => $controls->setAcChargingLimit($device, $percent))->toThrow(InvalidArgumentException::class);
    expect(fn () => $controls->sendAndConfirm($device, ['ac_charging_limit' => $percent]))->toThrow(InvalidArgumentException::class);
    expect($http->getRequests())->toHaveCount(0);
})->with([-1, 0, 2, 101]);

it('keeps raw unsafe opt-in isolated and logs codes and masked suffixes without values', function (string $deviceKey, string $suffix) {
    $clock = new FrozenClock;
    $fake = new FakeOukitelCloud($clock);
    $logger = new RecordingLogger;
    $account = OukitelClient::fromCloud($fake, logger: $logger, clock: $clock)
        ->account(new Credentials(Region::Eu, 'user@example.test', 'example-password'));
    $controls = $account->controls();
    $device = new DeviceId($deviceKey, 'example-product');
    expect(fn () => $controls->send($device, ['custom_code' => 'private-value']))->toThrow(InvalidArgumentException::class);

    $controls->unsafe()->send($device, ['custom_code' => 'private-value', 'usb_switch' => false]);

    expect($fake->commands())->toBe([['device' => $device, 'commands' => ['custom_code' => 'private-value', 'usb_switch' => false]]]);
    expect($logger->records)->toBe([
        ['level' => 'notice', 'message' => 'Sending experimental Oukitel control.', 'context' => ['device_suffix' => $suffix, 'code' => 'custom_code']],
        ['level' => 'notice', 'message' => 'Sending experimental Oukitel control.', 'context' => ['device_suffix' => $suffix, 'code' => 'usb_switch']],
    ]);
    expect(json_encode($logger->records, JSON_THROW_ON_ERROR))->not->toContain('private-value', 'example-product', 'example-password', 'user@example.test');
    expect(fn () => $controls->send($device, ['custom_code' => true]))->toThrow(InvalidArgumentException::class);
    expect(fn () => $controls->unsafe()->setAcChargingLimit($device, 2))->toThrow(InvalidArgumentException::class);
    expect($fake->commands())->toHaveCount(1);
})->with([['example-device-1234', '****1234'], ['x', '****']]);

it('evicts vendor-expired sessions without reauthentication or replay of controls', function () {
    $http = new Client(new Psr17Factory);
    $cache = new RecordingCache;
    $account = controlHttpAccount($http, cache: $cache);
    $http->setDefaultResponse(new Response(200, [], '{"code":5032}'));

    expect(fn () => $account->controls()->setAcOutput(new DeviceId('example', 'example'), true))->toThrow(SessionExpired::class);
    expect($http->getRequests())->toHaveCount(2);
    expect($cache->values)->toBe([]);
});

it('never replays any failed control through an injected cloud', function (string $failure) {
    $clock = new FrozenClock;
    $error = match ($failure) {
        'vendor' => new SessionExpired,
        'transport' => new TransportFailed,
        default => new ApiError(503, 'control'),
    };
    $fake = (new FakeOukitelCloud($clock))->failControlWith($error);
    $account = OukitelClient::fromCloud($fake, clock: $clock)
        ->account(new Credentials(Region::Eu, 'user@example.test', 'example-password'));

    expect(fn () => $account->controls()->setUsbOutput(new DeviceId('example', 'example'), true))->toThrow($error::class);
    expect($fake->commands())->toHaveCount(1);
})->with(['vendor', 'transport', 'http']);

it('fails explicitly when a custom read cloud has no control capability', function () {
    $cloud = new ScriptedCloud;
    $account = OukitelClient::fromCloud($cloud, clock: new FrozenClock)
        ->account(new Credentials(Region::Eu, 'user@example.test', 'example-password'));

    expect(fn () => $account->controls()->setDcOutput(new DeviceId('example', 'example'), false))->toThrow(ControlsUnavailable::class);
    expect($cloud->calls)->toBe([]);
});

it('confirms only newer matching telemetry with an injected sleeper and clock', function () {
    $http = new Client(new Psr17Factory);
    $clock = new FrozenClock;
    $delays = [];
    $account = controlHttpAccount($http, $clock, function (int $milliseconds) use ($clock, &$delays): void {
        $delays[] = $milliseconds;
        $clock->timestamp++;
    });
    $http->addResponse(acObservation(false, '1000'));
    $http->addResponse(new Response(200, [], '{"code":200}'));
    $http->addResponse(acObservation(false, '1000'));
    $http->addResponse(acObservation(true, '1001'));

    $result = $account->controls()->sendAndConfirm(new DeviceId('example', 'example'), ['ac_switch' => true], attempts: 3, delayMilliseconds: 500);

    expect($result->confirmed)->toBeTrue();
    expect($result->observed)->toBe(['ac_switch' => true]);
    expect($delays)->toBe([500]);
    expect($http->getRequests())->toHaveCount(5);
});

it('does not mistake stale or unprovably fresh matching values for confirmation', function (int|string|null $before, int|string|null $after, bool $expected) {
    $http = new Client(new Psr17Factory);
    $account = controlHttpAccount($http, delay: fn (int $milliseconds) => test()->fail('No sleep expected.'));
    $http->addResponse(acObservation(true, $before));
    $http->addResponse(new Response(200, [], '{"code":200}'));
    $http->addResponse(acObservation(true, $after));

    $result = $account->controls()->sendAndConfirm(new DeviceId('example', 'example'), ['ac_switch' => true], attempts: 1);

    expect($result->confirmed)->toBe($expected);
    expect($result->observed)->toBe(['ac_switch' => true]);
    expect($http->getRequests())->toHaveCount(4);
})->with([[1000, 1000, false], [1000, 999, false], [null, 1001, false], [1000, null, false],
    ['unknown', 'unknown', false], ['1700000000000', '1700000001000', true]]);

it('stops at the poll count without resending or sleeping after the final observation', function () {
    $http = new Client(new Psr17Factory);
    $delays = [];
    $account = controlHttpAccount($http, delay: function (int $milliseconds) use (&$delays): void {
        $delays[] = $milliseconds;
    });
    $http->addResponse(acObservation(false, 1000));
    $http->addResponse(new Response(200, [], '{"code":200}'));
    for ($poll = 0; $poll < 3; $poll++) {
        $http->addResponse(acObservation(false, 1001));
    }

    $result = $account->controls()->sendAndConfirm(new DeviceId('example', 'example'), ['ac_switch' => true], attempts: 3, delayMilliseconds: 17);

    expect($result->confirmed)->toBeFalse();
    expect($result->observed)->toBe(['ac_switch' => false]);
    expect($delays)->toBe([17, 17]);
    expect($http->getRequests())->toHaveCount(6);
});

it('caps waiting at the clock deadline without polling again or resending', function () {
    $http = new Client(new Psr17Factory);
    $clock = new FrozenClock;
    $delays = [];
    $account = controlHttpAccount($http, $clock, function (int $milliseconds) use ($clock, &$delays): void {
        $delays[] = $milliseconds;
        $clock->timestamp++;
    });
    $http->addResponse(acObservation(false, 1000));
    $http->addResponse(new Response(200, [], '{"code":200}'));
    $http->addResponse(acObservation(false, 1001));

    $result = $account->controls()->sendAndConfirm(new DeviceId('example', 'example'), ['ac_switch' => true],
        attempts: 100, delayMilliseconds: 2000, timeoutSeconds: 1);

    expect($result->confirmed)->toBeFalse();
    expect($delays)->toBe([1000]);
    expect($http->getRequests())->toHaveCount(4);
});

it('surfaces observation errors after the write without reporting success or replaying it', function () {
    $http = new Client(new Psr17Factory);
    $account = controlHttpAccount($http);
    $http->addResponse(acObservation(false, 1000));
    $http->addResponse(new Response(200, [], '{"code":200}'));
    $http->addResponse(new Response(200, [], '{broken'));

    expect(fn () => $account->controls()->sendAndConfirm(new DeviceId('example', 'example'), ['ac_switch' => true]))->toThrow(MalformedResponse::class);
    expect($http->getRequests())->toHaveCount(4);
});

it('requires fresh confirmation of every code in a multi-command write', function () {
    $http = new Client(new Psr17Factory);
    $account = controlHttpAccount($http, delay: static function (int $milliseconds): void {});
    foreach ([[false, 100, 1000], [true, 50, 1000], [true, 50, 1001]] as $index => [$enabled, $limit, $limitTime]) {
        $http->addResponse(new Response(200, [], json_encode(['code' => 200, 'data' => ['customizeTslInfo' => [
            ['resourceCode' => 'ac_switch', 'resourceValce' => $enabled, 'dataType' => 'BOOL', 'createTime' => 1000 + $index],
            ['resourceCode' => 'ac_charging_limit', 'resourceValce' => $limit, 'dataType' => 'INT', 'createTime' => $limitTime],
        ]]], JSON_THROW_ON_ERROR)));
        if ($index === 0) {
            $http->addResponse(new Response(200, [], '{"code":200}'));
        }
    }

    $result = $account->controls()->sendAndConfirm(new DeviceId('example', 'example'),
        ['ac_switch' => true, 'ac_charging_limit' => 50], attempts: 2, delayMilliseconds: 0);

    expect($result->confirmed)->toBeTrue();
    expect($result->observed)->toBe(['ac_switch' => true, 'ac_charging_limit' => 50]);
    expect($http->getRequests())->toHaveCount(5);
});

it('does not confirm a matching observation that arrives after the clock deadline', function () {
    $http = new Client(new Psr17Factory);
    $clock = new FrozenClock;
    $http->addResponse(new Response(200, [], '{"code":200,"data":{"accessToken":{"token":"example","expirationTime":1800003600},"refreshToken":{"token":"example"}}}'));
    $http->addResponse(acObservation(false, 1000));
    $http->addResponse(new Response(200, [], '{"code":200}'));
    $http->addResponse(acObservation(true, 1001));
    $wrapper = new class($http, $clock) implements ClientInterface
    {
        private int $reads = 0;

        public function __construct(private Client $http, private FrozenClock $clock) {}

        public function sendRequest(RequestInterface $request): ResponseInterface
        {
            $response = $this->http->sendRequest($request);
            if (str_contains($request->getUri()->getPath(), 'getDeviceBusinessAttributes') && ++$this->reads === 2) {
                $this->clock->timestamp += 2;
            }

            return $response;
        }
    };
    $factory = new Psr17Factory;
    $account = OukitelClient::create($wrapper, $factory, $factory, clock: $clock)
        ->account(new Credentials(Region::Eu, 'user@example.test', 'example'));

    $result = $account->controls()->sendAndConfirm(new DeviceId('example', 'example'), ['ac_switch' => true], timeoutSeconds: 1);

    expect($result->confirmed)->toBeFalse();
    expect($result->observed)->toBe(['ac_switch' => true]);
    expect($http->getRequests())->toHaveCount(4);
});

it('rejects invalid confirmation budgets before any request', function (int $attempts, int $delay, int $timeout) {
    $http = new Client(new Psr17Factory);
    $controls = controlHttpAccount($http)->controls();

    expect(fn () => $controls->sendAndConfirm(new DeviceId('example', 'example'), ['ac_switch' => true],
        $attempts, $delay, $timeout))->toThrow(InvalidArgumentException::class);
    expect($http->getRequests())->toHaveCount(0);
})->with([[0, 0, 1], [101, 0, 1], [1, -1, 1], [1, 60001, 1], [1, 0, 0], [1, 0, 301]]);
