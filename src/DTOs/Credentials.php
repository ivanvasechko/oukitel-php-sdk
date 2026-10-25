<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\DTOs;

use IvanVasechko\Oukitel\Enums\Region;
use JsonSerializable;
use SensitiveParameter;

final readonly class Credentials implements JsonSerializable
{
    public function __construct(
        public Region $region,
        #[SensitiveParameter] public string $login,
        #[SensitiveParameter] public string $password,
    ) {}

    /** @return array{region: string, login: string, password: string} */
    public function __debugInfo(): array
    {
        return ['region' => $this->region->value, 'login' => '[redacted]', 'password' => '[redacted]'];
    }

    /** @return array{region: string, login: string, password: string} */
    public function jsonSerialize(): array
    {
        return $this->__debugInfo();
    }
}
