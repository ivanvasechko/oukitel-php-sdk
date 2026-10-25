<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Http;

use Closure;
use IvanVasechko\Oukitel\Config\ClientOptions;
use IvanVasechko\Oukitel\Config\RegionConfiguration;
use IvanVasechko\Oukitel\Contracts\OukitelCloud;
use IvanVasechko\Oukitel\Contracts\RandomStringGenerator;
use IvanVasechko\Oukitel\Crypto\LoginSignature;
use IvanVasechko\Oukitel\Crypto\PasswordCipher;
use IvanVasechko\Oukitel\Crypto\RandomString;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\ApiError;
use IvanVasechko\Oukitel\Exceptions\ConfigurationMissing;
use IvanVasechko\Oukitel\Exceptions\RefreshUnavailable;
use IvanVasechko\Oukitel\Exceptions\TransportFailed;
use IvanVasechko\Oukitel\Parsing\ResponseParser;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use SensitiveParameter;

final class AcceleronixCloudClient implements OukitelCloud
{
    private readonly RetryingHttpClient $retrying;

    /**
     * @param  array<string, RegionConfiguration>  $regions
     * @param  Closure(int): void|null  $delay
     */
    public function __construct(
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly array $regions,
        private readonly ClientOptions $options = new ClientOptions,
        private readonly RandomStringGenerator $random = new RandomString,
        private readonly ResponseParser $parser = new ResponseParser,
        ?Closure $delay = null,
        LoggerInterface $logger = new NullLogger,
    ) {
        if ($options->useRefreshToken) {
            throw new RefreshUnavailable;
        }
        $this->retrying = new RetryingHttpClient($http, $options, $delay, $logger);
    }

    public function refresh(#[SensitiveParameter] Session $session): Session
    {
        throw new RefreshUnavailable;
    }

    public function login(#[SensitiveParameter] Credentials $credentials): Session
    {
        $configuration = $this->configuration($credentials->region);
        $random = $this->random->generate();
        $password = (new PasswordCipher)->encrypt($credentials->password, $random);
        $body = http_build_query([
            'email' => $credentials->login,
            'pwd' => $password,
            'random' => $random,
            'userDomain' => $configuration->userDomain,
            'signature' => (new LoginSignature)->sign($credentials->login, $password, $random, $configuration->userDomainSecret),
        ], '', '&', PHP_QUERY_RFC3986);
        $payload = $this->request($credentials->region, 'POST', '/v2/enduser/enduserapi/emailPwdLogin',
            'login', $body, 'application/x-www-form-urlencoded');

        return $this->parser->login($payload, $credentials->region);
    }

    public function devices(#[SensitiveParameter] Session $session): array
    {
        return $this->parser->devices($this->request($session->region, 'GET',
            '/v2/binding/enduserapi/userDeviceList', 'devices', token: $session->accessToken));
    }

    public function telemetry(#[SensitiveParameter] Session $session, #[SensitiveParameter] DeviceId $device): Telemetry
    {
        return $this->parser->telemetry($this->attributes($session, $device));
    }

    public function rawAttributes(#[SensitiveParameter] Session $session, #[SensitiveParameter] DeviceId $device): array
    {
        return $this->parser->rawAttributes($this->attributes($session, $device));
    }

    public function wake(#[SensitiveParameter] Session $session, #[SensitiveParameter] DeviceId $device): void
    {
        $body = json_encode([
            'data' => '[{"high_frequency_reporting":3}]',
            'deviceList' => [['deviceKey' => $device->deviceKey, 'productKey' => $device->productKey]],
            'cacheTime' => 60, 'isCache' => 1, 'isCover' => 1, 'dataFormat' => 0, 'type' => 2,
        ], JSON_THROW_ON_ERROR);
        $this->parser->assertSuccessful($this->request($session->region, 'POST',
            '/v2/binding/enduserapi/batchControlDevice', 'wake', $body, 'application/json', $session->accessToken, false), 'wake');
    }

    private function attributes(#[SensitiveParameter] Session $session, #[SensitiveParameter] DeviceId $device): mixed
    {
        $query = http_build_query(['pk' => $device->productKey, 'dk' => $device->deviceKey], '', '&', PHP_QUERY_RFC3986);

        return $this->request($session->region, 'GET',
            '/v2/binding/enduserapi/getDeviceBusinessAttributes?'.$query, 'telemetry', token: $session->accessToken);
    }

    private function request(
        Region $region,
        string $method,
        #[SensitiveParameter] string $path,
        string $operation,
        #[SensitiveParameter] string $body = '',
        string $contentType = 'application/json',
        #[SensitiveParameter] ?string $token = null,
        bool $retry = true,
    ): mixed {
        $configuration = $this->configuration($region);
        $request = $this->requests->createRequest($method, rtrim($configuration->baseUrl, '/').$path)
            ->withHeader('appid', $configuration->appId)
            ->withHeader('appversion', $configuration->appVersion)
            ->withHeader('appsystemtype', 'android')
            ->withHeader('User-Agent', $this->options->userAgent)
            ->withHeader('Content-Type', $contentType)
            ->withBody($this->streams->createStream($body));
        if ($token !== null) {
            $request = $request->withHeader('Authorization', $token);
        }
        try {
            $response = ($retry ? $this->retrying : $this->http)->sendRequest($request);
        } catch (ClientExceptionInterface|RuntimeException) {
            throw new TransportFailed;
        }
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new ApiError($response->getStatusCode(), $operation);
        }
        try {
            $responseBody = $response->getBody()->getContents();
        } catch (RuntimeException) {
            throw new TransportFailed;
        }

        return $this->parser->decode($responseBody);
    }

    private function configuration(Region $region): RegionConfiguration
    {
        return $this->regions[$region->value] ?? throw new ConfigurationMissing;
    }
}
