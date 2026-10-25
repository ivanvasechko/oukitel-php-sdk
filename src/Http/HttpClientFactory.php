<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Http;

use Composer\InstalledVersions;
use GuzzleHttp\Client;
use Http\Discovery\Exception\NotFoundException;
use Http\Discovery\Psr18ClientDiscovery;
use IvanVasechko\Oukitel\Config\ClientOptions;
use IvanVasechko\Oukitel\Exceptions\TransportConfigurationFailed;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\HttpClient\Psr18Client;

final class HttpClientFactory
{
    public static function discover(ClientOptions $options): ClientInterface
    {
        try {
            $client = Psr18ClientDiscovery::find();
        } catch (NotFoundException) {
            throw new TransportConfigurationFailed;
        }

        return self::configureDiscovered($client, $options);
    }

    public static function configureDiscovered(ClientInterface $client, ClientOptions $options): ClientInterface
    {
        if ($client instanceof Client) {
            return new Client([
                'connect_timeout' => $options->connectTimeoutSeconds,
                'timeout' => $options->timeoutSeconds,
                'allow_redirects' => false,
            ]);
        }
        if ($client instanceof Psr18Client) {
            $version = InstalledVersions::getVersion('symfony/http-client');
            if ($version === null || version_compare($version, '8.1.0', '<')) {
                throw new TransportConfigurationFailed;
            }

            return $client->withOptions([
                'max_connect_duration' => $options->connectTimeoutSeconds,
                'max_duration' => $options->timeoutSeconds,
                'max_redirects' => 0,
            ]);
        }

        throw new TransportConfigurationFailed;
    }
}
