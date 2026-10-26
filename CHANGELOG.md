# Changelog

All notable changes will be documented here, following Keep a Changelog and
Semantic Versioning.

## [Unreleased]

## [0.3.0] - 2026-10-26

First announced standalone release. PHP 8.2+; no framework required.

### Added

- Repository scaffolding, legal notices, bootstrap tests and release workflows.
- Framework-independent client/account API for login verification, devices,
  normalized telemetry, raw attributes and explicit high-frequency reporting wake.
- Typed contracts, immutable configuration/DTOs, portable PSR integrations,
  reviewed public region presets and deterministic login crypto.
- Bounded login/read transport retries, one-time explicit-expiry reauthentication,
  session caching, login cooldowns and sanitized error handling.
- Recursive redaction, outlet STRUCT aliases, presence/timestamp diagnostics,
  17 independently authored synthetic protocol examples and offline testing helpers.
- Maximum-level static analysis, 90% coverage gate and archive-installed
  Guzzle/Symfony compatibility checks.
- Optional refresh expiry with legacy session/cache compatibility, opt-in refresh
  orchestration, auth-only login fallback and best-effort owned refresh leases.
- Separate experimental control API, strict JSON bool/int validation, P2001
  charging range, explicit unsafe code opt-in and value-free notice logging.
- At-most-once control sending, fresh-timestamp confirmation with injectable
  clock/delay and inferred readable/controllable device capabilities.
- Complete telemetry/error reference, security and upgrade guidance, plus
  offline executable README and Markdown/link quality checks.

### Changed

- Made package documentation self-contained.
- Allowed Pest 5 on compatible PHP versions while retaining PHP 8.2 support.
- Excluded local development dependencies and ignored lockfiles from archives.
- Required maintained development HTTP tooling in lowest-resolution
  checks, without suppressing abandoned-package audits.
- Scoped the secret-scan false-positive exception to the public deterministic
  nonce in the two crypto-vector tests; other values and paths remain scanned.

### Integration notes

- Custom clouds must implement `refresh()`; custom session stores must implement
  refresh lease acquisition and owner-checked release. Constructor additions are
  optional and old cached sessions remain readable.
- Device writes are an optional, separate `ControlCloud` capability. Existing
  read/wake expiry replay remains unchanged; experimental controls never replay.
- Development documentation is excluded from distribution archives.

### Limitations

- Regional availability and device-model compatibility are not guaranteed.
- Refresh remains disabled by default: no usable vendor endpoint was confirmed.
  The bundled transport explicitly refuses refresh without sending a request.
- PSR-16 refresh leases cannot guarantee atomic exclusion.
- The charging-limit policy is not a model-support guarantee. Cloud confirmation
  is not proof of physical output state.
- No scheduling or LAN protocol.
