<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\DTOs;

final readonly class Telemetry
{
    /**
     * @param  array<string, mixed>  $extra
     * @param  list<array{resourceCode: string, resourceValue: mixed, dataType: string}>  $rawTsl
     * @param  list<array{resourceCode: string, resourceValue: mixed, dataType: string, present: bool, reportedAt: int|string|null}>  $diagnostics
     */
    public function __construct(
        public ?bool $isOnline = null,
        public ?int $wifiSignalDbm = null,
        public ?int $batteryPercent = null,
        public ?int $remainingDischargeMinutes = null,
        public ?int $remainingChargeMinutes = null,
        public ?int $totalInputW = null,
        public ?int $totalOutputW = null,
        public ?int $acInputW = null,
        public ?int $dcInputW = null,
        public ?float $temperatureC = null,
        public ?float $inverterTemperatureC = null,
        public ?int $acChargingLimitPercent = null,
        public ?bool $acOutputEnabled = null,
        public ?int $acOutputW = null,
        public ?float $acOutputV = null,
        public ?int $dcOutputW = null,
        public ?int $usbOutputW = null,
        public ?int $typeCOutputW = null,
        public array $extra = [],
        public array $rawTsl = [],
        public array $diagnostics = [],
    ) {}
}
