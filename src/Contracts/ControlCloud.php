<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Contracts;

use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Session;

/** @experimental Internal transport capability; use Account::controls() for validated commands. */
interface ControlCloud
{
    /**
     * @experimental Must send at most once, including authentication failures.
     *
     * @param  array<string, mixed>  $commands
     */
    public function control(Session $session, DeviceId $device, array $commands): void;
}
