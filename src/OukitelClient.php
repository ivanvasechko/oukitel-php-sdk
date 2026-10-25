<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel;

use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr17FactoryDiscovery;
use IvanVasechko\Oukitel\Clock\SystemClock;
use IvanVasechko\Oukitel\Config\ClientOptions;
use IvanVasechko\Oukitel\Config\RegionConfiguration;
use IvanVasechko\Oukitel\Contracts\OukitelCloud;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\ConfigurationMissing;
use IvanVasechko\Oukitel\Exceptions\TransportConfigurationFailed;
use IvanVasechko\Oukitel\Http\AcceleronixCloudClient;
use IvanVasechko\Oukitel\Http\HttpClientFactory;
use IvanVasechko\Oukitel\Session\InMemorySessionStore;
use IvanVasechko\Oukitel\Session\Psr16SessionStore;
use IvanVasechko\Oukitel\Session\SessionManager;
use Psr\Clock\ClockInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use SensitiveParameter;

final class OukitelClient
{
    public function __construct(private readonly SessionManager $sessions) {}

    /** @param array<string, RegionConfiguration> $regions */
    public static function create(
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null,
        ?ClockInterface $clock = null,
        ?ClientOptions $options = null,
        array $regions = [],
    ): self {
        $options ??= new ClientOptions;
        $logger ??= new NullLogger;
        foreach (array_keys($regions) as $key) {
            if (! in_array($key, ['EU', 'US', 'CN'], true)) {
                throw new ConfigurationMissing;
            }
        }
        foreach (Region::cases() as $region) {
            $regions[$region->value] ??= RegionConfiguration::preset($region);
        }
        try {
            $requestFactory ??= Psr17FactoryDiscovery::findRequestFactory();
            $streamFactory ??= Psr17FactoryDiscovery::findStreamFactory();
        } catch (NotFoundException) {
            throw new TransportConfigurationFailed;
        }
        $httpClient ??= HttpClientFactory::discover($options);
        $cloud = new AcceleronixCloudClient($httpClient, $requestFactory, $streamFactory, $regions, $options, logger: $logger);

        return self::fromCloud($cloud, $cache, $logger, $clock, $options);
    }

    public static function fromCloud(
        OukitelCloud $cloud,
        ?CacheInterface $cache = null,
        ?LoggerInterface $logger = null,
        ?ClockInterface $clock = null,
        ?ClientOptions $options = null,
    ): self {
        $clock ??= new SystemClock;
        $logger ??= new NullLogger;
        $store = $cache === null ? new InMemorySessionStore($clock) : new Psr16SessionStore($cache, $logger);

        return new self(new SessionManager($cloud, $store, $clock, $options ?? new ClientOptions));
    }

    public function account(#[SensitiveParameter] Credentials $credentials): Account
    {
        return new Account($this->sessions, $credentials);
    }
}
