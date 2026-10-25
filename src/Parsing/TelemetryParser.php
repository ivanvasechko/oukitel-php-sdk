<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Parsing;

use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Enums\TslDataType;

final class TelemetryParser
{
    /**
     * @param  array<string, mixed>  $deviceData
     * @param  list<TslAttribute>  $attributes
     */
    public function parse(array $deviceData, array $attributes): Telemetry
    {
        $values = [];
        $raw = [];
        $diagnostics = [];
        foreach ($attributes as $attribute) {
            $diagnostics[] = [
                'resourceCode' => $attribute->resourceCode, 'resourceValue' => $attribute->resourceValue,
                'dataType' => $attribute->dataType, 'present' => $attribute->present, 'reportedAt' => $attribute->reportedAt,
            ];
            if (! $attribute->present || $attribute->resourceValue === null) {
                continue;
            }
            $field = 'customizeTslInfo.resourceValce';
            $value = match ($attribute->type()) {
                TslDataType::Int => Values::integer($attribute->resourceValue, $field),
                TslDataType::Float, TslDataType::Double, TslDataType::Decimal => Values::number($attribute->resourceValue, $field),
                TslDataType::Bool => Values::boolean($attribute->resourceValue, $field),
                TslDataType::Struct => Values::structure($attribute->resourceValue, $field),
                default => $attribute->resourceValue,
            };
            $values[$attribute->resourceCode] = $value;
            $raw[] = ['resourceCode' => $attribute->resourceCode, 'resourceValue' => $value, 'dataType' => $attribute->dataType];
        }
        $ac = Values::structure($values['AC_Info'] ?? $values['ac_data'] ?? null, 'AC_Info');
        $dc = Values::structure($values['DC_Info'] ?? $values['dc_data'] ?? null, 'DC_Info');
        $usb = Values::structure($values['USB_Info'] ?? $values['usb_data'] ?? null, 'USB_Info');
        $typeC = Values::structure($values['TypeC_Info'] ?? $values['typec_data'] ?? null, 'TypeC_Info');
        $known = [
            'battery_percentage', 'remain_time', 'remaining_time', 'remain_charging_time', 'remaining_charge_time',
            'total_input_power', 'total_output_power', 'ac_input', 'dc_input', 'temp', 'inverter_temp',
            'ac_charging_limit', 'AC_Info', 'ac_data', 'DC_Info', 'dc_data', 'USB_Info', 'usb_data',
            'TypeC_Info', 'typec_data', 'ac_switch', 'ac_output_power', 'ac_output_voltage', 'dc_output_power',
        ];
        $extra = array_diff_key($values, array_fill_keys($known, true));
        $online = null;
        foreach (['isOnline', 'onlineStatus', 'online'] as $key) {
            if (isset($deviceData[$key])) {
                $online = Values::boolean($deviceData[$key], 'deviceData.online');
                break;
            }
        }

        return new Telemetry(
            isOnline: $online,
            wifiSignalDbm: Values::integer($deviceData['signalStrength'] ?? null, 'signalStrength'),
            batteryPercent: Values::integer($values['battery_percentage'] ?? null, 'battery_percentage'),
            remainingDischargeMinutes: $this->minutes($values['remain_time'] ?? $values['remaining_time'] ?? null),
            remainingChargeMinutes: $this->minutes($values['remain_charging_time'] ?? $values['remaining_charge_time'] ?? null),
            totalInputW: Values::integer($values['total_input_power'] ?? null, 'total_input_power'),
            totalOutputW: Values::integer($values['total_output_power'] ?? null, 'total_output_power'),
            acInputW: Values::integer($values['ac_input'] ?? null, 'ac_input'),
            dcInputW: Values::integer($values['dc_input'] ?? null, 'dc_input'),
            temperatureC: Values::number($values['temp'] ?? null, 'temp'),
            inverterTemperatureC: Values::number($values['inverter_temp'] ?? null, 'inverter_temp'),
            acChargingLimitPercent: Values::integer($values['ac_charging_limit'] ?? null, 'ac_charging_limit'),
            acOutputEnabled: Values::boolean($this->first($ac, [1, 'ac_switch']) ?? $values['ac_switch'] ?? null, 'AC_Info.switch'),
            acOutputW: Values::integer($this->first($ac, [2, 'ac1_output']) ?? $values['ac_output_power'] ?? null, 'AC_Info.power'),
            acOutputV: Values::number($this->first($ac, [3, 'ac1_output_voltage']) ?? $values['ac_output_voltage'] ?? null, 'AC_Info.voltage'),
            dcOutputW: Values::integer($this->first($dc, [2, 'car1_output']) ?? $values['dc_output_power'] ?? null, 'DC_Info.power'),
            usbOutputW: $this->sum($usb, [[2, 'USB_QC1_output'], [3, 'USB_QC2_output']]),
            typeCOutputW: $this->sum($typeC, [[2, 'Typec1_output'], [5, 'Typec2_output'], [6, 'Typec3_output'], [7, 'Typec4_output']]),
            extra: $extra,
            rawTsl: $raw,
            diagnostics: $diagnostics,
        );
    }

    /** @param array<array-key, mixed>|null $structure
     * @param  list<int|string>  $keys
     */
    private function first(?array $structure, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (isset($structure[$key])) {
                return $structure[$key];
            }
        }

        return null;
    }

    /** @param array<array-key, mixed>|null $structure
     * @param  list<list<int|string>>  $fields
     */
    private function sum(?array $structure, array $fields): ?int
    {
        $sum = null;
        foreach ($fields as $keys) {
            $value = Values::integer($this->first($structure, $keys), 'outlet power');
            if ($value !== null) {
                $sum = ($sum ?? 0) + $value;
            }
        }

        return $sum;
    }

    private function minutes(mixed $value): ?int
    {
        $minutes = Values::integer($value, 'remaining minutes');

        return $minutes === 5940 ? null : $minutes;
    }
}
