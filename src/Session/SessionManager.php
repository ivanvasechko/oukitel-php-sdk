<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Session;

use Closure;
use IvanVasechko\Oukitel\Clock\SystemClock;
use IvanVasechko\Oukitel\Config\ClientOptions;
use IvanVasechko\Oukitel\Contracts\OukitelCloud;
use IvanVasechko\Oukitel\Contracts\SessionStore;
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\DTOs\Device;
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Exceptions\AuthenticationFailed;
use IvanVasechko\Oukitel\Exceptions\LoginCoolingDown;
use IvanVasechko\Oukitel\Exceptions\MalformedResponse;
use IvanVasechko\Oukitel\Exceptions\RefreshInProgress;
use IvanVasechko\Oukitel\Exceptions\SessionExpired;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

final class SessionManager
{
    private const REFRESH_LOCK_SECONDS = 30;

    public function __construct(
        private readonly OukitelCloud $cloud,
        private readonly SessionStore $store,
        private readonly ClockInterface $clock = new SystemClock,
        private readonly ClientOptions $options = new ClientOptions,
    ) {}

    public function verify(#[SensitiveParameter] Credentials $credentials): void
    {
        $this->cloud->login($credentials);
    }

    /** @return list<Device> */
    public function devices(#[SensitiveParameter] Credentials $credentials): array
    {
        return $this->withSession($credentials, fn (Session $session): array => $this->cloud->devices($session));
    }

    public function telemetry(#[SensitiveParameter] Credentials $credentials, #[SensitiveParameter] DeviceId $device): Telemetry
    {
        return $this->withSession($credentials, fn (Session $session): Telemetry => $this->cloud->telemetry($session, $device));
    }

    /** @return list<TslAttribute> */
    public function rawAttributes(#[SensitiveParameter] Credentials $credentials, #[SensitiveParameter] DeviceId $device): array
    {
        return $this->withSession($credentials, fn (Session $session): array => $this->cloud->rawAttributes($session, $device));
    }

    public function wake(#[SensitiveParameter] Credentials $credentials, #[SensitiveParameter] DeviceId $device): void
    {
        $this->withSession($credentials, function (Session $session) use ($device): void {
            $this->cloud->wake($session, $device);
        });
    }

    /**
     * @internal
     *
     * @template T
     *
     * @param  Closure(Session, OukitelCloud): T  $operation
     * @return T
     */
    public function withSessionOnce(#[SensitiveParameter] Credentials $credentials, Closure $operation): mixed
    {
        $session = $this->sessionFor($credentials);
        try {
            return $operation($session, $this->cloud);
        } catch (SessionExpired $exception) {
            $this->store->delete($this->key('session', $credentials));
            throw $exception;
        }
    }

    /**
     * @template T
     *
     * @param  Closure(Session): T  $operation
     * @return T
     */
    private function withSession(#[SensitiveParameter] Credentials $credentials, Closure $operation): mixed
    {
        for ($attempt = 0; ; $attempt++) {
            $session = $this->sessionFor($credentials);
            try {
                return $operation($session);
            } catch (SessionExpired $exception) {
                $this->store->delete($this->key('session', $credentials));
                if ($attempt === 1) {
                    throw $exception;
                }
            }
        }
    }

    private function sessionFor(#[SensitiveParameter] Credentials $credentials): Session
    {
        $key = $this->key('session', $credentials);
        $session = $this->store->get($key);
        if ($session !== null && $this->isFresh($session, $credentials)) {
            return $session;
        }
        if ($this->options->useRefreshToken && $session !== null && $this->canRefresh($session, $credentials)) {
            return $this->refreshFor($credentials);
        }

        return $this->loginFor($credentials);
    }

    private function isFresh(#[SensitiveParameter] Session $session, #[SensitiveParameter] Credentials $credentials): bool
    {
        return $session->region === $credentials->region
            && $session->expiresAt > $this->clock->now()->getTimestamp() + $this->options->refreshBeforeSeconds;
    }

    private function canRefresh(#[SensitiveParameter] Session $session, #[SensitiveParameter] Credentials $credentials): bool
    {
        return $session->region === $credentials->region && $session->refreshToken !== ''
            && ($session->refreshExpiresAt ?? $session->expiresAt) > $this->clock->now()->getTimestamp();
    }

    private function refreshFor(#[SensitiveParameter] Credentials $credentials): Session
    {
        $key = $this->key('session', $credentials);
        $lockKey = $this->key('session:refreshing', $credentials);
        $owner = $this->store->acquireRefreshLock($lockKey, self::REFRESH_LOCK_SECONDS);
        if ($owner === null) {
            $latest = $this->store->get($key);
            if ($latest !== null && $this->isFresh($latest, $credentials)) {
                return $latest;
            }
            throw new RefreshInProgress;
        }
        try {
            $session = $this->store->get($key);
            if ($session !== null && $this->isFresh($session, $credentials)) {
                return $session;
            }
            if ($session === null || ! $this->canRefresh($session, $credentials)) {
                return $this->loginFor($credentials);
            }
            try {
                $refreshed = $this->cloud->refresh($session);
            } catch (AuthenticationFailed|SessionExpired) {
                return $this->loginFor($credentials);
            }
            if ($refreshed->region !== $credentials->region || $refreshed->expiresAt <= $this->clock->now()->getTimestamp()) {
                throw new MalformedResponse('refresh session');
            }

            return $this->storeSession($credentials, $refreshed);
        } finally {
            $this->store->releaseRefreshLock($lockKey, $owner);
        }
    }

    private function loginFor(#[SensitiveParameter] Credentials $credentials): Session
    {
        $key = $this->key('session', $credentials);
        $this->store->delete($key);
        $cooldownKey = $this->key('login-cooldown', $credentials);
        if ($this->store->isCoolingDown($cooldownKey)) {
            throw new LoginCoolingDown;
        }
        try {
            $session = $this->cloud->login($credentials);
        } catch (AuthenticationFailed $exception) {
            if ($this->options->loginFailureCooldownMinutes > 0) {
                $this->store->coolDown($cooldownKey, $this->options->loginFailureCooldownMinutes * 60);
            }
            throw $exception;
        }
        if ($session->region !== $credentials->region) {
            throw new MalformedResponse('login region');
        }

        return $this->storeSession($credentials, $session);
    }

    private function storeSession(#[SensitiveParameter] Credentials $credentials, #[SensitiveParameter] Session $session): Session
    {
        $retainUntil = $this->options->useRefreshToken
            ? max($session->expiresAt, $session->refreshExpiresAt ?? $session->expiresAt)
            : $session->expiresAt - $this->options->refreshBeforeSeconds;
        $ttl = $retainUntil - $this->clock->now()->getTimestamp();
        if ($ttl > 0) {
            $this->store->put($this->key('session', $credentials), $session, $ttl);
        }

        return $session;
    }

    private function key(string $namespace, #[SensitiveParameter] Credentials $credentials): string
    {
        return hash('sha256', 'oukitel.'.$namespace.'|'.$credentials->region->value.'|'.strtolower($credentials->login));
    }
}
