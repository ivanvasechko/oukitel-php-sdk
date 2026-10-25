<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Parsing;

use IvanVasechko\Oukitel\Exceptions\MalformedResponse;
use JsonException;

final class Values
{
    /** @return array<string, mixed> */
    public static function map(mixed $value, string $field): array
    {
        if (! is_array($value)) {
            throw new MalformedResponse($field);
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                throw new MalformedResponse($field);
            }
            $result[$key] = $item;
        }

        return $result;
    }

    public static function integer(mixed $value, string $field): ?int
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return $value;
        }
        if ((is_float($value) && is_finite($value) && floor($value) === $value)
            || (is_string($value) && preg_match('/^-?\d+$/D', $value) === 1)) {
            $integer = filter_var($value, FILTER_VALIDATE_INT);
            if ($integer !== false) {
                return $integer;
            }
        }

        throw new MalformedResponse($field);
    }

    public static function number(mixed $value, string $field): ?float
    {
        if ($value === null) {
            return null;
        }
        if ((is_int($value) || is_float($value) || (is_string($value) && is_numeric($value)))
            && is_finite((float) $value)) {
            return (float) $value;
        }

        throw new MalformedResponse($field);
    }

    public static function boolean(mixed $value, string $field): ?bool
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }
        if (is_int($value) && in_array($value, [0, 1], true)) {
            return $value === 1;
        }
        if (is_string($value)) {
            return match (strtolower(trim($value))) {
                '1', 'true', 'online', 'on' => true,
                '0', 'false', 'offline', 'off' => false,
                default => throw new MalformedResponse($field),
            };
        }

        throw new MalformedResponse($field);
    }

    /** @return array<array-key, mixed>|null */
    public static function structure(mixed $value, string $field): ?array
    {
        if ($value === null || is_array($value)) {
            return $value;
        }
        if (! is_string($value)) {
            throw new MalformedResponse($field);
        }
        try {
            $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new MalformedResponse($field);
        }
        if (! is_array($decoded)) {
            throw new MalformedResponse($field);
        }

        return $decoded;
    }
}
