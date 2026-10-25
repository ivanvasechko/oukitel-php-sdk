<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;
use IvanVasechko\Oukitel\Config\ClientOptions;
use IvanVasechko\Oukitel\Http\HttpClientFactory;
use IvanVasechko\Oukitel\OukitelClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Component\HttpClient\Response\MockResponse;

$discovered = HttpClientFactory::discover(new ClientOptions);
if (! $discovered instanceof Psr18Client || class_exists(Client::class)) {
    throw new RuntimeException('Symfony-only discovery selected an unexpected transport.');
}
OukitelClient::create();
$observed = [];
$client = new Psr18Client(new MockHttpClient(function (string $method, string $url, array $options) use (&$observed): MockResponse {
    $observed = $options;

    return new MockResponse('example');
}), new Psr17Factory);
$configured = HttpClientFactory::configureDiscovered($client, new ClientOptions);
$configured->sendRequest((new Psr17Factory)->createRequest('GET', 'https://example.test'));
if ($observed['max_connect_duration'] != 3 || $observed['max_duration'] != 10 || $observed['max_redirects'] !== 0) {
    throw new RuntimeException('Symfony deadlines or redirect policy differ from configured options.');
}
echo "Archive-installed SDK discovers Symfony and applies exact connect/total deadlines.\n";
