<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Enums;

/** @experimental Reported codes imply capabilities, not guaranteed support on every model. */
enum TslCode: string
{
    case AcSwitch = 'ac_switch';
    case DcSwitch = 'dc_switch';
    case UsbSwitch = 'usb_switch';
    case AcChargingLimit = 'ac_charging_limit';
    case HighFrequencyReporting = 'high_frequency_reporting';
    case BatteryPercentage = 'battery_percentage';
    case RemainTime = 'remain_time';
    case RemainingTime = 'remaining_time';
    case RemainChargingTime = 'remain_charging_time';
    case RemainingChargeTime = 'remaining_charge_time';
    case TotalInputPower = 'total_input_power';
    case TotalOutputPower = 'total_output_power';
    case AcInput = 'ac_input';
    case DcInput = 'dc_input';
    case Temperature = 'temp';
    case InverterTemperature = 'inverter_temp';
    case AcInfo = 'AC_Info';
    case AcData = 'ac_data';
    case DcInfo = 'DC_Info';
    case DcData = 'dc_data';
    case UsbInfo = 'USB_Info';
    case UsbData = 'usb_data';
    case TypeCInfo = 'TypeC_Info';
    case TypeCData = 'typec_data';
    case AcOutputPower = 'ac_output_power';
    case AcOutputVoltage = 'ac_output_voltage';
    case DcOutputPower = 'dc_output_power';

    /** Charging-limit policy: integer percentages 3-100; model compatibility may vary. */
    public const AC_CHARGING_LIMIT_PERCENTAGES = [
        3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20,
        21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40,
        41, 42, 43, 44, 45, 46, 47, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60,
        61, 62, 63, 64, 65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80,
        81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96, 97, 98, 99, 100,
    ];

    /** @experimental Allow-listed controls; reported presence is not a safety guarantee. */
    public function isControllable(): bool
    {
        return match ($this) {
            self::AcSwitch, self::DcSwitch, self::UsbSwitch,
            self::AcChargingLimit, self::HighFrequencyReporting => true,
            default => false,
        };
    }
}
