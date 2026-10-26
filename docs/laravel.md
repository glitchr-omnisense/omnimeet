---
title: Laravel
order: 16
---

# Laravel

There is no Laravel bridge for Omnimeet: omnibridge/laravel has none, and this package ships no
service provider. None is needed for it to run - the core and its gateways need no framework - so
a service provider of the application builds the registry once, by hand.

## A service provider

```php
namespace App\Providers;

use App\Meetings\DatabaseSignalStore;            // the application's SignalStoreInterface
use Illuminate\Support\ServiceProvider;
use Omnimeet\Direct\DirectGatewayFactory;
use Omnimeet\Direct\Signaling;
use Omnimeet\Jitsi\JitsiGatewayFactory;
use Omnimeet\Registry;

final class MeetingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DirectGatewayFactory::class, fn ($app) => new DirectGatewayFactory($app->make(DatabaseSignalStore::class)));
        $this->app->singleton(Signaling::class, fn ($app) => $app->make(DirectGatewayFactory::class)->signaling());

        $this->app->singleton(Registry::class, fn ($app) => new Registry(
            [$app->make(DirectGatewayFactory::class), new JitsiGatewayFactory()],
            [
                'direct' => ['factory' => 'direct', 'options' => [
                    'turn_secret' => config('services.turn.secret'),
                    'turn_urls' => config('services.turn.urls'),          // a list, or one comma-separated string
                ]],
                'group' => ['factory' => 'jitsi', 'options' => [
                    'domain' => config('services.jitsi.domain'),
                    'app_id' => config('services.jitsi.app_id'),
                    'app_secret' => config('services.jitsi.app_secret'),
                ]],
            ],
        ));
    }
}
```

The direct gateway's store must outlive a request (a table, a cache): the handshake is posted by
one request and read by another. Its two endpoints are routes of the application, which check that
the caller is one of the meeting's participants before calling `Signaling::send()` or `receive()`
([omnimeet/direct](https://github.com/glitchr-omnisense/omnimeet-direct/blob/1.x/docs/index.md#the-two-endpoints)).

## The script

`$access->script` is a file inside the gateway's package (`vendor/omnimeet/direct/public/js/visio.js`,
`vendor/omnimeet/jitsi/public/js/jitsi.js`): copy it to `public/` at build time, or answer it from a
route, as for any other asset. Nothing of a gateway is loaded from elsewhere for an `ENGINE`.
