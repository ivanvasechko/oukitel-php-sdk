<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Tests\Support;

use IvanVasechko\Oukitel\Contracts\OukitelCloud;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\OukitelException;
use IvanVasechko\Oukitel\Exceptions\SessionExpired;

final class ScriptedCloud implements OukitelCloud
{
    public int $logins = 0;

    public int $expiries = 0;

    public int $refreshes = 0;

    public ?int $refreshExpiresAt = null;

    public ?Session $refreshSession = null;

    public ?OukitelException $refreshFailure = null;

    public int $expiresAt = 1_800_003_600;

    public ?Region $loginRegion = null;

    public ?OukitelException $loginFailure = null;

    public ?OukitelException $operationFailure = null;

    /** @var list<string> */
    public array $calls = [];

    /** @var list<string> */
    public array $tokens = [];

    public function login(Credentials $credentials): Session
    {
        $this->logins++;
        if ($this->loginFailure !== null) {
            throw $this->loginFailure;
        }

        return new Session($this->loginRegion ?? $credentials->region, 'example-token-'.$this->logins,
            'example-refresh', $this->expiresAt, $this->refreshExpiresAt);
    }

    public function refresh(Session $session): Session
    {
        $this->refreshes++;
        if ($this->refreshFailure !== null) {
            throw $this->refreshFailure;
        }

        return $this->refreshSession ?? new Session($session->region, 'example-refreshed-'.$this->refreshes,
            'example-rotated-refresh', 1_800_007_200, $session->refreshExpiresAt);
    }

    public function devices(Session $session): array
    {
        $this->record('devices', $session);

        return [];
    }

    public function telemetry(Session $session, DeviceId $device): Telemetry
    {
        $this->record('telemetry', $session);

        return new Telemetry(batteryPercent: 75);
    }

    public function rawAttributes(Session $session, DeviceId $device): array
    {
        $this->record('rawAttributes', $session);

        return [];
    }

    public function wake(Session $session, DeviceId $device): void
    {
        $this->record('wake', $session);
    }

    private function record(string $operation, Session $session): void
    {
        $this->calls[] = $operation;
        $this->tokens[] = $session->accessToken;
        if ($this->expiries > 0) {
            $this->expiries--;
            throw new SessionExpired;
        }
        if ($this->operationFailure !== null) {
            throw $this->operationFailure;
        }
    }
}
