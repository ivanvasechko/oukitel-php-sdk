<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel;

use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\Device;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Session\SessionManager;
use SensitiveParameter;

final readonly class Account
{
    public function __construct(
        private SessionManager $sessions,
        #[SensitiveParameter] private Credentials $credentials,
    ) {}

    public function verify(): void
    {
        $this->sessions->verify($this->credentials);
    }

    /** @return list<Device> */
    public function devices(): array
    {
        return $this->sessions->devices($this->credentials);
    }

    public function telemetry(#[SensitiveParameter] DeviceId $device): Telemetry
    {
        return $this->sessions->telemetry($this->credentials, $device);
    }

    /** @return list<TslAttribute> */
    public function rawAttributes(#[SensitiveParameter] DeviceId $device): array
    {
        return $this->sessions->rawAttributes($this->credentials, $device);
    }

    public function wake(#[SensitiveParameter] DeviceId $device): void
    {
        $this->sessions->wake($this->credentials, $device);
    }
}
