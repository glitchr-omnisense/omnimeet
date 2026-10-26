---
title: Gateways
order: 30
---

# Gateways

One contract, `Omnimeet\GatewayInterface`, whatever carries the call:

| Method | Request | Answers |
|---|---|---|
| `open(Meeting)` | `Request\Open` | the meeting with its `reference` on the gateway. The same key, the same room |
| `join(Meeting, Participant)` | `Request\Join` | an `Access`: how that participant enters, now |
| `close(Meeting)` | `Request\Close` | the meeting, `ENDED`: nobody gets in, what the gateway kept goes |
| `fetch(Meeting)` | `Request\Fetch` | the meeting with its `status` and who is `present` |
| `notify($body, $headers)` | `Request\Notify` | a `Notification`, once the callback's signature holds |

`capabilities()` says what its meetings are; `supports(Request::class)` what it answers. Each
method is a shortcut for `execute(new Request\...)`. What a gateway does not do throws
`NotSupportedException` - nothing is guessed, nothing silently skipped.

| | `omnimeet/direct` | `omnimeet/jitsi` |
|---|---|---|
| The call | from browser to browser (WebRTC), two people, end to end | on a Jitsi Meet instance, several people |
| Third party | none | the instance |
| `open` | the room is the key | names the room; nothing is asked of the instance |
| `join` | `ENGINE`: `visio.js`, the participant's side, their ICE servers | `FRAME`: the instance's address and token, `jitsi.js` to drive it |
| `close` | purges the handshake | not supported: a moderator's page ends the conference |
| `fetch` | reads the handshake: who is there, `LIVE` once answered | not supported |
| `notify` | not supported: nobody to send any | JaaS webhooks, with `webhook_secret` |
| Needs | a store for the handshake; a TURN relay of your own, optional | an instance; its secret or key pair when it checks tokens |

A gateway does not decide who may enter, nor when, nor does it know who waits: that is the
application's, on its own record of the meeting - the same whatever the gateway.

## The contract, member by member

`Omnimeet\GatewayInterface`:

| Method | |
|---|---|
| `getName(): string`, `getTitle(): string` | the factory's name (`direct`, `jitsi`) and title (`Direct`, `Jitsi Meet`) |
| `capabilities(): Capabilities` | what its meetings are ([Models](models.md#capabilities)) |
| `supports(string $request): bool` | whether an action answers that request class; asked with a probe made without the request's constructor |
| `execute(Request $request): Request` | the request, answered by the first action that supports it; `NotSupportedException` when none does, `ProviderException` when the action left it unanswered |
| `open(Meeting): Meeting`, `join(Meeting, Participant): Access`, `close(Meeting): Meeting`, `fetch(Meeting): Meeting`, `notify(string $body, array $headers = []): Notification` | `execute()` with `Request\Open`, `Join`, `Close`, `Fetch`, `Notify` |

`Omnimeet\Gateway` (`final`) is the implementation every factory builds: a name, a title, the
capabilities and the actions.

### Requests

`Omnimeet\Request\Request` (abstract): `setResult()`, `getResult()`, `isAnswered()`. Each request is
`final` with public readonly properties:

| Request | Properties | Result |
|---|---|---|
| `Open` | `meeting` | `getMeeting()`: the meeting with its `reference` and `Status::OPEN`; the same key gives the same reference |
| `Join` | `meeting`, `participant` | `getAccess()` |
| `Close` | `meeting` | `getMeeting()`: `Status::ENDED` |
| `Fetch` | `meeting` | `getMeeting()`: its `status` and who is `present` |
| `Notify` | `body`, `headers` | `getNotification()`; `header($name)` reads a header whatever its case (the first value of a list) |

`join()`, `close()` and `fetch()` need an opened meeting: `Meeting::reference()` throws
`InvalidConfigException` for one without a reference.

### The factory and its `Config`

`Omnimeet\GatewayFactory` (abstract) has no constructor of its own - a gateway that needs something
(a store, an HTTP client) takes it in its factory's constructor. `create(array $options = [])`:

1. fills an `Omnimeet\Config` (an `ArrayObject`) with the options given, then the factory's defaults
   (`populateConfig()`, through `Config::defaults()`) under them;
2. checks `omnimeet.required_options`: one missing or empty (`null`, `''`, `[]`) is an
   `InvalidConfigException` naming them all (`The "jitsi" gateway needs: domain.`);
3. builds the client from `omnimeet.api` and the capabilities from `omnimeet.capabilities` (each
   a value or a closure of the `Config`); no `Capabilities` is an `InvalidConfigException`;
4. collects every `omnimeet.action.<name>` (a closure of the `Config` is called; one that gives
   anything but an action - `null` - is left out), hands the client to the actions implementing
   `ApiAwareInterface` (`ApiAwareTrait` checks its class), and returns a `Gateway`.

`Config::get($key, $default)`, `validateNotEmpty($keys)`, and `list($key)`: a list option given as
one comma-separated string (an environment variable) split into its values.

### The registry

`new Omnimeet\Registry(iterable $factories, array $config)`, `$config` the gateways by name
(`['<name>' => ['factory' => '<factory name>', 'options' => [...]]]`):

| Method | |
|---|---|
| `get(string $name): GatewayInterface` | built on first use, then kept; `No "x" gateway; configured: ...` and `No "y" factory for the "x" gateway; installed: ...` as `InvalidConfigException` |
| `has($name)`, `names()`, `options($name)`, `factories()` | |
| `create(string $name, array $overrides = [])` | a gateway built afresh, `$overrides` over its options; not kept |
| `all()` | every gateway, built, by name |

## Rendering an Access

```php
$access = $gateway->join($meeting, $participant);

match ($access->kind) {
    AccessKind::ENGINE => /* the page's own markup + the gateway's script */,
    AccessKind::FRAME  => /* a frame on $access->url, or driven by the gateway's script */,
    AccessKind::LINK   => /* a button to $access->url */,
};
```

- **The script is a file of the gateway's package** (`$access->script`, an absolute path):
  serve it yourself - copy it to the public directory at build time, or answer it from a route.
  Nothing is loaded from elsewhere for an `ENGINE`.
- **A script boots on its own root**, `[data-omnimeet-<engine>]` (`data-omnimeet-direct`,
  `data-omnimeet-jitsi`), and takes `$access->options` from it: each package's documentation
  gives its markup. A script that drives a frame puts it in `[data-omnimeet-frame]`, is told
  `omnimeet:join`, `omnimeet:leave`, `omnimeet:end` (events on its root) and says where it stands
  with `omnimeet:state` (`{state}`): the page's own controls stay the same from one gateway to
  the other.
- **A third party is named first.** When `$access->isThirdParty()`, `$access->origins` lists who
  the participant's browser will talk to: say so, wait for consent, and only then load the
  frame. They also go in the page's `Permissions-Policy` (`camera=(self "https://meet.example.org")`)
  and its Content-Security-Policy (`frame-src`, `script-src`); `$access->allow()` is the frame's
  `allow` attribute.
- **Ask again each time.** An `Access` carries credentials made for one person
  (`$access->expiresAt`): never cache it, never show it to anyone else.

## The state of a meeting

`fetch()` answers what the gateway itself knows, which may be little: the direct one reads its
handshake (who said something, whether an answer was given), Jitsi publishes nothing to ask.
Presence, a waiting room, "the host has arrived": keep them in the application - a heartbeat
from the page - and they work for every gateway.

## Callbacks

```php
// the webhook's controller
try {
    $notification = $registry->get('group')->notify($request->getContent(), $request->headers->all());
} catch (InvalidNotificationException) {
    return new Response('', 400);
}
if ($seen->has($notification->id)) {   // the same callback, sent twice
    return new Response('', 200);
}
match ($notification->event) {
    Event::JOINED => ..., Event::LEFT => ..., Event::ENDED => ..., Event::RECORDING_READY => ..., default => null,
};
```

## Writing a gateway

A package `omnimeet/<provider>`: a factory extending `Omnimeet\GatewayFactory` that fills a
`Config` - its name, title, capabilities, the options it needs, its client (`omnimeet.api`) and
one action per request it answers:

```php
final class WherebyGatewayFactory extends GatewayFactory
{
    // A provider reached over HTTP takes its client here: the application's, a mock in a test.
    public function __construct(private readonly HttpClientInterface $http)
    {
    }

    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnimeet.factory_name' => 'whereby',
            'omnimeet.factory_title' => 'Whereby',
            'omnimeet.required_options' => ['api_key'],
            'omnimeet.capabilities' => new Capabilities(thirdParty: true, access: [AccessKind::FRAME]),
            'omnimeet.api' => fn (Config $c) => new Api((string) $c['api_key'], $this->http),
            'omnimeet.action.open' => new OpenAction(),
            'omnimeet.action.join' => new JoinAction(),
            'omnimeet.action.close' => new CloseAction(),
        ]);
    }
}
```

An action implements `Action\ActionInterface` (`supports(Request)`, `execute(Request)` which
sets the request's result) and, to be handed the client, `Action\ApiAwareInterface`. Leave out
the actions the provider's documentation does not show: the gateway then says it does not
support them.
