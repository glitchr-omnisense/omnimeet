---
title: Configuration reference
order: 40
---

# Configuration reference

## The registry

```php
new Omnimeet\Registry(iterable $factories, array $config);
// $config: ['<name>' => ['factory' => '<factory name>', 'options' => [...]], ...]
```

Each gateway: `factory` (string, required: a factory's `getName()`) and `options` (array, `[]` by
default, handed to the factory's `create()` on first use; the gateway is then kept). Nothing is
checked before a gateway is asked for. The Symfony bundle's tree (`omnimeet.gateways.<name>.factory`,
`.options`) is the same ([Symfony](symfony.md#the-configuration-tree)).

## `direct`

`new DirectGatewayFactory(?SignalStoreInterface $signals = null)`: the store the handshake waits in
(`InMemorySignalStore` when none is given: one PHP process only).

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `turn_secret` | ?string | `null` | no | coturn's `static-auth-secret`: the participants' temporary credentials are signed with it |
| `turn_urls` | list or comma-separated string | `[]` | no | the relay's addresses (`turn:host:3478?transport=udp`...); with `turn_secret`, the relay is given to each participant |
| `stun_urls` | list or comma-separated string | `[]` | no | STUN servers; none by default, so no third party is asked where the browser is |
| `turn_ttl` | int | `3600` | no | seconds the credentials last (at least 60) |

No option is required: without a relay the two browsers connect directly, or not at all.

## `jitsi`

`new JitsiGatewayFactory()`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `domain` | string | - | yes | the instance's host (`meet.example.org`, `8x8.vc`); a scheme and a trailing slash are dropped, anything else than a host (and port) is an `InvalidConfigException` |
| `app_id` | ?string | `null` | with `app_secret`, and for JaaS | Prosody's `app_id` (the token's `iss`); JaaS: the AppID (`vpaas-magic-cookie-...`) |
| `app_secret` | ?string | `null` | no | Prosody's `app_secret`: tokens signed HS256 |
| `private_key` | ?string | `null` | for JaaS | a PEM, or the path of its file: tokens signed RS256 (needs `ext-openssl`) |
| `key_id` | ?string | `null` | with `private_key` | the token's `kid` (JaaS: `<AppID>/<key id>`) |
| `jaas` | ?bool | `null` | no | Jitsi as a Service; told from the domain `8x8.vc` or an AppID starting `vpaas-magic-cookie-` when null |
| `audience` | ?string | `null` | no | the token's `aud` (`jitsi` when null); self-hosted only |
| `issuer` | ?string | `null` | no | the token's `iss` (`app_id` when null); self-hosted only |
| `subject` | ?string | `null` | no | the token's `sub` (the domain without its port when null: a tenant otherwise); self-hosted only |
| `features` | array | `recording`, `livestreaming`, `transcription`, `outbound-call` all `false` | no | JaaS: the token's `context.features`; `recording: true` also sets `Capabilities::$recording` |
| `webhook_secret` | ?string | `null` | for `notify()` | JaaS: the webhook endpoint's signing secret; without it `notify()` is not supported |
| `webhook_tolerance` | int | `300` | no | seconds a callback's timestamp may differ from now; `0`: any age |
| `token_ttl` | int | `7200` | no | seconds a token lasts when the meeting has no end (at least 60); with an end in the future, it lapses five minutes after the end |
| `room_prefix` | string | `''` | no | before the room's name (lower-cased) |
| `room_secret` | ?string | `null` | no | the room's name an HMAC-SHA256 of the key with it, instead of a SHA-256: set it when meeting keys can be guessed |
| `config` | array | `disableInviteFunctions: true`, `disableThirdPartyRequests: true` | no | `config.js` overrides (`configOverwrite`; in the address after the `#`, flattened, `config.<key>=<JSON>`) |
| `interface_config` | array | `[]` | no | `interface_config.js` overrides |
| `embed` | bool | `true` | no | `false`: the instance forbids being framed - `join()` answers a `LINK` |

The instance's kind follows from the options: no `app_secret` and no `private_key`, an open
instance (the room's name is the only secret); one of them, a token per participant; `jaas`, JaaS's
claims and rooms under the AppID. Inconsistent options (a secret without `app_id`, a key without
`key_id`, JaaS without `app_id`, `private_key` and `key_id`) are an `InvalidConfigException` when
the gateway is built.
