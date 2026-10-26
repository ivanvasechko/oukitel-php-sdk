<?php

declare(strict_types=1);

$root = $argv[1] ?? dirname(__DIR__, 2);
require $argv[2] ?? $root.'/vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

$readme = file_get_contents($root.'/README.md');
if ($readme === false || preg_match_all('/^```php\R(.*?)^```/ms', $readme, $matches) < 1) {
    throw new RuntimeException('README must contain PHP examples.');
}

foreach ($matches[1] as $index => $snippet) {
    $path = tempnam(sys_get_temp_dir(), 'oukitel-doc-');
    if ($path === false) {
        throw new RuntimeException('Cannot create PHP lint file.');
    }
    try {
        if (file_put_contents($path, "<?php\n".$snippet) === false) {
            throw new RuntimeException('Cannot write PHP lint file.');
        }
        passthru(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path), $status);
        if ($status !== 0) {
            throw new RuntimeException('README PHP block '.($index + 1).' failed lint.');
        }
    } finally {
        unlink($path);
    }
}

$login = json_encode(['code' => 200, 'data' => [
    'accessToken' => ['token' => 'synthetic-access', 'expirationTime' => time() + 3600],
    'refreshToken' => ['token' => 'synthetic-refresh'],
]], JSON_THROW_ON_ERROR);
$devices = json_encode(['code' => 200, 'data' => ['list' => [[
    'deviceKey' => 'example-device', 'productKey' => 'example-product',
    'deviceName' => 'Example station', 'isOnline' => true,
]]]], JSON_THROW_ON_ERROR);
$telemetry = file_get_contents($root.'/src/Testing/Fixtures/online.json');
if ($telemetry === false) {
    throw new RuntimeException('Synthetic telemetry fixture is missing.');
}
$mock = new MockHandler([
    new Response(200, [], $login), new Response(200, [], $login),
    new Response(200, [], $devices), new Response(200, [], $telemetry),
    new Response(200, [], $telemetry), new Response(200, [], '{"code":200}'),
]);
$history = [];
$handler = HandlerStack::create($mock);
$handler->push(Middleware::history($history));
$http = new Client(['handler' => $handler]);
$snippet = str_replace(
    ["require 'vendor/autoload.php';", 'OukitelClient::create()'],
    ['', 'OukitelClient::create(httpClient: $http)'],
    $matches[1][0],
    $replacements,
);
if ($replacements !== 2) {
    throw new RuntimeException('Quick start bootstrap changed; update its offline harness.');
}
eval($snippet);
if (count($history) !== 6 || $mock->count() !== 0
    || count($devices) !== 1 || $telemetry->batteryPercent !== 64 || count($attributes) === 0
    || $history[5]['request']->getUri()->getPath() !== '/v2/binding/enduserapi/batchControlDevice') {
    throw new RuntimeException('README quick start did not exercise login, list, reads and wake.');
}

$offline = array_values(array_filter($matches[1], static fn (string $snippet): bool => str_contains($snippet, 'new FakeOukitelCloud')));
if (count($offline) !== 1) {
    throw new RuntimeException('README must contain one offline testing example.');
}
eval('use IvanVasechko\\Oukitel\\OukitelClient; use IvanVasechko\\Oukitel\\DTOs\\Credentials; use IvanVasechko\\Oukitel\\Enums\\Region;'.$offline[0]);
if ($wakes !== [$device] || $snapshot->isOnline !== false) {
    throw new RuntimeException('README offline example did not preserve fake behavior.');
}
echo 'README PHP blocks lint and execute offline successfully.'.PHP_EOL;
