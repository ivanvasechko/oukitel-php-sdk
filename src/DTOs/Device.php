<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\DTOs;

final readonly class Device
{
    public function __construct(
        public DeviceId $id,
        public string $name,
        public ?string $productName,
        public ?bool $isOnline,
    ) {}
}
