<?php

/**
 * Every gateway package: its slug (omnimeet/<slug>, github.com/glitchr-omnisense/omnimeet-<slug>),
 * its tests' namespace and its factory class.
 */
return [
    'direct' => ['Omnimeet\\Direct\\Tests\\', 'Omnimeet\\Direct\\DirectGatewayFactory'],
    'jitsi' => ['Omnimeet\\Jitsi\\Tests\\', 'Omnimeet\\Jitsi\\JitsiGatewayFactory'],
];
