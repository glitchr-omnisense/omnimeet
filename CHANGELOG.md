# Changelog

## [Unreleased]

### Added

- omnisense/omnimeet on [omnisense/contracts](https://github.com/glitchr-omnisense/contracts), the
  kernel of the omni families, with usage policies; the public API unchanged. `Config`,
  `GatewayFactory` (`populateConfig()` as before) and `Registry` are thin subclasses of the
  kernel's (prefix `omnimeet`); `GatewayInterface` extends its `ProviderInterface`,
  `GatewayFactoryInterface` its `ProviderFactoryInterface`, `Model\Capabilities` implements its
  `CapabilitiesInterface` (`contracts()`: the operations the gateway answers; `policy()`);
  `OmnimeetException` extends its `ExceptionInterface`, `InvalidConfigException` its
  `InvalidConfigException` (an `\InvalidArgumentException`, a `\LogicException` as before).
- A factory declares its policy (`omnimeet.policy`: its `policy.json`, a `UsagePolicy` or a closure
  of the Config); `Registry::policy()`, `policies()`, `guard()` and `guarded()` (the gateway
  behind its guard: a request over a limit refused before its action runs,
  `Exception\UsageLimitReachedException`; a callback never); `policy` and `usage` beside a
  gateway's options; a shared `RateLimiterInterface` as the registry's third argument; the bundle
  takes `policy`, `usage` and `limiter`. Nothing retries a request: an open is never replayed.
- `Testing\BareTestCase` (the kernel's bare check, the core added); `Tests/LoadAllTest`.
- PHPStan at level max on each package (`phpstan.neon`, the errors there when it came in in
  `phpstan-baseline.neon`); the harness runs it (`phpstan`), installs omnisense/contracts from its
  checkout (`OMNISENSE_CONTRACTS`) or GitHub and runs its tests, names its entrypoint and uses
  Docker's default bridge.

### Changed

- PHPStan at level max with an empty baseline: types only (`Meeting::with()`'s `$present` a
  `list<Participant>`, the tests' reads narrowed), no behaviour changed.

### Fixed

- The models' constructors throw an `InvalidModelException` (an `OmnimeetException`, and an
  `\InvalidArgumentException` as before) for a meeting without a key or ending before it starts, a
  participant without an identifier, an access without its script or its address: a
  `catch (OmnimeetException)` missed the plain `\InvalidArgumentException`.
- `Gateway::open()`, `join()`, `close()`, `fetch()` and `notify()` throw a `ProviderException`
  ("Open was answered with null, not Omnimeet\Model\Meeting.") when an action answers with nothing
  or with something else than the request asks for, where a `TypeError` came out; the requests'
  getters (`getMeeting()`, `getAccess()`, `getNotification()`) give null for an answer of another
  type instead of a `TypeError`.
