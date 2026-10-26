# omnisense/omnimeet

One contract for video meetings - **open a room and let someone in** - whatever carries the
call: the two browsers themselves, or a conferencing service.

Published as `omnisense/omnimeet` (formerly `glitchr/omnimeet`, which this package replaces: a
`composer.json` that still requires the old name keeps resolving to this package, never both).
`omnisense/` is the vendor of every family's core; the providers keep theirs (`omnimeet/direct`...).

```php
$gateway = $registry->get('direct');                       // or 'group', a Jitsi instance: the same calls

$meeting = $gateway->open(new Meeting($token, $startsAt, $endsAt));
$meeting->reference;                                       // the room on that gateway: keep it

$access = $gateway->join($meeting, Participant::guest('u42'));
$access->kind;                                             // ENGINE: a script of the gateway's package and its options
                                                           // FRAME: a provider's interface to embed - LINK: an address
$access->origins;                                          // the third parties the browser will talk to: [] for direct

$gateway->close($meeting);                                 // where the gateway can: supports(Close::class)
```

This package holds the contract (`GatewayInterface`, the requests `Open`, `Join`, `Close`,
`Fetch`, `Notify`, `GatewayFactory`, `Registry`), the models (`Meeting`, `Participant`, `Access`,
`Capabilities`, `Status`, `Notification`) and a bridge for Symfony. It needs no framework and no
library: it requires PHP alone, and so do its gateways - none of them calls anything. Each gateway
is a package of its own:

| Package | The call | Third party |
|---|---|---|
| [`omnimeet/direct`](https://github.com/glitchr-omnisense/omnimeet-direct) | from browser to browser (WebRTC): two people, encrypted end to end, nothing recorded; the application relays the handshake, a TURN relay of your own when a network forbids the direct way | none |
| [`omnimeet/jitsi`](https://github.com/glitchr-omnisense/omnimeet-jitsi) | on a Jitsi Meet instance - yours, a public one, Jitsi as a Service: several people, an embedded frame driven from the page, a token per participant | the instance |

## What a gateway is, and is not

A gateway opens a room and lets a participant in. It does not decide **who** may enter nor
**when**, it does not keep a waiting room, it does not know what the meeting is for: the
application does, on its own record of the meeting - the same whatever the gateway. It is told a
key, a start and an end, and of each person a role and an opaque identifier; a name or a title
only if the application gives one. **Never an account.**

What a gateway cannot do is a `NotSupportedException`, and `supports()` says so beforehand:
nobody calls back from two browsers talking to each other, and Jitsi's documentation shows no way
to close a room from a server.

This is not a calendar, a chat, nor a recorder; and the direct gateway does not become a group
call.

## Documentation

- [Overview: gateways and what each does](docs/index.md)
- [Installation and a first meeting](docs/installation.md)
- [Symfony](docs/symfony.md) and [Laravel](docs/laravel.md)
- [Models](docs/models.md)
- [Gateways: the contract, rendering an Access, writing one](docs/gateways.md)
- [Configuration reference](docs/configuration.md)
- [Errors](docs/errors.md)
- [Testing](docs/testing.md)
- [The Docker harness: the console, bare PHP, the demo](docs/harness.md)
- [Verified, and not](docs/verified.md)

## Plain PHP

```sh
composer require omnisense/omnimeet omnimeet/direct omnimeet/jitsi
```

```php
use Omnimeet\Direct\DirectGatewayFactory;
use Omnimeet\Jitsi\JitsiGatewayFactory;
use Omnimeet\Registry;

$registry = new Registry([new DirectGatewayFactory($signalStore), new JitsiGatewayFactory()], [
    'direct' => ['factory' => 'direct', 'options' => ['turn_secret' => '...', 'turn_urls' => ['turn:turn.example.org:3478']]],
    'group' => ['factory' => 'jitsi', 'options' => ['domain' => 'meet.example.org']],
]);
```

No bundle, no container: a factory per gateway package, the registry built by hand.
[docs/installation.md](docs/installation.md) opens on a whole script that runs as it is.

## Symfony

`Omnimeet\Bridge\Symfony\OmnimeetBundle` does that wiring in a Symfony application
([docs/symfony.md](docs/symfony.md)); its components (`symfony/config`,
`symfony/dependency-injection`, `symfony/http-kernel`) are not required by this package.

```yaml
omnimeet:
    gateways:
        direct: { factory: direct, options: { turn_secret: '%env(TURN_SECRET)%', turn_urls: '%env(TURN_URLS)%' } }
        group: { factory: jitsi, options: { domain: meet.example.org } }
```

```php
public function __construct(GatewayInterface $direct, GatewayInterface $group) {}
```

[`omnibase/office`](https://github.com/glitchr-omnisense/omnibase-office), a professional practice's
site, is built on it: the room, its waiting room and its pages are office's, the call a gateway's.

## Docker: every gateway, and a call between two tabs

```sh
cd docker && cp .env.dist .env
docker compose run --rm omnimeet gateways
docker compose run --rm omnimeet handshake
docker compose run --rm omnimeet bare          # plain PHP: no bundle, no container, and what PHP loaded
docker compose run --rm omnimeet test
docker compose up                              # the demo, in plain PHP: http://localhost:8799/
```

## Responsibility

`omnisense/omnimeet` and its `omnimeet/*` providers are independent, unofficial clients: none is
affiliated with, endorsed by or sponsored by the services it reaches. Each service keeps its own
terms (accounts and keys, rate limits, the instance's own rules), named with a link in the
"Responsibility" section of the README of each provider that reaches one. The MIT licence covers
this code only and grants no right to any service or to its data.

Whoever installs and runs these packages is responsible for complying with those terms, for
obtaining whatever they require, and for any use made of what they return. The authors publish them
free of charge, make no commercial use of them, and accept no liability for how they are used (see
[LICENSE](LICENSE)).

License: MIT since 2026-10-09; earlier versions remain published under LGPL-3.0-or-later.
