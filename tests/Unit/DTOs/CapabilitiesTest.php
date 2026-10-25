<?php

use IvanVasechko\Oukitel\DTOs\Capabilities;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Enums\TslCode;
use IvanVasechko\Oukitel\OukitelClient;
use IvanVasechko\Oukitel\Testing\FakeOukitelCloud;
use IvanVasechko\Oukitel\Testing\FixtureLoader;
use IvanVasechko\Oukitel\Tests\Support\FrozenClock;

it('infers known readable and controllable codes without claiming unknown model support', function () {
    $capabilities = Capabilities::fromAttributes([
        new TslAttribute('battery_percentage', 75, 'INT'),
        new TslAttribute('ac_switch', null, 'BOOL', false),
        new TslAttribute('ac_switch', false, 'BOOL'),
        new TslAttribute('unrecognized', 1, 'INT'),
    ]);

    expect($capabilities->readable)->toBe([TslCode::BatteryPercentage, TslCode::AcSwitch]);
    expect($capabilities->controllable)->toBe([TslCode::AcSwitch]);
    expect($capabilities->canRead(TslCode::BatteryPercentage))->toBeTrue();
    expect($capabilities->canControl(TslCode::BatteryPercentage))->toBeFalse();
    expect($capabilities->canControl(TslCode::AcSwitch))->toBeTrue();
    expect($capabilities->canRead(TslCode::DcSwitch))->toBeFalse();
    expect(Capabilities::fromAttributes([])->readable)->toBe([]);
});

it('builds account capabilities from every bundled fixture without writing to the device', function (string $scenario) {
    $clock = new FrozenClock;
    $device = new DeviceId('example-device', 'example-product');
    $fake = (new FakeOukitelCloud($clock))->withTelemetry($device, $scenario);
    $account = OukitelClient::fromCloud($fake, clock: $clock)->account(new Credentials(Region::Eu, 'user@example.test', 'example'));

    $capabilities = $account->capabilities($device);

    $actual = array_map(fn (TslCode $code): string => $code->value, $capabilities->controllable);
    sort($actual);
    $expected = match ($scenario) {
        'offline', 'online', 'post-wake' => ['ac_charging_limit', 'ac_switch', 'dc_switch', 'high_frequency_reporting', 'usb_switch'],
        'no-wake-repeat' => [],
        'charge-limit' => ['ac_charging_limit', 'ac_switch'],
        default => ['ac_switch'],
    };
    expect($actual)->toBe($expected);
    expect($capabilities->canRead(TslCode::BatteryPercentage))->toBe($scenario !== 'charge-limit');
    expect($capabilities->canControl(TslCode::BatteryPercentage))->toBeFalse();
    expect($fake->commands())->toBe([]);
    expect($fake->wakes())->toBe([]);
})->with(FixtureLoader::SCENARIOS);
