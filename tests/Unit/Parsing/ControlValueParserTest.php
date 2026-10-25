<?php

use IvanVasechko\Oukitel\Parsing\ControlValueParser;

it('validates every integer in the charging-limit policy independently of fixtures', function () {
    foreach (range(3, 100) as $percent) {
        expect(ControlValueParser::commands(['ac_charging_limit' => $percent]))->toBe(['ac_charging_limit' => $percent]);
    }
});

it('rejects unsafe encodings and invalid control commands', function (array $commands) {
    expect(fn () => ControlValueParser::commands($commands))->toThrow(InvalidArgumentException::class);
})->with([
    [[]], [[0 => true]], [['bad-code' => true]], [["bad\ncode" => true]],
    [['unknown' => true]], [['battery_percentage' => 50]],
    [['ac_switch' => 1]], [['dc_switch' => 'false']], [['usb_switch' => null]],
    [['ac_charging_limit' => 2]], [['ac_charging_limit' => 101]],
    [['ac_charging_limit' => 50.0]], [['ac_charging_limit' => '50']],
    [['high_frequency_reporting' => 0]], [['high_frequency_reporting' => '3']],
]);

it('permits unknown JSON data only with explicit opt-in and keeps known controls validated', function () {
    $commands = ['custom_code' => ['nested' => [null, 2, 'example', false, 1.5]]];

    expect(ControlValueParser::commands($commands, true))->toBe($commands);
    expect(fn () => ControlValueParser::commands(['ac_charging_limit' => 2], true))->toThrow(InvalidArgumentException::class);
    expect(fn () => ControlValueParser::commands(['custom_code' => new stdClass], true))->toThrow(InvalidArgumentException::class);
    expect(fn () => ControlValueParser::commands(['custom_code' => INF], true))->toThrow(InvalidArgumentException::class);
    expect(fn () => ControlValueParser::commands(['custom_code' => "\xFF"], true))->toThrow(InvalidArgumentException::class);
    $nested = true;
    for ($depth = 0; $depth < 66; $depth++) {
        $nested = [$nested];
    }
    expect(fn () => ControlValueParser::commands(['custom_code' => $nested], true))->toThrow(InvalidArgumentException::class);
});
