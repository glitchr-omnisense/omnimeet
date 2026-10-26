---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-10-26 on the workspace's checkouts (the core, omnimeet/direct, omnimeet/jitsi), PHP
8.4.27, PHPUnit 11.5.57, no network: 70 tests, 357 assertions, green.

- The core: a meeting opened, joined, fetched and closed; a meeting not opened cannot be joined; a
  callback read once its signature holds; a gateway saying what it does not do; an action leaving
  its request unanswered is an error; an option missing said when the gateway is built
  (`GatewayTest`); gateways by name built once, nothing built before it is asked for, a gateway
  built afresh with overrides (`RegistryTest`); the models' rules (`ModelTest`); the bundle
  building the configured gateways, injectable by name, and giving a factory what its constructor
  asks of the application (`Bridge/OmnimeetBundleTest`).
- In plain PHP (`BareTest`, `bin/bare --json`): the registry built by hand, a meeting held from
  browser to browser, a frame and its token made, nothing of Symfony loaded; the bundle does load
  the framework.
- omnimeet/direct: two people, end to end, no third party; the engine with the participant's own
  relay credentials; without a relay, no ICE server; the room read from its handshake and purged
  on close; the handshake's rules (order, pages, the five messages, JSON, size).
- omnimeet/jitsi: an open instance; what the documentation does not show not supported; a token per
  participant (HS256, RS256), its audience, issuer, subject and lifetime; JaaS's claims, rooms and
  script; `room_secret`; the configuration overrides; `embed: false`; misconfigurations said when
  first asked; JaaS callbacks of every event, refused unsigned, with another secret, changed on the
  way, replayed an hour later, or not an event; the documentation's own signature example.

## Run for this manual

On 2026-10-26, PHP 8.4.27, no network, the workspace's checkouts: the script of
[Installation](installation.md#plain-php) and `bin/bare` ([harness](harness.md)), the bundle's
configuration tree and services ([Symfony](symfony.md)), the direct gateway's flow of
[Testing](testing.md), and the providers' examples. Their output is pasted as it came.

## In browsers

As the gateways' manuals state: a real call between two headless Chromes with fake cameras on
2026-10-05, through `omnibase/office`'s room - direct (and through a local coturn with
`?relay=1`), and on a public open Jitsi instance (`meet.evolix.org`); two public instances seen
refusing to be framed or waiting for a signed-in moderator.

## What is not verified

- Jitsi tokens against a real instance with token authentication, Jitsi as a Service and its
  webhooks: written and tested against the documentation only.
- `endConference` from a host's page: sent, what the instance then does not checked.
- `embed: false` in a browser.
- Laravel: no bridge; the service provider of [Laravel](laravel.md) is a sketch, not a tested
  package.
- No conformance suite exists for the family yet.
