<?php

use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Parsing\ControlObservationParser;

it('normalizes known control encodings and unknown reported types without fabricating missing data', function () {
    $result = ControlObservationParser::collect([
        new TslAttribute('ac_switch', 'false', 'BOOL', true, '1001'),
        new TslAttribute('ac_charging_limit', '50', 'INT', true, 1002),
        new TslAttribute('high_frequency_reporting', '3', 'ENUM', true, 1003),
        new TslAttribute('custom_int', '7', 'INT', true, 1004),
        new TslAttribute('custom_bool', '1', 'BOOL', true, 1005),
        new TslAttribute('custom_float', '2.5', 'FLOAT', true, 1006),
        new TslAttribute('custom_struct', '{"state":1}', 'STRUCT', true, 1007),
        new TslAttribute('custom_enum', 'mode-a', 'ENUM', true, 1008),
        new TslAttribute('ignored', true, 'BOOL', true, 1009),
        new TslAttribute('missing', false, 'BOOL', false, 1010),
        new TslAttribute('password', 'private', 'INT', true, 1011),
    ], array_fill_keys(['ac_switch', 'ac_charging_limit', 'high_frequency_reporting', 'custom_int',
        'custom_bool', 'custom_float', 'custom_struct', 'custom_enum', 'missing', 'absent', 'password'], null));

    expect($result['observed'])->toBe([
        'ac_switch' => false, 'ac_charging_limit' => 50, 'high_frequency_reporting' => 3,
        'custom_int' => 7, 'custom_bool' => true, 'custom_float' => 2.5, 'custom_struct' => ['state' => 1],
        'custom_enum' => 'mode-a', 'missing' => null, 'absent' => null, 'password' => '[redacted]',
    ]);
    expect($result['timestamps'])->toBe([
        'ac_switch' => 1001, 'ac_charging_limit' => 1002, 'high_frequency_reporting' => 1003,
        'custom_int' => 1004, 'custom_bool' => 1005, 'custom_float' => 1006, 'custom_struct' => 1007,
        'custom_enum' => 1008, 'missing' => null, 'absent' => null, 'password' => null,
    ]);
});
