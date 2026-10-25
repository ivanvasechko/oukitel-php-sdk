<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Session;

use Closure;
use IvanVasechko\Oukitel\Contracts\SessionStore;
use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\Exceptions\CacheFailed;
use IvanVasechko\Oukitel\Exceptions\MalformedResponse;
use IvanVasechko\Oukitel\Parsing\CacheValueParser;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use SensitiveParameter;

final class Psr16SessionStore implements SessionStore
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger = new NullLogger,
        private readonly CacheValueParser $parser = new CacheValueParser,
    ) {}

    public function get(string $key): ?Session
    {
        $value = $this->access(fn (): mixed => $this->cache->get($key));
        try {
            return $this->parser->session($value);
        } catch (MalformedResponse) {
            $this->logger->warning('Discarding malformed Oukitel session cache entry.');
            $this->delete($key);

            return null;
        }
    }

    public function put(string $key, #[SensitiveParameter] Session $session, int $ttl): void
    {
        if (! $this->access(fn (): bool => $this->cache->set($key, [
            'region' => $session->region->value,
            'access_token' => $session->accessToken,
            'refresh_token' => $session->refreshToken,
            'expires_at' => $session->expiresAt,
        ], $ttl))) {
            throw new CacheFailed;
        }
    }

    public function delete(string $key): void
    {
        if (! $this->access(fn (): bool => $this->cache->delete($key))) {
            throw new CacheFailed;
        }
    }

    public function isCoolingDown(string $key): bool
    {
        $value = $this->access(fn (): mixed => $this->cache->get($key));
        try {
            return $this->parser->cooldown($value);
        } catch (MalformedResponse) {
            throw new CacheFailed;
        }
    }

    public function coolDown(string $key, int $ttl): void
    {
        if (! $this->access(fn (): bool => $this->cache->set($key, true, $ttl))) {
            throw new CacheFailed;
        }
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $operation
     * @return T
     */
    private function access(Closure $operation): mixed
    {
        try {
            return $operation();
        } catch (CacheException|RuntimeException) {
            throw new CacheFailed;
        }
    }
}
