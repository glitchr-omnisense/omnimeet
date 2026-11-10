<?php

namespace Omnimeet\Tests;

use Omnimeet\Registry;
use Omnimeet\Testing\BareTestCase;

/**
 * Every class of the core loads with omnisense/contracts alone - the Symfony
 * bundle (@suggested symfony/http-kernel) and the bare check itself
 * (@suggested phpunit/phpunit) left out, as they need what the core only
 * suggests.
 */
final class LoadAllTest extends BareTestCase
{
    protected static function entryClass(): string
    {
        return Registry::class;
    }

    protected static function requiredPackages(): array
    {
        return ['symfony/http-client-contracts', 'symfony/deprecation-contracts', 'psr/container'];
    }
}
