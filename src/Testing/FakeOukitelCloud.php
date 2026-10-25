<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Testing;

use IvanVasechko\Oukitel\Clock\SystemClock;
use IvanVasechko\Oukitel\Contracts\ControlCloud;
use IvanVasechko\Oukitel\Contracts\OukitelCloud;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\Device;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Parsing\ControlValueParser;
use LogicException;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Throwable;

final class FakeOukitelCloud implements ControlCloud, OukitelCloud
{
    /** @var list<Device>|null */
    private ?array $devices = null;

    /** @var array<string, Telemetry> */
    private array $telemetry = [];

    /** @var array<string, list<TslAttribute>> */
    private array $attributes = [];

    /** @var list<DeviceId> */
    private array $wakes = [];

    private ?Throwable $loginFailure = null;

    private ?Throwable $refreshFailure = null;

    private int $refreshCalls = 0;

    /** @var list<array{device: DeviceId, commands: array<string, mixed>}> */
    private array $commands = [];

    private ?Throwable $controlFailure = null;

    public function __construct(private readonly ClockInterface $clock = new SystemClock) {}

    /** @param list<Device> $devices */
    public function withDevices(array $devices): self
    {
        $this->devices = $devices;

        return $this;
    }

    public function withTelemetry(#[SensitiveParameter] DeviceId $device, Telemetry|string $fixture): self
    {
        $key = $this->key($device);
        if ($fixture instanceof Telemetry) {
            $this->telemetry[$key] = $fixture;
            unset($this->attributes[$key]);
        } else {
            $this->telemetry[$key] = FixtureLoader::telemetry($fixture);
            $this->attributes[$key] = FixtureLoader::rawAttributes($fixture);
        }

        return $this;
    }

    /** @param list<TslAttribute> $attributes */
    public function withRawAttributes(#[SensitiveParameter] DeviceId $device, array $attributes): self
    {
        $this->attributes[$this->key($device)] = $attributes;

        return $this;
    }

    public function failLoginWith(Throwable $exception): self
    {
        $this->loginFailure = $exception;

        return $this;
    }

    public function login(#[SensitiveParameter] Credentials $credentials): Session
    {
        if ($this->loginFailure !== null) {
            throw $this->loginFailure;
        }

        return new Session($credentials->region, 'fake-access-token', 'fake-refresh-token',
            $this->clock->now()->getTimestamp() + 3600, $this->clock->now()->getTimestamp() + 2_592_000);
    }

    public function failRefreshWith(Throwable $exception): self
    {
        $this->refreshFailure = $exception;

        return $this;
    }

    public function refresh(#[SensitiveParameter] Session $session): Session
    {
        $this->refreshCalls++;
        if ($this->refreshFailure !== null) {
            throw $this->refreshFailure;
        }

        return new Session($session->region, 'fake-refreshed-access-token', 'fake-refreshed-refresh-token',
            $this->clock->now()->getTimestamp() + 3600, $this->clock->now()->getTimestamp() + 2_592_000);
    }

    public function refreshCount(): int
    {
        return $this->refreshCalls;
    }

    public function devices(#[SensitiveParameter] Session $session): array
    {
        return $this->devices ?? throw new LogicException('Configure fake devices with withDevices().');
    }

    public function telemetry(#[SensitiveParameter] Session $session, #[SensitiveParameter] DeviceId $device): Telemetry
    {
        return $this->telemetry[$this->key($device)] ?? throw new LogicException('Configure fake telemetry with withTelemetry().');
    }

    public function rawAttributes(#[SensitiveParameter] Session $session, #[SensitiveParameter] DeviceId $device): array
    {
        return $this->attributes[$this->key($device)] ?? throw new LogicException('Configure fake raw attributes with a fixture name or withRawAttributes().');
    }

    public function wake(#[SensitiveParameter] Session $session, #[SensitiveParameter] DeviceId $device): void
    {
        $this->wakes[] = $device;
    }

    /** @return list<DeviceId> */
    public function wakes(): array
    {
        return $this->wakes;
    }

    /** @experimental */
    public function failControlWith(Throwable $exception): self
    {
        $this->controlFailure = $exception;

        return $this;
    }

    /**
     * @experimental Records attempts without changing configured telemetry.
     *
     * @param  array<string, mixed>  $commands
     */
    public function control(#[SensitiveParameter] Session $session, #[SensitiveParameter] DeviceId $device, #[SensitiveParameter] array $commands): void
    {
        $this->commands[] = ['device' => $device, 'commands' => ControlValueParser::commands($commands, true)];
        if ($this->controlFailure !== null) {
            throw $this->controlFailure;
        }
    }

    /**
     * @experimental
     *
     * @return list<array{device: DeviceId, commands: array<string, mixed>}>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    private function key(#[SensitiveParameter] DeviceId $device): string
    {
        return hash('sha256', strlen($device->productKey).':'.$device->productKey.$device->deviceKey);
    }
}
