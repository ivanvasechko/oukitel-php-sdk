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
use IvanVasechko\Oukitel\Exceptions\SessionExpired;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

final class SessionManager
{
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
        $now = $this->clock->now()->getTimestamp();
        if ($session !== null && $session->region === $credentials->region
            && $session->expiresAt > $now + $this->options->refreshBeforeSeconds) {
            return $session;
        }
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
        $ttl = $session->expiresAt - $this->clock->now()->getTimestamp() - $this->options->refreshBeforeSeconds;
        if ($ttl > 0) {
            $this->store->put($key, $session, $ttl);
        }

        return $session;
    }

    private function key(string $namespace, #[SensitiveParameter] Credentials $credentials): string
    {
        return hash('sha256', 'oukitel.'.$namespace.'|'.$credentials->region->value.'|'.strtolower($credentials->login));
    }
}
