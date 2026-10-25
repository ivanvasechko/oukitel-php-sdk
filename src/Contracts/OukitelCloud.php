<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Contracts;

use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\Device;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\DTOs\TslAttribute;

interface OukitelCloud
{
    public function login(Credentials $credentials): Session;

    public function refresh(Session $session): Session;

    /** @return list<Device> */
    public function devices(Session $session): array;

    public function telemetry(Session $session, DeviceId $device): Telemetry;

    /** @return list<TslAttribute> */
    public function rawAttributes(Session $session, DeviceId $device): array;

    public function wake(Session $session, DeviceId $device): void;
}
