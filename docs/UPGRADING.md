# Upgrading

## v0.3.0

This is the first announced standalone SDK release. Earlier scaffolding or
development revisions are not a stable API. Review the
[README](../README.md) and pin a compatible version before upgrading.
PHP 8.2+ and a PSR-18 HTTP client with PSR-17 factories are required.

### Custom adapters

- Implement `OukitelCloud::refresh(Session): Session`. Unsupported transports
  should throw `RefreshUnavailable` without making speculative HTTP requests.
- Implement `SessionStore::acquireRefreshLock()` and `releaseRefreshLock()` with
  the owner semantics in the contract. PSR-16 leases are best effort, not atomic.
- Implement the separate `ControlCloud` contract only when writes are supported.
  Read-only cloud implementations need not implement it.

### Backward-compatible session additions

The fifth `Session` constructor argument, `refreshExpiresAt`, is optional.
Four-argument construction and cached sessions lacking refresh expiry remain
supported. New client factory arguments are optional.

### Behavioral boundaries

- Refresh is disabled by default. The bundled HTTP transport does not implement
  refresh; enable it only for an independently implemented custom transport.
- Read/wake vendor-expiry replay remains supported once. Experimental controls
  never replay, including after expiry, timeout or confirmation failure.
- Confirmation requires matching values and strictly newer numeric timestamps.
  `confirmed: false` is not proof that a command failed physically.
- Fixture values are synthetic examples, not observations or compatibility
  evidence. Do not depend on example numeric readings.

Before 1.0, review every minor release for API changes. Experimental controls
may change in any release. Keep application lockfiles and run offline tests
before rollout; do not use live device writes as an automated upgrade check.
