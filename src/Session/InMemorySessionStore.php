<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Session;

use InvalidArgumentException;
use IvanVasechko\Oukitel\Clock\SystemClock;
use IvanVasechko\Oukitel\Contracts\SessionStore;
use IvanVasechko\Oukitel\DTOs\Session;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

final class InMemorySessionStore implements SessionStore
{
    /** @var array<string, array{session: Session, expiresAt: int}> */
    private array $sessions = [];

    /** @var array<string, int> */
    private array $cooldowns = [];

    /** @var array<string, array{owner: string, expiresAt: int}> */
    private array $refreshLocks = [];

    public function __construct(private readonly ClockInterface $clock = new SystemClock) {}

    public function get(string $key): ?Session
    {
        if (! isset($this->sessions[$key]) || $this->sessions[$key]['expiresAt'] <= $this->clock->now()->getTimestamp()) {
            unset($this->sessions[$key]);

            return null;
        }

        return $this->sessions[$key]['session'];
    }

    public function put(string $key, #[SensitiveParameter] Session $session, int $ttl): void
    {
        if ($ttl <= 0) {
            $this->delete($key);

            return;
        }
        $this->sessions[$key] = ['session' => $session, 'expiresAt' => $this->clock->now()->getTimestamp() + $ttl];
    }

    public function delete(string $key): void
    {
        unset($this->sessions[$key], $this->cooldowns[$key]);
    }

    public function isCoolingDown(string $key): bool
    {
        if (! isset($this->cooldowns[$key]) || $this->cooldowns[$key] <= $this->clock->now()->getTimestamp()) {
            unset($this->cooldowns[$key]);

            return false;
        }

        return true;
    }

    public function coolDown(string $key, int $ttl): void
    {
        if ($ttl <= 0) {
            unset($this->cooldowns[$key]);

            return;
        }
        $this->cooldowns[$key] = $this->clock->now()->getTimestamp() + $ttl;
    }

    public function acquireRefreshLock(string $key, int $ttl): ?string
    {
        if ($ttl < 1) {
            throw new InvalidArgumentException('Refresh lock TTL must be positive.');
        }
        $now = $this->clock->now()->getTimestamp();
        if (isset($this->refreshLocks[$key]) && $this->refreshLocks[$key]['expiresAt'] > $now) {
            return null;
        }
        $owner = bin2hex(random_bytes(16));
        $this->refreshLocks[$key] = ['owner' => $owner, 'expiresAt' => $now + $ttl];

        return $owner;
    }

    public function releaseRefreshLock(string $key, string $owner): void
    {
        if (($this->refreshLocks[$key]['owner'] ?? null) === $owner) {
            unset($this->refreshLocks[$key]);
        }
    }
}
