<?php

namespace Omnimeet;

use Omnisense\Contracts\Provider\ProviderFactoryInterface;

/** Builds a gateway from its options (an instance's address, a secret, a relay...). */
interface GatewayFactoryInterface extends ProviderFactoryInterface
{
    /** The name gateways are configured with: "direct", "jitsi"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): GatewayInterface;
}
