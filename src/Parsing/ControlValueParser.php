<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Parsing;

use InvalidArgumentException;
use IvanVasechko\Oukitel\Enums\TslCode;
use JsonException;
use SensitiveParameter;

final class ControlValueParser
{
    /**
     * @param  array<array-key, mixed>  $commands
     * @return array<string, mixed>
     */
    public static function commands(#[SensitiveParameter] array $commands, bool $unsafe = false): array
    {
        if ($commands === []) {
            throw new InvalidArgumentException('At least one control code is required.');
        }
        $validated = [];
        foreach ($commands as $code => $value) {
            if (! is_string($code) || preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,127}$/D', $code) !== 1) {
                throw new InvalidArgumentException('Invalid control code.');
            }
            $known = TslCode::tryFrom($code);
            if ($known !== null && $known->isControllable()) {
                $valid = match ($known) {
                    TslCode::AcSwitch, TslCode::DcSwitch, TslCode::UsbSwitch => is_bool($value),
                    TslCode::AcChargingLimit => is_int($value) && in_array($value, TslCode::AC_CHARGING_LIMIT_PERCENTAGES, true),
                    TslCode::HighFrequencyReporting => $value === 3,
                    default => false,
                };
                if (! $valid) {
                    throw new InvalidArgumentException('Invalid value for an allow-listed control.');
                }
            } elseif (! $unsafe) {
                throw new InvalidArgumentException('Control code is not allow-listed; unsafe() requires explicit opt-in.');
            }
            self::assertJsonValue($value);
            $validated[$code] = $value;
        }
        try {
            json_encode($validated, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('Control values must be valid JSON.');
        }

        return $validated;
    }

    private static function assertJsonValue(mixed $value, int $depth = 0): void
    {
        if ($depth > 64) {
            throw new InvalidArgumentException('Control value nesting exceeds 64 levels.');
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                self::assertJsonValue($item, $depth + 1);
            }

            return;
        }
        if ($value === null || is_string($value) || is_int($value) || is_bool($value)
            || (is_float($value) && is_finite($value))) {
            return;
        }
        throw new InvalidArgumentException('Control values must contain only JSON data.');
    }
}
