<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Contracts;

use IvanVasechko\Oukitel\DTOs\Session;

interface SessionStore
{
    public function get(string $key): ?Session;

    public function put(string $key, Session $session, int $ttl): void;

    public function delete(string $key): void;

    public function isCoolingDown(string $key): bool;

    public function coolDown(string $key, int $ttl): void;
}
