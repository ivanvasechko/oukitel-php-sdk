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
use IvanVasechko\Oukitel\Testing\FixtureLoader;
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

foreach (FixtureLoader::SCENARIOS as $scenario) {
    FixtureLoader::telemetry($scenario);
    FixtureLoader::rawAttributes($scenario);
}
$device = new DeviceId('example-device', 'example-product');
$fake = (new FakeOukitelCloud)->withDevices([])->withTelemetry($device, 'online');
$account = OukitelClient::fromCloud($fake)->account(new Credentials(Region::Eu, 'example@example.test', 'example-password'));
$account->devices();
$account->telemetry($device);
$account->rawAttributes($device);
$account->wake($device);
if ($fake->wakes() !== [$device]) {
    throw new RuntimeException('Archive-installed fake cloud did not record wake.');
}
$account->controls()->setUsbOutput($device, true);
$account->controls()->setAcChargingLimit($device, 50);
if ($fake->commands() !== [
    ['device' => $device, 'commands' => ['usb_switch' => true]],
    ['device' => $device, 'commands' => ['ac_charging_limit' => 50]],
] || ! $account->capabilities($device)->canControl(TslCode::UsbSwitch)) {
    throw new RuntimeException('Archive-installed controls or capabilities did not preserve their contract.');
}
$fake->refresh($fake->login(new Credentials(Region::Eu, 'example@example.test', 'example-password')));
if ($fake->refreshCount() !== 1) {
    throw new RuntimeException('Archive-installed fake refresh did not record the operation.');
}
$installed = __DIR__.'/vendor/ivanvasechko/oukitel-php-sdk';
foreach (['docs', 'tests', 'vendor', '.github', '.git', '.gitleaks.toml', 'composer.lock', 'phpstan.neon', 'phpunit.xml'] as $excluded) {
    if (file_exists($installed.'/'.$excluded)) {
        throw new RuntimeException('Development files leaked into the distribution.');
    }
}
echo "All 17 scenarios, refresh, controls and capabilities work from the distribution.\n";
