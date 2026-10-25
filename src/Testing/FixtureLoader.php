<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Testing;

use InvalidArgumentException;
use IvanVasechko\Oukitel\DTOs\Telemetry;
use IvanVasechko\Oukitel\DTOs\TslAttribute;
use IvanVasechko\Oukitel\Parsing\ResponseParser;
use RuntimeException;

/** Bundled examples contain invented data, not service captures. */
final class FixtureLoader
{
    public const SCENARIOS = [
        'ac-output-off', 'charge-limit', 'charging-load-40w', 'charging-load-70w', 'charging',
        'fully-off', 'idle', 'no-wake-repeat', 'offline', 'on-battery', 'online', 'output-recovery',
        'passthrough', 'periodic-wake-repeat', 'periodic-wake', 'post-wake', 'stale-repeat',
    ];

    public static function telemetry(string $name): Telemetry
    {
        return (new ResponseParser)->telemetry(self::payload($name));
    }

    /** @return list<TslAttribute> */
    public static function rawAttributes(string $name): array
    {
        return (new ResponseParser)->rawAttributes(self::payload($name));
    }

    private static function payload(string $name): mixed
    {
        if (! in_array($name, self::SCENARIOS, true)) {
            throw new InvalidArgumentException('Unknown Oukitel fixture name.');
        }
        $contents = @file_get_contents(__DIR__.'/Fixtures/'.$name.'.json');
        if ($contents === false) {
            throw new RuntimeException('Unable to read bundled Oukitel fixture.');
        }

        return (new ResponseParser)->decode($contents);
    }
}
