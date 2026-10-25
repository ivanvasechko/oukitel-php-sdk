<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;
use IvanVasechko\Oukitel\Config\ClientOptions;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Http\HttpClientFactory;
use IvanVasechko\Oukitel\OukitelClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\Psr18Client;

$http = HttpClientFactory::discover(new ClientOptions);
if (! $http instanceof Client || $http->getConfig('connect_timeout') !== 3 || $http->getConfig('timeout') !== 10) {
    throw new RuntimeException('Isolated Guzzle discovery did not configure exact deadlines.');
}
OukitelClient::create()->account(new Credentials(Region::Eu, 'example@example.test', 'example-password'));
if (class_exists(Psr18Client::class) || class_exists(Psr17Factory::class)) {
    throw new RuntimeException('Development HTTP implementations leaked into the isolated consumer.');
}
echo "Archive-installed SDK discovers Guzzle and factories without development dependencies.\n";
