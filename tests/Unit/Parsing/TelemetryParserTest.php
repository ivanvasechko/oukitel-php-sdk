<?php

declare(strict_types=1);

use IvanVasechko\Oukitel\Exceptions\MalformedResponse;
use IvanVasechko\Oukitel\Parsing\ResponseParser;
use IvanVasechko\Oukitel\Parsing\Values;

it('accepts zero-padded integer fields without accepting integer overflow', function () {
    expect(Values::integer('0075', 'test'))->toBe(75)
        ->and(Values::integer('-0075', 'test'))->toBe(-75)
        ->and(Values::integer('000', 'test'))->toBe(0)
        ->and(Values::integer((string) PHP_INT_MIN, 'test'))->toBe(PHP_INT_MIN)
        ->and(Values::integer((string) PHP_INT_MAX, 'test'))->toBe(PHP_INT_MAX);
    expect(fn () => Values::integer(PHP_INT_MAX.'0', 'test'))
        ->toThrow(MalformedResponse::class);
});

it('preserves the audited telemetry scenarios with explicit outlet alias corrections', function (string $name): void {
    $root = dirname(__DIR__, 3);
    $parser = new ResponseParser;
    $payload = $parser->decode(file_get_contents($root.'/src/Testing/Fixtures/'.$name.'.json'));
    $expected = json_decode(file_get_contents($root.'/tests/Fixtures/expected/'.$name.'.json'), true, flags: JSON_THROW_ON_ERROR);
    $telemetry = $parser->telemetry($payload);

    foreach ($expected as $field => $value) {
        expect($telemetry->$field)->toEqual($value);
    }
})->with([
    'ac-output-off', 'charge-limit', 'charging-load-40w', 'charging-load-70w', 'charging',
    'fully-off', 'idle', 'no-wake-repeat', 'offline', 'on-battery', 'online', 'output-recovery',
    'passthrough', 'periodic-wake-repeat', 'periodic-wake', 'post-wake', 'stale-repeat',
]);

it('maps legacy structures and preserves zero false diagnostics and unknown fields', function (): void {
    $payload = ['code' => 200, 'data' => [
        'deviceData' => ['isOnline' => '1', 'signalStrength' => '-47'],
        'customizeTslInfo' => [
            ['resourceCode' => 'battery_percentage', 'resourceValce' => '0', 'dataType' => 'INT'],
            ['resourceCode' => 'AC_Info', 'resourceValce' => '{"1":false,"2":220,"3":230}', 'dataType' => 'STRUCT'],
            ['resourceCode' => 'DC_Info', 'resourceValce' => ['2' => 15], 'dataType' => 'STRUCT'],
            ['resourceCode' => 'USB_Info', 'resourceValce' => ['2' => 5, '3' => 7], 'dataType' => 'STRUCT'],
            ['resourceCode' => 'TypeC_Info', 'resourceValce' => ['2' => 20, '5' => 10], 'dataType' => 'STRUCT'],
            ['resourceCode' => 'custom_metric', 'resourceValce' => '42', 'dataType' => 'INT'],
            ['resourceCode' => 'missing', 'dataType' => 'STRING'],
            ['resourceCode' => 'nullable', 'resourceValce' => null, 'createTime' => '123'],
            ['resourceCode' => 'inverter_temp', 'resourceValce' => '24.5', 'dataType' => 'FLOAT'],
        ],
    ]];

    $telemetry = (new ResponseParser)->telemetry($payload);

    expect($telemetry->batteryPercent)->toBe(0);
    expect($telemetry->acOutputEnabled)->toBeFalse();
    expect([$telemetry->acOutputW, $telemetry->acOutputV, $telemetry->dcOutputW,
        $telemetry->usbOutputW, $telemetry->typeCOutputW])->toBe([220, 230.0, 15, 12, 30]);
    expect($telemetry->extra)->toBe(['custom_metric' => 42]);
    expect($telemetry->inverterTemperatureC)->toBe(24.5);
    expect($telemetry->diagnostics[6]['present'])->toBeFalse();
    expect($telemetry->diagnostics[7])->toBe([
        'resourceCode' => 'nullable', 'resourceValue' => null, 'dataType' => 'STRING',
        'present' => true, 'reportedAt' => '123',
    ]);
});

it('normalizes only the exact 5940 display sentinel', function (int $value, ?int $expected): void {
    $telemetry = (new ResponseParser)->telemetry(['code' => 200, 'data' => ['customizeTslInfo' => [
        ['resourceCode' => 'remain_time', 'resourceValce' => (string) $value, 'dataType' => 'INT'],
        ['resourceCode' => 'remain_charging_time', 'resourceValce' => $value, 'dataType' => 'INT'],
    ]]]);

    expect($telemetry->remainingDischargeMinutes)->toBe($expected);
    expect($telemetry->remainingChargeMinutes)->toBe($expected);
})->with([[5939, 5939], [5940, null], [5941, 5941]]);

it('leaves raw values unparsed while rejecting malformed normalized readings', function (mixed $value, string $type): void {
    $payload = ['code' => 200, 'data' => ['customizeTslInfo' => [
        ['resourceCode' => 'custom', 'resourceValce' => $value, 'dataType' => $type],
    ]]];
    $parser = new ResponseParser;

    expect($parser->rawAttributes($payload))->toHaveCount(1);
    expect(fn () => $parser->telemetry($payload))->toThrow(MalformedResponse::class);
})->with([['eighty', 'INT'], ['{bad-json', 'STRUCT'], [true, 'STRUCT'], ['12', 'STRUCT'], ['bad', 'FLOAT'], ['unknown', 'BOOL']]);

it('redacts nested arrays serialized JSON and sensitive raw fields', function (): void {
    $payload = ['code' => 200, 'data' => ['customizeTslInfo' => [
        ['resourceCode' => 'authToken', 'resourceValce' => 'fake-private-marker'],
        ['resourceCode' => 'custom', 'resourceValce' => '{"reading":12,"authKey":"fake-private-marker","nested":{"email":"fake-private-marker"}}', 'dataType' => 'STRUCT'],
        ['resourceCode' => 'future', 'resourceValce' => ['serial_number' => 'fake-private-marker'], 'dataType' => 'FUTURE'],
    ]]];
    $parser = new ResponseParser;

    expect(json_encode($parser->telemetry($payload), JSON_THROW_ON_ERROR))->not->toContain('fake-private-marker');
    expect(json_encode($parser->rawAttributes($payload), JSON_THROW_ON_ERROR))->not->toContain('fake-private-marker');
    expect($parser->telemetry($payload)->extra['authToken'])->toBe('[redacted]');
});

it('returns absent fields as null rather than zero', function (): void {
    $telemetry = (new ResponseParser)->telemetry(['code' => 200, 'data' => []]);

    expect($telemetry->batteryPercent)->toBeNull();
    expect($telemetry->isOnline)->toBeNull();
    expect($telemetry->usbOutputW)->toBeNull();
});
