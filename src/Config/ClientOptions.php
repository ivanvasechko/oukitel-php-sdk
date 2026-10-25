<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Config;

use InvalidArgumentException;

final readonly class ClientOptions
{
    public function __construct(
        public int $timeoutSeconds = 10,
        public int $connectTimeoutSeconds = 3,
        public int $retryTimes = 3,
        public int $retryDelayMilliseconds = 250,
        public string $userAgent = 'okhttp/4.9.3',
        public int $refreshBeforeSeconds = 120,
        public int $loginFailureCooldownMinutes = 5,
    ) {
        if ($timeoutSeconds < 1 || $connectTimeoutSeconds < 1 || $retryTimes < 1
            || $retryDelayMilliseconds < 0 || $refreshBeforeSeconds < 0
            || $loginFailureCooldownMinutes < 0 || trim($userAgent) === ''
            || preg_match('/[\r\n]/', $userAgent) === 1) {
            throw new InvalidArgumentException('Invalid Oukitel client options.');
        }
    }
}
