<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Parsing;

use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Enums\TslCode;
use IvanVasechko\Oukitel\Enums\TslDataType;
use SensitiveParameter;

/** @experimental */
final class ControlObservationParser
{
    /**
     * @experimental
     *
     * @param  list<TslAttribute>  $attributes
     * @param  array<string, mixed>  $commands
     * @return array{observed: array<string, mixed>, timestamps: array<string, ?int>}
     */
    public static function collect(array $attributes, #[SensitiveParameter] array $commands): array
    {
        $observed = array_fill_keys(array_keys($commands), null);
        $timestamps = array_fill_keys(array_keys($commands), null);
        $redactor = new Redactor;
        foreach ($attributes as $attribute) {
            if (! array_key_exists($attribute->resourceCode, $commands) || ! $attribute->present) {
                continue;
            }
            $code = TslCode::tryFrom($attribute->resourceCode);
            if ($redactor->isSensitive($attribute->resourceCode)) {
                $observed[$attribute->resourceCode] = '[redacted]';

                continue;
            }
            $value = $redactor->value($attribute->resourceValue, $attribute->resourceCode);
            $observed[$attribute->resourceCode] = match ($code) {
                TslCode::AcSwitch, TslCode::DcSwitch, TslCode::UsbSwitch => Values::boolean($value, 'control observation'),
                TslCode::AcChargingLimit, TslCode::HighFrequencyReporting => Values::integer($value, 'control observation'),
                default => match ($attribute->type()) {
                    TslDataType::Int => Values::integer($value, 'control observation'),
                    TslDataType::Bool => Values::boolean($value, 'control observation'),
                    TslDataType::Float, TslDataType::Double, TslDataType::Decimal => Values::number($value, 'control observation'),
                    TslDataType::Struct => Values::structure($value, 'control observation'),
                    default => $value,
                },
            };
            $time = $attribute->reportedAt;
            if ((is_int($time) || (is_string($time) && preg_match('/^\d+$/D', $time) === 1))) {
                $timestamps[$attribute->resourceCode] = Values::integer($time, 'control timestamp');
            }
        }

        return ['observed' => $observed, 'timestamps' => $timestamps];
    }
}
