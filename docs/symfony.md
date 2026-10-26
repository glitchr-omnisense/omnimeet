---
title: Symfony
order: 15
---

# Symfony

Omnimeet runs without a framework ([installation](installation.md)); in a Symfony application its
bundle does the wiring. Its components - `symfony/config`, `symfony/dependency-injection`,
`symfony/http-kernel` - are not required by `omnisense/omnimeet`: the application has them, and
nothing of them is loaded outside Symfony.

Register `Omnimeet\Bridge\Symfony\OmnimeetBundle` (no Flex recipe):

```php
// config/bundles.php
return [
    // ...
    Omnimeet\Bridge\Symfony\OmnimeetBundle::class => ['all' => true],
];
```

```yaml
# config/packages/omnimeet.yaml
omnimeet:
    gateways:                      # by name: a factory and its options
        direct:
            factory: direct
            options:
                turn_secret: '%env(default::TURN_SECRET)%'
                turn_urls: '%env(default::TURN_URLS)%'       # one variable, comma-separated
        group:
            factory: jitsi
            options:
                domain: '%env(JITSI_DOMAIN)%'
                app_id: '%env(default::JITSI_APP_ID)%'
                app_secret: '%env(default::JITSI_APP_SECRET)%'
```

## The configuration tree

As the bundle's `configure()` declares it (dumped with Symfony's `YamlReferenceDumper`, the dumper
`config:dump-reference omnimeet` uses):

```yaml
omnimeet:

    # The gateways, by name: a factory (direct, jitsi...) and its options.
    gateways:

        # Prototype
        name:
            factory:              ~ # Required
            options:              []
```

| Key | Type | Default | |
|---|---|---|---|
| `gateways.<name>.factory` | string | required | `direct`, `jitsi`, or an application factory's `getName()` |
| `gateways.<name>.options` | any | `[]` | handed whole to the factory's `create()` ([Configuration](configuration.md)) |

## What the bundle registers

| Service | | |
|---|---|---|
| `Omnimeet\Direct\DirectGatewayFactory`, `Omnimeet\Jitsi\JitsiGatewayFactory` | private, autowired, tagged `omnimeet.gateway_factory` | each when its package is installed |
| `Omnimeet\Registry` | public | every service tagged `omnimeet.gateway_factory`, the configured `gateways` |
| `omnimeet.gateway.<name>` | private | `Registry::get('<name>')`, autowired as `GatewayInterface $<name>` |

Any service of the application implementing `Omnimeet\GatewayFactoryInterface` is tagged
`omnimeet.gateway_factory` by autoconfiguration. The bundle defines no command, route, event or
Twig function: the signal endpoints of the direct gateway, the page and its script are the
application's.

The services compiled for two gateways (`direct`, and `group` on `jitsi` with a domain) and a
`SignalStoreInterface` service, then the registry's factories and the gateways' titles:

```
Omnimeet\Direct\DirectGatewayFactory
Omnimeet\Jitsi\JitsiGatewayFactory
Omnimeet\Registry
direct, jitsi | Direct, Jitsi Meet
```

(run on 2026-10-26, Symfony 8 DependencyInjection, the workspace's checkouts)

## Factories and what they are given

Every `omnimeet/*` package installed registers its factory, **autowired**: what its constructor
asks for is the application's service of that type. `omnimeet/direct` asks for a
`SignalStoreInterface` - alias it to your store:

```php
#[AsAlias(SignalStoreInterface::class)]
final class SignalRepository extends ServiceEntityRepository implements SignalStoreInterface { /* ... */ }
```

Without such a service the factory's argument is left out (it is nullable) and the handshake is
kept in the PHP process's memory (`InMemorySignalStore`), which forgets it between two requests:
under PHP-FPM a direct call then never connects.

What is autowired:

| Service | |
|---|---|
| `GatewayInterface $direct`, `GatewayInterface $group` | one gateway by the argument's name (the configured name) |
| `Registry` | every configured gateway by name (`get()`, `has()`, `names()`, `options()`, `create()`) |

Nothing is built when the container compiles: a gateway is built the first time it is asked
for, and an option left empty only shows then (`InvalidConfigException`).

An application's own gateway - a class implementing `GatewayFactoryInterface` - is registered
too, autoconfigured, and can be named as a `factory`.

```php
final class MeetingController extends AbstractController
{
    #[Route('/meetings/{token}', name: 'app_meeting')]
    #[IsGranted('MEETING_ENTER', 'room')]                       // who may enter is the application's to say
    public function __invoke(Room $room, Registry $gateways): Response
    {
        $gateway = $gateways->get($room->getGateway());
        $access = $gateway->join(
            new Meeting($room->getToken(), $room->getStartsAt(), $room->getEndsAt(), reference: $room->getReference()),
            new Participant('u'.$this->getUser()->getId(), $room->isHost($this->getUser()) ? Role::HOST : Role::GUEST),
        );

        return $this->render('meeting/room.html.twig', ['room' => $room, 'access' => $access]);
    }
}
```

[`omnibase/office`](https://github.com/glitchr-omnisense/omnibase-office) does all of this for a
professional practice: its `Visio\Rooms` opens a room on `office.visio.gateway`, its page renders
the `Access`, its `SignalRepository` is the direct gateway's store.
