<?php

declare(strict_types=1);

namespace IvanVasechko\Oukitel\Config;

use IvanVasechko\Oukitel\Enums\Region;

/** Public app-level protocol values; regional availability is not guaranteed. */
final class RegionPreset
{
    public const PROVIDED_AT = '2026-10-25';

    /** No package-wide regional verification guarantee. */
    public const VERIFIED_AT = null;

    /** Public app protocol constant, not an account credential. */
    private const PUBLIC_SIGNING_CONSTANT = '3aRNUwWahjyANa7WfBK2wCCkxCexB6nXxKJwXxfePvzf';

    public static function for(Region $region): RegionConfiguration
    {
        $baseUrl = match ($region) {
            Region::Eu => 'https://iot-api.acceleronix.io',
            Region::Us => 'https://iot-api.quectelus.com',
            Region::Cn => 'https://iot-api.quectelcn.com',
        };

        return new RegionConfiguration($baseUrl, '277', '3.7.5', 'E.SP.4294967410', self::PUBLIC_SIGNING_CONSTANT);
    }
}
