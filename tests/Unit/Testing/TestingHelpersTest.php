<?php

use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\Device;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\AuthenticationFailed;
use IvanVasechko\Oukitel\OukitelClient;
use IvanVasechko\Oukitel\Parsing\TelemetryParser;
use IvanVasechko\Oukitel\Testing\FakeOukitelCloud;
use IvanVasechko\Oukitel\Testing\FixtureLoader;
use IvanVasechko\Oukitel\Tests\Support\FrozenClock;

it('loads each bundled scenario through the production parser', function (string $name) {
    expect(FixtureLoader::telemetry($name))->toBeInstanceOf(Telemetry::class);
    foreach (FixtureLoader::rawAttributes($name) as $attribute) {
        expect($attribute)->toBeInstanceOf(TslAttribute::class);
    }
})->with(FixtureLoader::SCENARIOS);

it('rejects unknown or traversal fixture names without exposing paths', function (string $name) {
    expect(fn () => FixtureLoader::telemetry($name))->toThrow(InvalidArgumentException::class, 'Unknown Oukitel fixture name.');
})->with(['../online', '/etc/passwd', 'online.json', 'unknown', '', "online\0"]);

it('provides deterministic sessions and all fake cloud operations through account composition', function () {
    $clock = new FrozenClock;
    $id = new DeviceId('example-device', 'example-product');
    $devices = [new Device($id, 'Example device', 'Example model', true)];
    $fake = (new FakeOukitelCloud($clock))->withDevices($devices)->withTelemetry($id, 'online');
    $credentials = new Credentials(Region::Us, 'example@example.test', 'example-password');
    $session = $fake->login($credentials);
    expect($session->region)->toBe(Region::Us)->and($session->expiresAt)->toBe(1_800_003_600);
    $account = OukitelClient::fromCloud($fake, clock: $clock)->account($credentials);
    $account->verify();
    expect($account->devices())->toBe($devices)
        ->and($account->telemetry($id))->toEqual(FixtureLoader::telemetry('online'))
        ->and($account->rawAttributes($id))->toEqual(FixtureLoader::rawAttributes('online'))
        ->and($fake->wakes())->toBe([]);
    $account->wake($id);
    expect($fake->wakes())->toBe([$id]);
});

it('accepts typed telemetry and explicit unconverted raw attributes without fabricating values', function () {
    $fake = new FakeOukitelCloud(new FrozenClock);
    $id = new DeviceId('example', 'example');
    $session = $fake->login(new Credentials(Region::Eu, 'example', 'example'));
    $telemetry = new Telemetry(batteryPercent: 75);
    $fake->withTelemetry($id, $telemetry);
    expect($fake->telemetry($session, $id))->toBe($telemetry);
    expect(fn () => $fake->rawAttributes($session, $id))->toThrow(LogicException::class);
    $raw = [new TslAttribute('battery_percentage', '0075', 'INT')];
    $fake->withRawAttributes($id, $raw);
    expect($fake->rawAttributes($session, $id))->toBe($raw);
});

it('fails explicitly for unconfigured fake reads and supports intentionally empty device lists', function () {
    $fake = new FakeOukitelCloud(new FrozenClock);
    $session = $fake->login(new Credentials(Region::Eu, 'example', 'example'));
    $id = new DeviceId('example', 'example');
    expect(fn () => $fake->devices($session))->toThrow(LogicException::class);
    expect(fn () => $fake->telemetry($session, $id))->toThrow(LogicException::class);
    expect(fn () => $fake->rawAttributes($session, $id))->toThrow(LogicException::class);
    $fake->withDevices([]);
    expect($fake->devices($session))->toBe([]);
});

it('keeps fake data isolated by both product and device identity without delimiter collisions', function () {
    $fake = new FakeOukitelCloud(new FrozenClock);
    $one = new DeviceId('b|c', 'a');
    $two = new DeviceId('c', 'a|b');
    $fake->withTelemetry($one, new Telemetry(batteryPercent: 10))
        ->withTelemetry($two, new Telemetry(batteryPercent: 20));
    $session = $fake->login(new Credentials(Region::Eu, 'example', 'example'));
    expect($fake->telemetry($session, $one)->batteryPercent)->toBe(10)
        ->and($fake->telemetry($session, $two)->batteryPercent)->toBe(20);
});

it('simulates login exceptions without network discovery', function () {
    $error = new AuthenticationFailed;
    $fake = (new FakeOukitelCloud)->failLoginWith($error);
    $account = OukitelClient::fromCloud($fake)->account(new Credentials(Region::Eu, 'example', 'example'));
    expect(fn () => $account->verify())->toThrow(AuthenticationFailed::class);
});

it('redacts directly supplied typed attributes before conversion and diagnostics', function () {
    $telemetry = (new TelemetryParser)->parse([], [
        new TslAttribute('phone', '5550100', 'INT'),
        new TslAttribute('custom', '{"apiKey":"example-private","safe":7}', 'STRUCT'),
    ]);
    expect($telemetry->extra['phone'])->toBe('[redacted]')
        ->and($telemetry->extra['custom'])->toBe(['apiKey' => '[redacted]', 'safe' => 7]);
    $encoded = json_encode($telemetry, JSON_THROW_ON_ERROR);
    expect($encoded)->not->toContain('5550100')->not->toContain('example-private');
});
