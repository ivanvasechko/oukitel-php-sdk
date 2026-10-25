# Changelog

All notable changes will be documented here, following Keep a Changelog and
Semantic Versioning.

## [Unreleased]

Intended next release: **0.1.0**. No release tag or publication is created here.

### Added

- Repository scaffolding, legal notices, bootstrap tests and release workflows.
- Framework-independent client/account API for login verification, devices,
  normalized telemetry, raw attributes and explicit high-frequency reporting wake.
- Typed contracts, immutable configuration/DTOs, portable PSR integrations,
  reviewed public region presets and deterministic login crypto.
- Bounded login/read transport retries, one-time explicit-expiry reauthentication,
  session caching, login cooldowns and sanitized error handling.
- Recursive redaction, outlet STRUCT aliases, presence/timestamp diagnostics,
  17 reconstructed protocol scenarios and shipped offline testing helpers.
- Maximum-level static analysis, 90% coverage gate and archive-installed
  Guzzle/Symfony compatibility checks.

### Changed

- Made package documentation self-contained.
- Allowed Pest 5 on compatible PHP versions while retaining PHP 8.2 support.
- Excluded local development dependencies and ignored lockfiles from archives.

### Limitations

- Region constants are public reviewed inputs, not live-verified presets.
- No refresh-token flow, general device controls, tagging or publication.
