<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\DTOs;

use IvanVasechko\Oukitel\Enums\Region;
use JsonSerializable;
use SensitiveParameter;

final readonly class Session implements JsonSerializable
{
    public function __construct(
        public Region $region,
        #[SensitiveParameter] public string $accessToken,
        #[SensitiveParameter] public string $refreshToken,
        public int $expiresAt,
        public ?int $refreshExpiresAt = null,
    ) {}

    /** @return array{region: string, accessToken: string, refreshToken: string, expiresAt: int, refreshExpiresAt: ?int} */
    public function __debugInfo(): array
    {
        return [
            'region' => $this->region->value,
            'accessToken' => '[redacted]',
            'refreshToken' => '[redacted]',
            'expiresAt' => $this->expiresAt,
            'refreshExpiresAt' => $this->refreshExpiresAt,
        ];
    }

    /** @return array{region: string, accessToken: string, refreshToken: string, expiresAt: int, refreshExpiresAt: ?int} */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }
}
