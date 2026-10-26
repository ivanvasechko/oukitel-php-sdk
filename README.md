# Oukitel PHP SDK (unofficial)

**Unofficial. Not affiliated with, endorsed by, or supported by Oukitel,
Wonderfree, Quectel or Acceleronix.**

[![License](https://img.shields.io/badge/license-PolyForm%20Noncommercial-blue)](LICENSE)
[![CI](https://github.com/ivanvasechko/oukitel-php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/ivanvasechko/oukitel-php-sdk/actions/workflows/ci.yml)
[![Coverage gate](https://img.shields.io/badge/coverage%20gate-90%25-brightgreen)](.github/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/ivanvasechko/oukitel-php-sdk)](https://packagist.org/packages/ivanvasechko/oukitel-php-sdk)

Source-available SDK for personal and other permitted noncommercial uses under
[PolyForm Noncommercial 1.0.0](LICENSE). This is not an OSI open-source licence.

## Status

The v0.3.0 API provides cloud reads, session management, offline helpers and
experimental controls. Before 1.0, APIs may change between minor releases;
experimental controls may change in any release. Pin and review upgrades.
PHP 8.2+ and Composer 2 are required. No framework is required.

## Supported devices

The parser includes P2001 / P2001-PLUS-TT-style TSL fields. This is a compatibility
target, not certification or a guarantee for any unit or firmware. Other models
using the Wonderfree app are expected to share parts of the API but are unverified.
EU, US and CN region presets are bundled; automated checks use synthetic data,
not live accounts.

Report compatibility using a minimal synthetic reproduction. Do not attach
account responses, device identifiers, telemetry captures or operator logs.

## Install and quick start

Install with a supported HTTP implementation. Guzzle supplies both the PSR-18
client and PSR-17 factories:

```sh
composer config allow-plugins.php-http/discovery false
composer require ivanvasechko/oukitel-php-sdk guzzlehttp/guzzle
```

Discovery works without enabling Composer plugin auto-installation. A dependency's
plugin policy does not configure your root application, so set it explicitly.

```php
require 'vendor/autoload.php';

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
    $account->wake($devices[0]->id); // Optional explicit reporting request.
}
```

Use your own authorized credentials; the placeholders above cannot authenticate.
`rawAttributes()` returns redacted `TslAttribute` values without numeric or STRUCT
normalization. `telemetry()` returns nullable normalized fields, unknown values
in `extra`, normalized `rawTsl` entries and presence/timestamp `diagnostics`.
Missing, null, false and zero are not interchangeable. A remaining-time value
equal to 5940 becomes null; adjacent values are preserved.

Wake requests high-frequency reporting; it does not promise fresh data or a
polling cadence. Call it only after selecting a device. General controls require a separate,
explicit `controls()` call. Nothing starts polling or changes outputs
automatically.

### Telemetry fields

Scalar fields are nullable: missing data stays `null`, not zero or false.
Units describe normalized output, not electrical measurement guarantees.

| Field | Type | Unit | Source TSL code | Notes |
| --- | --- | --- | --- | --- |
| `isOnline` | `?bool` | — | `deviceData` | `isOnline`, `onlineStatus` or `online` |
| `wifiSignalDbm` | `?int` | dBm | `deviceData.signalStrength` | May be absent |
| `batteryPercent` | `?int` | % | `battery_percentage` | Reported state of charge |
| `remainingDischargeMinutes` | `?int` | min | `remain_time`, `remaining_time` | Exactly 5940 becomes `null` |
| `remainingChargeMinutes` | `?int` | min | `remain_charging_time`, `remaining_charge_time` | Exactly 5940 becomes `null` |
| `totalInputW` | `?int` | W | `total_input_power` | Reported aggregate |
| `totalOutputW` | `?int` | W | `total_output_power` | Reported aggregate |
| `acInputW` | `?int` | W | `ac_input` | Reported input |
| `dcInputW` | `?int` | W | `dc_input` | Reported input |
| `temperatureC` | `?float` | °C | `temp` | Reported temperature |
| `inverterTemperatureC` | `?float` | °C | `inverter_temp` | May be absent |
| `acChargingLimitPercent` | `?int` | % | `ac_charging_limit` | Read value; write policy is separate |
| `acOutputEnabled` | `?bool` | — | `AC_Info`, `ac_data`, `ac_switch` | STRUCT key `1` / `ac_switch`, then scalar fallback |
| `acOutputW` | `?int` | W | `AC_Info`, `ac_data`, `ac_output_power` | STRUCT key `2` / `ac1_output`, then scalar fallback |
| `acOutputV` | `?float` | V | `AC_Info`, `ac_data`, `ac_output_voltage` | STRUCT key `3` / `ac1_output_voltage`, then scalar fallback |
| `dcOutputW` | `?int` | W | `DC_Info`, `dc_data`, `dc_output_power` | STRUCT key `2` / `car1_output`, then scalar fallback |
| `usbOutputW` | `?int` | W | `USB_Info`, `usb_data` | Sum of present `2` / `USB_QC1_output` and `3` / `USB_QC2_output` |
| `typeCOutputW` | `?int` | W | `TypeC_Info`, `typec_data` | Sum of present keys `2`, `5`, `6`, `7` / `Typec1_output` through `Typec4_output` |
| `extra` | `array<string, mixed>` | varies | Unmapped codes | Redacted, type-normalized values |
| `rawTsl` | `list<array>` | varies | Present, non-null attributes | Redacted, normalized code/value/type entries |
| `diagnostics` | `list<array>` | varies | All attributes | Presence, redacted raw value, type and `reportedAt` |

The 5940-minute sentinel represents the 99-hour display cap, not a reliable
duration estimate. STRUCT aliases are normalized without fabricating absent
outlets. There is no UPS-mode inference or background freshness guarantee.

### Experimental controls

**Safety warning:** switching AC off cuts power to connected devices. Switching
AC on energizes mains-voltage outlets. Never experiment on equipment powering
critical loads or somebody else's devices. Select your own station, disconnect
loads, remain present, and be prepared to check and restore settings physically.

Every control API is experimental and may change without a major release.
Support varies by model and firmware; verify compatibility before use.
Request acceptance and cloud telemetry are not proof of physical output state.

```php
$device = $devices[0]->id; // Select and verify your own device first.
$capabilities = $account->capabilities($device); // Read-only inference.
$controls = $account->controls(); // No network request or write.

// These calls change device settings. Do not run them blindly.
$controls->setUsbOutput($device, true);
$controls->setDcOutput($device, false);
$controls->setAcOutput($device, false);
$controls->setAcChargingLimit($device, 50);

$result = $controls->sendAndConfirm(
    $device,
    ['usb_switch' => false],
    attempts: 5,
    delayMilliseconds: 1000,
    timeoutSeconds: 15,
);
// $result->confirmed is bool; $result->observed maps codes to last observed values.
```

Switches use JSON booleans, not integer or string substitutes. The charging-limit
policy permits every integer from **3 through 100**, step 1. The allowed set is
`TslCode::AC_CHARGING_LIMIT_PERCENTAGES`. Invalid values fail before login,
reads or device writes. This policy is not a guarantee of model compatibility.

`send($device, ['ac_switch' => true])` validates the code allow-list and known
values. The five allowed codes are `ac_switch`, `dc_switch`, `usb_switch`,
`ac_charging_limit` and `high_frequency_reporting` (wake value 3 only).
`$controls->unsafe()->send($device, ['custom_code' => 1])` returns a separate
unsafe instance permitting other codes; the original remains safe. Known control
value checks, valid code syntax and JSON-data checks still apply. Unknown codes
can be dangerous and have not been validated.

Controls are sent **at most once**, with no retry on network errors, HTTP 5xx,
vendor 5032 or confirmation failure. A 5032 evicts the session and throws without
reauthenticating and replaying the command. Do not blanket-retry control errors:
an outcome can be ambiguous. Check the station and restore it deliberately.
Every attempted control logs at `notice` with only its code and masked device
suffix; no command values are logged.

Confirmation reads a baseline before sending, then polls up to the specified
attempt count and clock budget. Every target value must match and have a strictly
newer numeric report timestamp. Stale values, missing/opaque timestamps and
missing codes cannot confirm a command. Timeout returns `confirmed: false`;
HTTP, parsing and cache errors still throw. One in-flight read is bounded by the
transport's own deadlines and can outlast the polling budget; late results are
not confirmed. Defaults are 5 attempts, 1000ms delay and 15 seconds. Limits are
1-100 attempts, 0-60000ms delay and 1-300 seconds. Inject `controlDelay` (a closure
receiving milliseconds) and `clock` through either client factory for offline
tests without real sleeping.

`capabilities()` returns `Capabilities` with `readable` and `controllable`
lists of known `TslCode` values and `canRead()` / `canControl()` helpers. Presence
plus the allow-list is an inference, not a model guarantee or a safety interlock;
unknown codes are omitted.

### Sessions, caching and reliability

`create()` accepts named `httpClient`, `requestFactory`, `streamFactory`, `cache`,
`logger`, `clock`, `options`, `regions` and `controlDelay` arguments. They use PSR-18, PSR-17,
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
| Refresh-token use (`useRefreshToken`) | `false` |

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
This replay behavior does **not** apply to experimental controls.

Sessions use an isolated in-memory store per client unless you supply PSR-16
cache. Shared cache keys hash the full namespace, region and lowercased login
into 64 hexadecimal characters. Passwords are never cached. Tokens are cached
only until expiry minus the refresh margin when refresh is disabled. Cache exceptions and false
write/delete results raise `CacheFailed`. Malformed session entries are discarded
with a safe warning; malformed cooldown entries fail closed.

A rejected login raises `AuthenticationFailed`; later attempts during cooldown
raise `LoginCoolingDown`. `verify()` bypasses both cached sessions and cooldown,
without populating or clearing them. Setting the cooldown to zero disables it.

To share sessions across workers, pass the same persistent PSR-16 cache through
`OukitelClient::create(cache: $cache)`. Use the same cache backend and namespace
for workers that should share an account session. Configure separate namespaces
in your cache adapter for isolated applications; the client has no namespace
option. A process-local cache does not share sessions across
workers. Protect the backend with access controls and encryption appropriate to
your deployment; cached tokens grant account access.

EU, US and CN presets contain public app-level constants, not account
credentials. Regional availability and continued validity are not guaranteed. Override any field
immutably, for example:

```php
use IvanVasechko\Oukitel\Config\RegionConfiguration;

$configuration = RegionConfiguration::preset(Region::Eu)->withAppVersion('3.7.5');
$client = OukitelClient::create(regions: ['EU' => $configuration]);
```

### Refresh-token status and custom transports

Login sessions retain optional `refreshExpiresAt`. Old four-argument `Session`
construction and old cached sessions remain supported. The bundled transport
has no verified refresh implementation. Its
`refresh()` throws `RefreshUnavailable` without any HTTP call; enabling
`useRefreshToken` through `create()` fails explicitly at construction.

Applications with an independently verified custom `OukitelCloud` implementation
can opt in using `OukitelClient::fromCloud($cloud, options: new
ClientOptions(useRefreshToken: true))`. The manager reuses fresh access tokens,
refreshes near expiry, and falls back to login only on `AuthenticationFailed`
or `SessionExpired` (vendor 5032). A failed fallback login uses the usual cooldown.
Other refresh errors propagate without a silent login fallback.

With refresh enabled, cache retention extends to the later of access and known
refresh expiry; access freshness is still checked separately. If refresh expiry
is unknown, material is retained only until access expiry, and refresh is tried
only before that expiry. Tokens are never assumed usable indefinitely.

Refresh uses a 30-second owner-checked lease. Its complete `session:refreshing`
namespace and account identity are hashed into a portable 64-character key.
A busy worker reuses another worker's fresh result or throws `RefreshInProgress`
before the requested operation is sent, rather than starting another login.
PSR-16 has no atomic acquire or compare-and-delete: acquisition, ownership checks
and release are **best effort**, not a distributed mutex. Processes can race
between cache calls or outlive a lease. Use an atomic external lock where strict
cross-worker exclusion is required.

Custom transport/store implementers must add `OukitelCloud::refresh()` and
`SessionStore::acquireRefreshLock()` / `releaseRefreshLock()` to their adapters.
An unsupported refresh implementation should throw `RefreshUnavailable`.
Implement the separate experimental `ControlCloud` only if device writes are
supported; read-only clouds otherwise keep working, and attempted controls raise
`ControlsUnavailable`. Never cache credentials or passwords.

### Errors

All SDK exceptions directly extend `OukitelException`, which extends
`RuntimeException`. Catch the base class for application error reporting, but
handle ambiguous control outcomes without automatic replay.

| Exception | When thrown |
| --- | --- |
| `AuthenticationFailed` | Login or refresh authentication is explicitly rejected |
| `SessionExpired` | Vendor code 5032; reads may reauthenticate once, controls never replay |
| `LoginCoolingDown` | Cached failed-login cooldown blocks another login |
| `RefreshUnavailable` | Refresh requested from an unsupported cloud transport |
| `RefreshInProgress` | Another worker holds the best-effort lease without a fresh reusable session |
| `ControlsUnavailable` | Cloud transport does not implement `ControlCloud` |
| `ConfigurationMissing` | Required regional configuration is absent or invalid |
| `TransportConfigurationFailed` | HTTP discovery or automatic deadline configuration fails |
| `TransportFailed` | HTTP/network operation fails |
| `MalformedResponse` | Response JSON, required fields or typed values are invalid |
| `ApiError` | Other non-success vendor response code |
| `CacheFailed` | Cache operation fails or security-sensitive cache state is malformed |

Invalid control arguments and polling limits raise `InvalidArgumentException`
before requests are sent. An unconfigured fake raises `LogicException`.
HTTP failure codes and vendor
body codes are handled separately. Sensitive DTO debug/JSON fields and telemetry
attributes are redacted; transport exception chains and raw responses are not
attached to SDK errors. Do not log credentials, cache payloads, device names or
raw HTTP exceptions, or paste them into issues.

### Security

Credentials remain in the account object in memory; never serialize it into
jobs, logs or shared caches. Sessions hold access and refresh tokens in memory
and, when configured, PSR-16 cache until the documented expiry.
Sensitive DTO `__debugInfo()` and JSON representations redact credentials,
tokens and identifiers. Redaction is not encryption and cannot protect arbitrary
application logs, reflection or manually accessed public properties.
Never log credentials, tokens, cache entries or raw HTTP bodies. Report
vulnerabilities through [SECURITY.md](SECURITY.md), not public issues.

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

`FixtureLoader::SCENARIOS` lists 17 allowlisted, independently authored synthetic
protocol examples. All example values are invented. They illustrate parsing
behavior rather than real device state.
Fixtures use the production parser; traversal names are rejected.

The fake accepts `Telemetry|string` in `withTelemetry()`, explicit unconverted
attributes in `withRawAttributes()`, and a throwable in `failLoginWith()`.
It also supplies deterministic `refresh()`, `refreshCount()` and
`failRefreshWith()`. Experimental controls record attempts in `commands()` and
accept `failControlWith()`; they do not mutate configured telemetry or
manufacture confirmation.
Unconfigured reads throw rather than returning success-shaped defaults. Named
fixtures configure both telemetry and raw attributes; a manually supplied
`Telemetry` requires separate raw-attribute configuration.

## Disclaimer and attribution

The SDK targets a reverse-engineered API with no SLA, which may change or
stop working without notice. Access may be subject to vendor terms or other legal
restrictions and may breach those terms; use is at your own risk.
Use is limited to purposes permitted by PolyForm Noncommercial 1.0.0, including
personal non-commercial use; no commercial licence is granted.
Users must obtain any necessary authorization and comply with
applicable law and terms. No disclaimer guarantees permission or eliminates risk.

All trademarks belong to their respective owners. No ownership of, or permission
to redistribute, vendor APIs or constants is claimed. This licence covers only
rights the author can grant.

Thanks to [VictorCV-DAM/ha-oukitel](https://github.com/VictorCV-DAM/ha-oukitel)
for protocol research. No code from that project is included in this SDK.
Its GPL licence does not automatically settle rights in vendor-owned material.
Bundled presets use public app constants; no third-party
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

Documentation checks require Node.js 22+:

```sh
npm ci
npm run docs:check
composer docs
```

The docs job lints every PHP block and runs the quick start with an injected
HTTP mock, never a live account. Markdown lint and link checks cover repository
guidance. Documentation and development tooling are excluded from SDK archives.

## Laravel

Use the separate [Laravel adapter](https://github.com/ivanvasechko/oukitel-laravel-sdk)
for framework integration. This package remains framework-independent; check the
adapter's version constraints before upgrading.

## Releases

See [CHANGELOG.md](CHANGELOG.md) for release notes,
[upgrading guidance](docs/UPGRADING.md) for compatibility changes and
[CONTRIBUTING.md](CONTRIBUTING.md) for the GitHub UI release procedure.
Packagist indexes pushed tags independently of GitHub Release creation.
Release validation is read-only; GPG signing is optional. Publish only after
the exact release commit passes CI on `main`, and never move published tags.

## Licence

Free to use, modify and redistribute for purposes permitted by
[PolyForm Noncommercial 1.0.0](LICENSE). Commercial use is not granted.
See [NOTICE](NOTICE) for required notices.
