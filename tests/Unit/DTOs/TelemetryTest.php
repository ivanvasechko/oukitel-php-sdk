<?php

declare(strict_types=1);

use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Enums\TslDataType;

it('distinguishes absent telemetry from explicit zero and false readings', function (): void {
    expect((new Telemetry)->batteryPercent)->toBeNull();
    $telemetry = new Telemetry(batteryPercent: 0, acOutputEnabled: false);
    expect($telemetry->batteryPercent)->toBe(0);
    expect($telemetry->acOutputEnabled)->toBeFalse();
});

it('preserves raw values and unknown type names without conversion', function (): void {
    $known = new TslAttribute('metric', '42', 'int');
    $unknown = new TslAttribute('future', 'unconverted', 'FUTURE_TYPE', false, '123');

    expect($known->type())->toBe(TslDataType::Int);
    expect($known->resourceValue)->toBe('42');
    expect($unknown->type())->toBe(TslDataType::Unknown);
    expect($unknown->dataType)->toBe('FUTURE_TYPE');
    expect($unknown->present)->toBeFalse();
    expect($unknown->reportedAt)->toBe('123');
});
