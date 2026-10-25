<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Parsing;

use IvanVasechko\Oukitel\DTOs\Session;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\MalformedResponse;
use SensitiveParameter;

final class CacheValueParser
{
    public function session(#[SensitiveParameter] mixed $value): ?Session
    {
        if ($value === null) {
            return null;
        }
        $data = Values::map($value, 'session cache');
        $region = $data['region'] ?? null;
        $access = $data['access_token'] ?? null;
        $refresh = $data['refresh_token'] ?? null;
        $expiry = $data['expires_at'] ?? null;
        if (! is_string($region) || ($parsedRegion = Region::tryFrom($region)) === null
            || ! is_string($access) || $access === '' || ! is_string($refresh) || $refresh === ''
            || ! is_int($expiry)) {
            throw new MalformedResponse('session cache');
        }

        return new Session($parsedRegion, $access, $refresh, $expiry);
    }

    public function cooldown(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }
        if (! is_bool($value)) {
            throw new MalformedResponse('cooldown cache');
        }

        return $value;
    }
}
