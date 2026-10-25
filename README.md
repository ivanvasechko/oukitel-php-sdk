# Oukitel PHP SDK (unofficial)

**Unofficial. Not affiliated with, endorsed by, or supported by Oukitel,
Wonderfree, Quectel or Acceleronix.**

[![CI](https://github.com/ivanvasechko/oukitel-php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/ivanvasechko/oukitel-php-sdk/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-PolyForm%20Noncommercial-blue)](LICENSE)

Source-available SDK for personal and other permitted noncommercial uses under
[PolyForm Noncommercial 1.0.0](LICENSE). This is not an OSI open-source licence.

## Status

This branch prepares the standalone core API for **0.1.0**. It does not tag or
publish a release; the examples below target the unreleased core API.
PHP 8.2+ and Composer 2 are required. No framework is required.

## Install and quick start

When a release containing this API is available, install it with a supported
HTTP implementation. Guzzle supplies both the PSR-18 client and PSR-17 factories:

```sh
composer config allow-plugins.php-http/discovery false
composer require ivanvasechko/oukitel-php-sdk guzzlehttp/guzzle
```

Discovery works without enabling Composer plugin auto-installation. A dependency's
plugin policy does not configure your root application, so set it explicitly.

```php
use IvanVasechko\Oukitel\DTOs\Credentials;
use IvanVasechko\Oukitel\Enums\Region;
use IvanVasechko\Oukitel\OukitelClient;

$client = OukitelClient::create();
$account = $client->account(new Credentials(
    Region::Eu,
    'user@example.test',
    'replace-with-your-password',
));

$account->verify(); // Always checks credentials with a fresh login.
$devices = $account->devices();
if ($devices !== []) {
    $telemetry = $account->telemetry($devices[0]->id);
    $attributes = $account->rawAttributes($devices[0]->id);
}
```

Use your own authorized credentials; the placeholders above cannot authenticate.
`rawAttributes()` returns redacted `TslAttribute` values without numeric or STRUCT
normalization. `telemetry()` returns nullable normalized fields, unknown values
in `extra`, normalized `rawTsl` entries and presence/timestamp `diagnostics`.
Missing, null, false and zero are not interchangeable. A remaining-time value
equal to 5940 becomes null; adjacent values are preserved.

The only device write is an explicit high-frequency reporting wake request:

```php
$account->wake($devices[0]->id);
```

Only call it after selecting a device. No automatic polling, refresh-token flow
or general device controls are included.

### Configuration and reliability

`create()` accepts named `httpClient`, `requestFactory`, `streamFactory`, `cache`,
`logger`, `clock`, `options` and `regions` arguments. They use PSR-18, PSR-17,
PSR-16, PSR-3 and PSR-20 respectively. Supplied clients/factories take precedence
and are never replaced or reconfigured. Configure supplied clients' own timeouts.

`ClientOptions` defaults:

| Setting | Default |
| --- | --- |
| Connect / total timeout | 3 / 10 seconds per attempt |
| Retry attempts / delay | 3 total attempts / 250 milliseconds |
| User agent | `okhttp/4.9.3` |
| Session refresh margin | 120 seconds |
| Login-failure cooldown | 5 minutes |

Automatically discovered Guzzle clients use exact connect/total deadlines.
Symfony HttpClient 8.1+ uses separate connect/total deadlines; older Symfony and
other unsupported discovered clients require explicit injection. Discovery may
select Symfony when multiple implementations are installed. Failure to discover
factories or configure deadlines raises `TransportConfigurationFailed`, not a
client with silently different timeout behavior.

Login/read requests retry only network failures and HTTP 5xx. HTTP 4xx, malformed
responses, vendor failures and invalid requests are not retried. Wake is never
transport-retried. An explicit vendor 5032 invalidates the session and permits
one fresh login and replay, including wake; a second 5032 evicts and throws.

Sessions use an isolated in-memory store per client unless you supply PSR-16
cache. Shared cache keys hash the full namespace, region and lowercased login
into 64 hexadecimal characters. Passwords are never cached. Tokens are cached
only until expiry minus the refresh margin. Cache exceptions and false
write/delete results raise `CacheFailed`. Malformed session entries are discarded
with a safe warning; malformed cooldown entries fail closed.

A rejected login raises `AuthenticationFailed`; later attempts during cooldown
raise `LoginCoolingDown`. `verify()` bypasses both cached sessions and cooldown,
without populating or clearing them. Setting the cooldown to zero disables it.

EU, US and CN presets contain public app-level constants provided on 2026-10-25.
**All three remain unverified against a live account.** These are not account
credentials, and their continued validity is not guaranteed. Override any field
immutably, for example:

```php
use IvanVasechko\Oukitel\Config\RegionConfiguration;

$configuration = RegionConfiguration::preset(Region::Eu)->withAppVersion('3.7.5');
$client = OukitelClient::create(regions: ['EU' => $configuration]);
```

All SDK exceptions extend `OukitelException`. HTTP failure codes and vendor
body codes are handled separately. Sensitive DTO debug/JSON fields and telemetry
attributes are redacted; transport exception chains and raw responses are not
attached to SDK errors. Do not log credentials, cache payloads, device names or
raw HTTP exceptions, or paste them into issues.

### Offline testing

Testing helpers are included in the distribution and require no test framework:

```php
use IvanVasechko\Oukitel\DTOs\DeviceId;
use IvanVasechko\Oukitel\Testing\FakeOukitelCloud;
use IvanVasechko\Oukitel\Testing\FixtureLoader;

$device = new DeviceId('example-device', 'example-product');
$fake = (new FakeOukitelCloud)->withDevices([])->withTelemetry($device, 'online');
$client = OukitelClient::fromCloud($fake);
$account = $client->account(new Credentials(Region::Eu, 'user@example.test', 'fake'));
$telemetry = $account->telemetry($device);
$account->wake($device);
$wakes = $fake->wakes();
$snapshot = FixtureLoader::telemetry('offline');
```

`FixtureLoader::SCENARIOS` lists 17 allowlisted synthetic/reconstructed protocol
scenarios. They preserve available sanitized readings, not captured HTTP dumps,
complete observation histories or guaranteed live-device state. Missing metadata
is not fabricated. Fixtures use the production parser; traversal names are rejected.

The fake accepts `Telemetry|string` in `withTelemetry()`, explicit unconverted
attributes in `withRawAttributes()`, and a throwable in `failLoginWith()`.
Unconfigured reads throw rather than returning success-shaped defaults. Named
fixtures configure both telemetry and raw attributes; a manually supplied
`Telemetry` requires separate raw-attribute configuration.

## Disclaimer and attribution

The SDK targets a reverse-engineered API with no SLA, which may change or
stop working without notice. Access may be subject to vendor terms or other legal
restrictions. Users must obtain any necessary authorization and comply with
applicable law and terms. No disclaimer guarantees permission or eliminates risk.

All trademarks belong to their respective owners. No ownership of, or permission
to redistribute, vendor APIs or constants is claimed. This licence covers only
rights the author can grant.

Thanks to [VictorCV-DAM/ha-oukitel](https://github.com/VictorCV-DAM/ha-oukitel)
for protocol research. No code from that project is included in this SDK.
Its GPL licence does not automatically settle rights in vendor-owned material.
Bundled presets use operator-reviewed public app constants; no third-party
protocol implementation code is included.

## Rights-holder concerns

Rights holders can contact the maintainer through a
[private report](https://github.com/ivanvasechko/oukitel-php-sdk/security/advisories/new).
Contact me and I will promptly change or remove disputed material, including
bundled constants.

## Development

Run the existing quality scripts:

```sh
composer update
composer validate --strict
composer test
composer phpstan
composer pint -- --test
composer audit
```

This library intentionally does not commit `composer.lock`. Consumers use their
application lockfile; CI resolves both the lowest and latest supported dependency
versions. Run `composer update` to refresh the local development lockfile.
Development-only HTTP tooling has maintained version floors so lowest-resolution
checks do not install the abandoned message factory.

PHPStan checks production source at maximum level. CI tests PHP 8.2-8.5 with both
lowest and latest compatible dependencies, enforces 90% coverage, audits resolved
dependencies and scans secrets. Isolated archive-installed consumers check Guzzle
on PHP 8.2/8.5 and Symfony on PHP 8.5 without root development dependencies.
All automated tests are offline; passing CI does not verify live region presets.

## Releases

See [CONTRIBUTING.md](CONTRIBUTING.md). There are no placeholder releases.
Packagist registration exists; publishing a tagged core release is a separate
maintainer action.

## Licence

Free to use, modify and redistribute for purposes permitted by
[PolyForm Noncommercial 1.0.0](LICENSE). Commercial use is not granted.
See [NOTICE](NOTICE) for required notices.
