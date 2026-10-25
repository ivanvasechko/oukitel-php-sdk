<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Tests\Support;

use Closure;
use DateInterval;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

final class RecordingCache implements CacheInterface
{
    /** @var array<string, mixed> */
    public array $values = [];

    /** @var array<string, DateInterval|int|null> */
    public array $ttls = [];

    public ?string $failure = null;

    public bool $throw = false;

    public ?Closure $afterSet = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $this->outcome('get');

        return $this->values[$key] ?? $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        if (! $this->outcome('set')) {
            return false;
        }
        $this->values[$key] = $value;
        $this->ttls[$key] = $ttl;
        if ($this->afterSet !== null) {
            ($this->afterSet)($key, $value);
        }

        return true;
    }

    public function delete(string $key): bool
    {
        if (! $this->outcome('delete')) {
            return false;
        }
        unset($this->values[$key], $this->ttls[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->values = $this->ttls = [];

        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (! $this->set($key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            if (! $this->delete($key)) {
                return false;
            }
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    private function outcome(string $operation): bool
    {
        if ($this->failure === $operation && $this->throw) {
            throw new RuntimeException('private backend details');
        }

        return $this->failure !== $operation;
    }
}
