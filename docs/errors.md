---
title: Errors
order: 50
---

# Errors and exceptions

```
Omnimeet\Exception\OmnimeetException (an interface, \Throwable)
├── InvalidConfigException         extends \LogicException             a gateway misconfigured, a meeting not opened
├── NotSupportedException          extends \LogicException             the gateway does not do that
├── ProviderException              extends \RuntimeException           the provider refused, or could not be reached
│   └── InvalidNotificationException                                   a callback that is not the provider's
└── Omnimeet\Direct\Exception\InvalidSignalException  extends \InvalidArgumentException   (omnimeet/direct) not a message of the handshake
```

`ProviderException` carries `provider` (the gateway's name) and `providerCode` (?string); its message
starts with `[<provider>] `.

## `InvalidConfigException`

| Message | Thrown by |
|---|---|
| `The "<factory>" gateway needs: <options>.` | `GatewayFactory::create()`: a required option missing or empty |
| `The "<factory>" factory does not say what its meetings are (omnimeet.capabilities).` | `GatewayFactory::create()`: a factory without capabilities |
| `No "x" gateway; configured: ...`, `No "y" factory for the "x" gateway; installed: ...` | `Registry::get()`, `Registry::create()` |
| `The meeting "<key>" is not opened on a gateway yet: open() gives its reference.` | `Meeting::reference()`, so `join()`, `close()`, `fetch()` of a meeting without a reference |
| `<Action> needs a <Api>, got <class>.` | `ApiAwareTrait::setApi()` |
| `TURN is not configured: ...` | direct: `TurnCredentials::issue()` without a secret or an address (`join()` asks only when configured) |
| `The jitsi gateway's domain is a host name ...`, `Jitsi as a Service needs app_id ..., private_key and key_id ...`, `A token signed with a private key names its public key: ... key_id.`, `A token signed with app_secret names its application: ... app_id.` | jitsi: when the gateway is built |
| `Signing a token with a private key (RS256) needs PHP's openssl extension.`, `The private key of the jitsi gateway cannot be read: a PEM, or the path of its file.`, `Tokens are signed with HS256 or RS256, not "x".` | jitsi: `Jwt::sign()`, on `join()` |

## `NotSupportedException`

`The "jitsi" gateway does not support Close.` - from `Gateway::execute()` for a request no action
supports; `supports()` says it beforehand:

| | Open | Join | Close | Fetch | Notify |
|---|---|---|---|---|---|
| direct | yes | yes | yes | yes | not supported |
| jitsi | yes | yes | not supported | not supported | only with `webhook_secret` |

## `ProviderException` and `InvalidNotificationException`

`ProviderException` is thrown by `Gateway::execute()` for an action that left its request
unanswered. Neither gateway calls a provider over the network today, so no other comes from them.

`InvalidNotificationException` (jitsi's `notify()`): no `webhook_secret`; no `X-Jaas-Signature`
(`t=...,v1=...`); a signature that does not hold; a timestamp further than `webhook_tolerance`
from now (`The callback is too old.`); a signed body that is not a JaaS event (no `eventType`).
Answer such a callback with a 4xx.

## `InvalidSignalException`

omnimeet/direct's `Signaling::send()`: no room or no sender, a type other than `hello`, `offer`,
`answer`, `candidate`, `bye`, a payload that is not JSON or is larger than 64 kB. Answer the page
with a 400.

## Not an `OmnimeetException`

> [!WARNING]
> Known defect: `OmnimeetException` says it is "every exception Omnimeet throws", but the models' constructors throw a plain `\InvalidArgumentException`: `Meeting` without a key or ending before it starts, `Participant` without an identifier, an `Access` of kind `ENGINE` without its script or a `FRAME`/`LINK` without an address.
