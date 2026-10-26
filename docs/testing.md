---
title: Testing
order: 60
---

# Testing

Omnimeet's gateways call nothing over the network, so an application tests its code against the
real ones: no mock client, no fixtures. There is no fake gateway and no conformance suite in the
family.

## The direct gateway in a test

`InMemorySignalStore` keeps the handshake in the test's process; the whole flow - open, join, the
handshake, fetch, close - runs as it does between two browsers:

```php
<?php // direct.php

require __DIR__.'/vendor/autoload.php';

use Omnimeet\Direct\DirectGatewayFactory;
use Omnimeet\Direct\Exception\InvalidSignalException;
use Omnimeet\Direct\InMemorySignalStore;
use Omnimeet\Direct\TurnCredentials;
use Omnimeet\Model\Meeting;
use Omnimeet\Model\Participant;

$factory = new DirectGatewayFactory(new InMemorySignalStore());
$gateway = $factory->create(['turn_secret' => 'coturn-static-auth-secret', 'turn_urls' => 'turn:turn.example.org:3478?transport=udp,turn:turn.example.org:3478?transport=tcp', 'turn_ttl' => 600]);

$meeting = $gateway->open(new Meeting('k4f9d2a7c1e8b3f6'));
$access = $gateway->join($meeting, Participant::host('u1'));
echo $access->kind->value, ' ', basename($access->script), ' until ', $access->expiresAt->format('H:i:s'), "\n";
echo json_encode($access->options['iceServers'][0], JSON_UNESCAPED_SLASHES), "\n";
$turn = TurnCredentials::fromOptions(['turn_secret' => 'coturn-static-auth-secret', 'turn_urls' => ['turn:turn.example.org:3478']]);
var_dump($turn->verify($access->options['iceServers'][0]['username'], $access->options['iceServers'][0]['credential']));

// What the application's endpoint does for each POST and GET
$signaling = $factory->signaling();
$signaling->send($meeting->reference, 'u2', 'hello');
$signaling->send($meeting->reference, 'u1', 'offer', '{"type":"offer","sdp":"v=0..."}');
echo json_encode($signaling->receive($meeting->reference, 'u2')), "\n";
$answer = $signaling->send($meeting->reference, 'u2', 'answer', '{"type":"answer","sdp":"v=0..."}');
var_dump($answer->connects());
$state = $gateway->fetch($meeting);
echo $state->status->value, ', present: ', implode(', ', array_map(fn ($p) => $p->id, $state->present)), "\n";
try {
    $signaling->send($meeting->reference, 'u2', 'renegotiate');
} catch (InvalidSignalException $e) {
    echo $e->getMessage(), "\n";
}
echo $gateway->close($meeting)->status->value, ', ', count($signaling->receive($meeting->reference, 'u1')), " message(s) left\n";
```

```
$ php direct.php
engine visio.js until 09:54:14
{"urls":["turn:turn.example.org:3478?transport=udp","turn:turn.example.org:3478?transport=tcp"],"username":"1793008454:u1","credential":"BV94caMIQhCOOyfH7BZkATh/pBw="}
bool(true)
[{"id":2,"type":"offer","payload":{"type":"offer","sdp":"v=0..."}}]
bool(true)
live, present: u2, u1
Unknown signal "renegotiate".
ended, 0 message(s) left
```

(run on 2026-10-26, PHP 8.4.27, no network; the credentials change with the time)

An application's own `SignalStoreInterface` (a table, a cache) is tested the same way, by giving it
to the factory in place of the in-memory one.

## The Jitsi gateway in a test

`join()` answers the frame's address and options, and the token signed on this side: a test reads
them, and checks the token with `Omnimeet\Jitsi\Jwt::verify($token, $secret)` (HS256) or the public
key (RS256). A JaaS callback is signed in the test with the endpoint's secret:

```php
$t = (string) time();
$signature = base64_encode(hash_hmac('sha256', $t.'.'.$body, 'whsec_example', true));
$notification = $gateway->notify($body, ['X-Jaas-Signature' => 't='.$t.',v1='.$signature]);
```

A whole example and its output: [omnimeet/jitsi](https://github.com/glitchr-omnisense/omnimeet-jitsi/blob/1.x/docs/index.md#example).

## A gateway of the test's own

A `GatewayFactory` whose actions answer from arrays stands in for a provider: the core's tests do
it with `Tests/StubFactory.php`. Registered in a `Registry` (or as a service tagged
`omnimeet.gateway_factory`) under the name the application uses, it replaces the real one.

## In the browser

What the PHP side cannot show - two browsers connecting, the relay, a frame an instance refuses -
is checked with the harness's demo (`docker compose up`, two tabs: [harness](harness.md)) or two
headless browsers with fake cameras, as the [verified](verified.md) page says was done.
