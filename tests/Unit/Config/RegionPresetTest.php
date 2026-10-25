<?php

declare(strict_types=1);

use IvanVasechko\Oukitel\Config\RegionConfiguration;
use IvanVasechko\Oukitel\Config\RegionPreset;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\Exceptions\ConfigurationMissing;

it('resolves every region without claiming live verification', function (Region $region, string $host): void {
    $preset = RegionPreset::for($region);

    expect($preset->baseUrl)->toBe('https://'.$host);
    expect($preset->appId)->toBe('277');
    expect($preset->appVersion)->toBe('3.7.5');
    expect($preset->userDomain)->toBe('E.SP.4294967410');
    expect(RegionPreset::VERIFIED_AT)->toBeNull();
    expect(RegionConfiguration::preset($region)->withAppVersion('3.8.0')->appVersion)->toBe('3.8.0');
    expect(fn () => $preset->withUserDomainSecret(''))->toThrow(ConfigurationMissing::class);
})->with([
    [Region::Eu, 'iot-api.acceleronix.io'],
    [Region::Us, 'iot-api.quectelus.com'],
    [Region::Cn, 'iot-api.quectelcn.com'],
]);
