<?php

use Composer\InstalledVersions;
use GuzzleHttp\Client as GuzzleClient;
use Http\Discovery\Psr18ClientDiscovery;
use Http\Mock\Client;
use IvanVasechko\Oukitel\Config\ClientOptions;
use IvanVasechko\Oukitel\Config\RegionConfiguration;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\ConfigurationMissing;
use IvanVasechko\Oukitel\Exceptions\TransportConfigurationFailed;
use IvanVasechko\Oukitel\Http\HttpClientFactory;
use IvanVasechko\Oukitel\OukitelClient;
use IvanVasechko\Oukitel\Tests\Support\FrozenClock;
use IvanVasechko\Oukitel\Tests\Support\RecordingCache;
use IvanVasechko\Oukitel\Tests\Support\RecordingFactories;
use IvanVasechko\Oukitel\Tests\Support\ScriptedCloud;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

it('composes all account operations without discovery or network calls during construction', function () {
    $cloud = new ScriptedCloud;
    $client = OukitelClient::fromCloud($cloud, clock: new FrozenClock);
    $account = $client->account(new Credentials(Region::Eu, 'example@example.test', 'example-password'));
    expect($cloud->logins)->toBe(0);
    $account->verify();
    $account->devices();
    $id = new DeviceId('example', 'example');
    expect($account->telemetry($id)->batteryPercent)->toBe(75)
        ->and($account->rawAttributes($id))->toBe([]);
    $account->wake($id);
    expect($cloud->logins)->toBe(2)
        ->and($cloud->calls)->toBe(['devices', 'telemetry', 'rawAttributes', 'wake']);
});

it('shares sessions inside a client but not between default client instances', function () {
    $cloud = new ScriptedCloud;
    $credentials = new Credentials(Region::Eu, 'example@example.test', 'example-password');
    $first = OukitelClient::fromCloud($cloud, clock: new FrozenClock);
    $first->account($credentials)->devices();
    $first->account($credentials)->devices();
    $second = OukitelClient::fromCloud($cloud, clock: new FrozenClock);
    $second->account($credentials)->devices();
    expect($cloud->logins)->toBe(2);
    $first->account(new Credentials(Region::Us, 'example@example.test', 'example-password'))->devices();
    expect($cloud->logins)->toBe(3);
});

it('honors injected HTTP, factories, cache, clock, options and region configuration', function () {
    $strategies = [...Psr18ClientDiscovery::getStrategies()];
    Psr18ClientDiscovery::setStrategies([]);
    try {
        $http = new Client(new Psr17Factory);
        $factories = new RecordingFactories;
        $cache = new RecordingCache;
        $http->addResponse(new Response(200, [], '{"code":200,"data":{"accessToken":{"token":"example","expirationTime":1800003600},"refreshToken":{"token":"example"}}}'));
        $http->addResponse(new Response(200, [], '{"code":200,"data":{"list":[]}}'));
        $client = OukitelClient::create($http, $factories, $factories, $cache,
            clock: new FrozenClock, options: new ClientOptions(userAgent: 'example-sdk/1', refreshBeforeSeconds: 60),
            regions: ['EU' => RegionConfiguration::preset(Region::Eu)->withBaseUrl('https://example.test')]);
        expect($http->getRequests())->toHaveCount(0);
        $account = $client->account(new Credentials(Region::Eu, 'example@example.test', 'example-password'));
        expect($account->devices())->toBe([])
            ->and($http->getRequests())->toHaveCount(2)
            ->and($factories->requests)->toBe(2)->and($factories->streams)->toBe(2)
            ->and($http->getLastRequest()->getUri()->getHost())->toBe('example.test')
            ->and($http->getLastRequest()->getHeaderLine('User-Agent'))->toBe('example-sdk/1')
            ->and(array_values($cache->ttls))->toBe([3540]);
    } finally {
        Psr18ClientDiscovery::setStrategies($strategies);
    }
});

it('fails clearly when a missing optional collaborator cannot be discovered', function (string $missing) {
    $strategies = [...Psr18ClientDiscovery::getStrategies()];
    Psr18ClientDiscovery::setStrategies([]);
    try {
        $factory = new Psr17Factory;
        expect(fn () => OukitelClient::create(
            httpClient: $missing === 'http' ? null : new Client($factory),
            requestFactory: $missing === 'request' ? null : $factory,
            streamFactory: $missing === 'stream' ? null : $factory,
        ))->toThrow(TransportConfigurationFailed::class, 'inject a PSR-18 client and PSR-17 factories');
    } finally {
        Psr18ClientDiscovery::setStrategies($strategies);
    }
})->with(['http', 'request', 'stream']);

it('rejects unknown region override keys rather than ignoring them', function () {
    expect(fn () => OukitelClient::create(regions: ['unknown' => RegionConfiguration::preset(Region::Eu)]))
        ->toThrow(ConfigurationMissing::class);
});

it('sets exact Guzzle connect and total timeouts and disables redirects', function () {
    $configured = HttpClientFactory::configureDiscovered(new GuzzleClient, new ClientOptions(timeoutSeconds: 11, connectTimeoutSeconds: 4));
    expect($configured)->toBeInstanceOf(GuzzleClient::class)
        ->and($configured->getConfig('timeout'))->toBe(11)
        ->and($configured->getConfig('connect_timeout'))->toBe(4)
        ->and($configured->getConfig('allow_redirects'))->toBeFalse();
});

it('uses exact Symfony timeouts only when the backend supports a separate connect deadline', function () {
    $observed = [];
    $client = new Psr18Client(new MockHttpClient(function ($method, $url, $options) use (&$observed) {
        $observed = $options;

        return new MockResponse('example');
    }), new Psr17Factory);
    if (version_compare(InstalledVersions::getVersion('symfony/http-client'), '8.1.0', '<')) {
        expect(fn () => HttpClientFactory::configureDiscovered($client, new ClientOptions))
            ->toThrow(TransportConfigurationFailed::class);
    } else {
        $configured = HttpClientFactory::configureDiscovered($client, new ClientOptions);
        $configured->sendRequest((new Psr17Factory)->createRequest('GET', 'https://example.test'));
        expect($observed['max_connect_duration'])->toEqual(3)
            ->and($observed['max_duration'])->toEqual(10)
            ->and($observed['max_redirects'])->toBe(0);
    }
    expect(OukitelClient::create($client, new Psr17Factory, new Psr17Factory))->toBeInstanceOf(OukitelClient::class);
});

it('requires explicit injection for unsupported discovered clients', function () {
    expect(fn () => HttpClientFactory::configureDiscovered(new Client(new Psr17Factory), new ClientOptions))
        ->toThrow(TransportConfigurationFailed::class);
});

it('discovers available default factories and either configures the backend or explains its unsupported deadline', function () {
    $discovered = Psr18ClientDiscovery::find();
    if ($discovered instanceof Psr18Client && version_compare(InstalledVersions::getVersion('symfony/http-client'), '8.1.0', '<')) {
        expect(fn () => OukitelClient::create())->toThrow(TransportConfigurationFailed::class);
    } else {
        expect(OukitelClient::create())->toBeInstanceOf(OukitelClient::class);
    }
});
