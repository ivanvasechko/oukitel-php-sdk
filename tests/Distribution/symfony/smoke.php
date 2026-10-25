<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use GuzzleHttp\Client;
use IvanVasechko\Oukitel\Config\ClientOptions;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Enums\TslCode;
use IvanVasechko\Oukitel\Http\HttpClientFactory;
use IvanVasechko\Oukitel\OukitelClient;
use IvanVasechko\Oukitel\Testing\FakeOukitelCloud;
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

$device = new DeviceId('example-device', 'example-product');
$fake = (new FakeOukitelCloud)->withTelemetry($device, 'online');
$account = OukitelClient::fromCloud($fake)->account(new Credentials(Region::Eu, 'example@example.test', 'example-password'));
$account->controls()->setDcOutput($device, false);
if ($fake->commands() !== [['device' => $device, 'commands' => ['dc_switch' => false]]]
    || ! $account->capabilities($device)->canControl(TslCode::DcSwitch)) {
    throw new RuntimeException('Archive-installed experimental API did not load without development dependencies.');
}
echo "Archive-installed experimental controls and capabilities work with Symfony only.\n";
