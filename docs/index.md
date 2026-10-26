# omnisense/omnimeet

Omnimeet is one PHP contract for video meetings: **open a room and let someone in**, whatever
carries the call - the two browsers themselves, or a conferencing service. An application asks
every gateway the same questions - open this meeting, how does this person enter it now, close it,
where does it stand, what is this callback - and gets the same answers back: a `Meeting` with its
reference on the gateway, an `Access` the page renders (an engine to run, a frame to show, an
address to go to), a `Notification`.

```php
$gateway = $registry->get('direct');                       // or 'group', a Jitsi instance: the same calls

$meeting = $gateway->open(new Meeting($token, $startsAt, $endsAt));
$meeting->reference;                                       // the room on that gateway: keep it

$access = $gateway->join($meeting, Participant::guest('u42'));
$access->kind;                                             // ENGINE, FRAME or LINK
$access->origins;                                          // the third parties the browser will talk to: [] for direct

$gateway->close($meeting);                                 // where the gateway can: supports(Close::class)
```

The design is the family's (Payum's): a gateway's **factory** fills a configuration with its name,
its capabilities, its options, its client and one **action** per request it answers; the
**gateway** runs the action that supports each request; a **registry** holds the application's
gateways by name. The core requires PHP alone - no framework, no library - and so do its two
gateways: neither calls anything over the network (a direct meeting is carried by the two
browsers, a Jitsi room exists when its first participant comes in, a token is signed on this side).
A Symfony bundle is included ([Symfony](symfony.md)).

Published as `omnisense/omnimeet`, formerly `omniscient/omnimeet` and before that
`glitchr/omnimeet`: the package `replace`s the old names. The namespace is `Omnimeet\`; gateways
keep the `omnimeet/` vendor and the `Omnimeet\<Gateway>\` namespace.

## When to use it

- An application that holds video meetings - a consultation, an interview, a class - and wants the
  call carried by the browsers alone (two people, no third party) or by a conferencing service
  (several people), without rewriting its pages when it changes.
- A record of the meeting kept by the application: who may enter, when, the waiting room. The
  gateway only opens the room and lets someone in.

It is not a calendar, a chat, a recorder nor a waiting room, and it decides nothing: **who** may
enter and **when** is the application's to check before asking. It is told a key, a start and an
end, and of each person a role and an opaque identifier - a name or a title only when the
application gives one, never an account.

## Gateways

| Package | Factory name | The call | Third party | Open | Join | Close | Fetch | Notify |
|---|---|---|---|---|---|---|---|---|
| [`omnimeet/direct`](https://github.com/glitchr-omnisense/omnimeet-direct) | `direct` | from browser to browser (WebRTC): two people, end to end, nothing recorded; the application relays the handshake; a TURN relay of its own when a network forbids the direct way | none | yes | yes (engine) | yes | yes | - |
| [`omnimeet/jitsi`](https://github.com/glitchr-omnisense/omnimeet-jitsi) | `jitsi` | a Jitsi Meet instance (one's own, a public one, Jitsi as a Service): several people, an embedded frame driven from the page, a token per participant where the instance checks one | the instance | yes | yes (frame, or link) | - | - | with `webhook_secret` (JaaS) |

The capabilities are what each gateway's `supports()` answers (probed in the
[harness](harness.md) on 2026-10-26, `bare`).

## How this manual is organised

- [Installation](installation.md): a first meeting in plain PHP, what the application does around
  a gateway.
- [Symfony](symfony.md) and [Laravel](laravel.md).
- [Models](models.md): `Meeting`, `Participant`, `Access`, `Capabilities`, `Status`,
  `Notification`.
- [Gateways](gateways.md): the contract, the factory and the registry, rendering an `Access`, the
  state of a meeting, callbacks, writing a gateway.
- [Configuration reference](configuration.md): the registry, the bundle, every gateway's options.
- [Errors](errors.md).
- [Testing](testing.md).
- [The Docker harness](harness.md): the console, bare PHP, the demo between two tabs.
- [Verified, and not](verified.md).
