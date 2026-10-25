<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\DTOs;

use JsonSerializable;
use SensitiveParameter;

final readonly class DeviceId implements JsonSerializable
{
    public function __construct(
        #[SensitiveParameter] public string $deviceKey,
        #[SensitiveParameter] public string $productKey,
    ) {}

    /** @return array{deviceKey: string, productKey: string} */
    public function __debugInfo(): array
    {
        return ['deviceKey' => '[redacted]', 'productKey' => '[redacted]'];
    }

    /** @return array{deviceKey: string, productKey: string} */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }
}
