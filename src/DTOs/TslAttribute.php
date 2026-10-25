<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\DTOs;

use IvanVasechko\Oukitel\Enums\TslDataType;

final readonly class TslAttribute
{
    public function __construct(
        public string $resourceCode,
        public mixed $resourceValue,
        public string $dataType,
        public bool $present = true,
        public int|string|null $reportedAt = null,
    ) {}

    public function type(): TslDataType
    {
        return TslDataType::tryFrom(strtoupper($this->dataType)) ?? TslDataType::Unknown;
    }
}
