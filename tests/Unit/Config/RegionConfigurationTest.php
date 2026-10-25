<?php

declare(strict_types=1);

use IvanVasechko\Oukitel\Config\RegionConfiguration;
use IvanVasechko\Oukitel\Exceptions\ConfigurationMissing;

it('parses configuration and provides immutable overrides for every field', function (): void {
    $configuration = RegionConfiguration::fromArray([
        'base_url' => 'https://example.test/', 'app_id' => 'test-app', 'app_version' => '1.0',
        'user_domain' => 'test-domain', 'user_domain_secret' => 'test-public-constant',
    ]);

    expect($configuration->baseUrl)->toBe('https://example.test');
    expect($configuration->withBaseUrl('https://other.test/')->baseUrl)->toBe('https://other.test');
    expect($configuration->withAppId('other')->appId)->toBe('other');
    expect($configuration->withAppVersion('2.0')->appVersion)->toBe('2.0');
    expect($configuration->withUserDomain('other')->userDomain)->toBe('other');
    expect($configuration->withUserDomainSecret('other')->userDomainSecret)->toBe('other');
    expect($configuration->appVersion)->toBe('1.0');
});

it('rejects missing malformed or explicitly emptied configuration', function (mixed $value): void {
    expect(fn () => RegionConfiguration::fromArray($value))->toThrow(ConfigurationMissing::class);
})->with([[null], [[]], [['base_url' => 123]], [[
    'base_url' => 'https://example.test', 'app_id' => '', 'app_version' => '1',
    'user_domain' => 'test', 'user_domain_secret' => 'test',
]]]);

it('rejects unsafe base URLs and header values', function (string $url): void {
    expect(fn () => new RegionConfiguration($url, 'app', '1', 'domain', 'constant'))
        ->toThrow(ConfigurationMissing::class);
})->with(['not-a-url', 'ftp://example.test', 'https://user:password@example.test', 'https://example.test?secret=value', 'https://example.test#fragment']);
