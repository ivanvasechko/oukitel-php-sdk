# Oukitel PHP SDK (unofficial)

**Unofficial. Not affiliated with, endorsed by, or supported by Oukitel,
Wonderfree, Quectel or Acceleronix.**

[![CI](https://github.com/ivanvasechko/oukitel-php-sdk/actions/workflows/ci.yml/badge.svg)](https://github.com/ivanvasechko/oukitel-php-sdk/actions/workflows/ci.yml)
[![License](https://img.shields.io/badge/license-PolyForm%20Noncommercial-blue)](LICENSE)

Source-available SDK for personal and other permitted noncommercial uses under
[PolyForm Noncommercial 1.0.0](LICENSE). This is not an OSI open-source licence.

## Status

Repository bootstrap only. No protocol implementation, vendor constants, released
version, or Packagist installation is available yet. Do not use in production.
Protocol implementation follows in power-platform task 15-02.

## Install and quick start

Once released, installation will be:

```sh
composer require ivanvasechko/oukitel-php-sdk
```

No working SDK quick start exists at this stage. Do not paste account credentials
into issues, logs or example files.

## Disclaimer and attribution

The intended SDK targets a reverse-engineered API with no SLA, which may change or
stop working without notice. Access may be subject to vendor terms or other legal
restrictions. Users must obtain any necessary authorization and comply with
applicable law and terms. No disclaimer guarantees permission or eliminates risk.

All trademarks belong to their respective owners. No ownership of, or permission
to redistribute, vendor APIs or constants is claimed. This licence covers only
rights the author can grant.

Thanks to [VictorCV-DAM/ha-oukitel](https://github.com/VictorCV-DAM/ha-oukitel)
for protocol research. No code from that project is included in this bootstrap.
Its GPL licence does not automatically settle rights in vendor-owned material.
Region presets are planned for task 15-02 under EPIC-15's explicit decision.
This bootstrap contains no vendor constants or third-party protocol code.

## Rights-holder concerns

Rights holders can contact the maintainer through a
[private report](https://github.com/ivanvasechko/oukitel-php-sdk/security/advisories/new).
Contact me and I will promptly change or remove disputed material, including
bundled constants in future releases.

## Development

Use PHP 8.2+ and Composer:

```sh
composer update
composer validate --strict
composer test
composer phpstan
composer pint -- --test
composer audit
```

PHPStan currently checks the bootstrap harness only. CI runs bootstrap tests without
a coverage gate until production PHP source exists; then it enforces 90% coverage.
Do not interpret bootstrap test results as SDK functionality or production coverage.

## Releases

See [CONTRIBUTING.md](CONTRIBUTING.md). There are no placeholder releases.
Packagist registration requires the maintainer's Packagist account.

## Licence

Free to use, modify and redistribute for purposes permitted by
[PolyForm Noncommercial 1.0.0](LICENSE). Commercial use is not granted.
See [NOTICE](NOTICE) for required notices.
