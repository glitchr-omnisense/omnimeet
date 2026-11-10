---
title: Usage policies
order: 45
---

# Usage policies

Each gateway declares what its provider's terms allow - its rate limits, its rights (commercial
use, caching, storage, redistribution, attribution) and what each request needs - as data, in the
schema of [omnisense/contracts](https://github.com/glitchr-omnisense/contracts), the kernel every
omni family builds on. A site tightens it, never looser; the registry enforces it.

## What each gateway declares

| Gateway | File | Limits | Rights | Source, verified |
|---|---|---|---|---|
| `direct` | `policy.json` | none (`[]`): no third party | all allowed: the site's own | no terms but the site's; 2026-11-10 |
| `jitsi` on meet.jit.si | `policy-meet-jit-si.json` | unknown (`null`): 25 active end points a calendar month at no cost, people rather than calls | commercial: contract; its branding may not be removed (attribution: display) | [meet.jit.si Terms of Service](https://jitsi.org/meet-jit-si-terms-of-service/), [jitsi.org/jaas](https://jitsi.org/jaas/); 2026-11-10 |
| `jitsi` on JaaS (8x8.vc) | `policy-jaas.json` | unknown: plans by monthly active users (Developer 25 free, then 300, 1,500, 3,000...) | commercial: contract; key required | [JaaS module](https://8x8.com/cpaas-order-terms/jaas-module), [pricing](https://cpaas.8x8.com/en/pricing/jitsi-as-a-service-pricing/); 2026-11-10 |
| `jitsi` elsewhere | `policy.json` | unknown | unknown: the instance's operator's | 2026-11-10 |

The jitsi gateway picks its file by its `domain` (`Omnimeet\Jitsi\Api::policyFile()`). Each
file's `provenance.notes` quotes the rules it was read from, including those the schema cannot
count: meet.jit.si no longer allows embedding (JaaS is the way to embed: `embed: false` sends
people to the address instead) and asks the first participant to sign in to create a room (since
2023-08-24). `vendor/bin/omnisense-policies` prints every policy installed, as one JSON.

## A site's tightening and what it is

```php
new Omnimeet\Registry([new DirectGatewayFactory($signals), new JitsiGatewayFactory()], [
    'direct' => [
        'factory' => 'direct',
        'options' => ['turn_secret' => '...', 'turn_urls' => ['turn:turn.example.org:3478']],
        // what the site's relay takes
        'policy' => ['rateLimits' => [['max' => 200, 'window' => 'day', 'operations' => ['open']]]],
    ],
    'group' => [
        'factory' => 'jitsi',
        'options' => ['domain' => '8x8.vc', /* app_id, private_key, key_id */],
        'usage' => ['commercial' => true, 'contract' => true],
    ],
], $limiter);
```

`policy` and `usage` sit beside `options` or in them; in Symfony, beside `options` under
`omnimeet.gateways.<name>`, with `omnimeet.limiter` naming a shared `RateLimiterInterface`
service. A looser limit, or a right made looser (`unknown` to `allowed`), is an
`InvalidPolicyException`; a commercial site without a contract on meet.jit.si or JaaS is a
`UsageNotPermitted` as soon as the guarded gateway is asked for.

## The guard

`Registry::guarded($name)` gives the gateway behind its policy: every request but a callback's
(`open`, `join`, `close`, `fetch`: `Gateway::OPERATIONS`) is checked against the limits that count
its operation before its action runs - `UsageLimitReachedException` (an `OmnimeetException`, with
`retryAfter`), nothing done - and counted after, failed or not. `notify()` is never refused: a
callback is the provider's own word. `get()` gives the gateway unguarded, as before.
`Registry::policy($name)` and `policies()` give the policies as the site runs them (a credits page
can list them); `capabilities()->policy()` the gateway's as declared.

## Nothing is sent twice

No gateway retries anything, and neither does the guard: a request refused, failed or answered by
an error goes back to the caller as it is. Opening a room creates nothing at a provider today - the
direct gateway's room is the meeting's key, a Jitsi room exists once its first participant comes in
- so opening the same meeting twice is the same room. A gateway that would create a room over HTTP
gets the provider's Backoff in its Config (`Config::backoff()`): a 429 and its Retry-After throw
at once, the next requests held until the wait is over, and the request is never replayed
(`Tests/PolicyTest.php` proves an open answered 429 or 503 ran once). Neither Jitsi nor JaaS
offers an idempotency key for a room (there is no call to create one); JaaS's webhooks carry an
`idempotencyKey`, which `notify()` gives back as `Notification::$id`.
