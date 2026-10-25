<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel;

use Closure;
use IvanVasechko\Oukitel\Clock\SystemClock;
use IvanVasechko\Oukitel\Controls\OukitelControls;
use IvanVasechko\Oukitel\DTOs\Capabilities;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\Device;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Session\SessionManager;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SensitiveParameter;

final readonly class Account
{
    /** @param Closure(int): void|null $controlDelay */
    public function __construct(
        private SessionManager $sessions,
        #[SensitiveParameter] private Credentials $credentials,
        private LoggerInterface $logger = new NullLogger,
        private ClockInterface $clock = new SystemClock,
        private ?Closure $controlDelay = null,
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

    /** @experimental Inferred capabilities do not guarantee support or safety on a device model. */
    public function capabilities(#[SensitiveParameter] DeviceId $device): Capabilities
    {
        return Capabilities::fromAttributes($this->rawAttributes($device));
    }

    /** @experimental Not validated on all models. May change without a major release. */
    public function controls(): OukitelControls
    {
        return new OukitelControls($this->sessions, $this->credentials, $this->logger, $this->clock, $this->controlDelay);
    }
}
