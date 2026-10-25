<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Parsing;

use JsonException;

final class Redactor
{
    public function value(mixed $value, string $key = ''): mixed
    {
        if ($this->isSensitive($key)) {
            return '[redacted]';
        }
        if (is_string($value) && in_array(substr(ltrim($value), 0, 1), ['{', '['], true)) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return '[redacted]';
            }

            return json_encode($this->value($decoded), JSON_THROW_ON_ERROR);
        }
        if (! is_array($value)) {
            return $value;
        }
        $redacted = [];
        foreach ($value as $childKey => $childValue) {
            $redacted[$childKey] = $this->value($childValue, (string) $childKey);
        }

        return $redacted;
    }

    public function isSensitive(string $key): bool
    {
        return preg_match('/auth|key|token|secret|password|pwd|email|phone|sim|imei|imsi|mac|serial|^sn$|^id$|uid|device.?id|device.?name|locat|address|latitude|longitude|share.?code|binding.?code|perm.?code/i', $key) === 1;
    }
}
