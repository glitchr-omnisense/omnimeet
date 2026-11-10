<?php

namespace Omnimeet\Tests\Bridge;

use Omnimeet\Bridge\Symfony\OmnimeetBundle;
use Omnimeet\Exception\UsageLimitReachedException;
use Omnimeet\Model\Meeting;
use Omnimeet\Registry;
use Omnimeet\Tests\StubFactory;
use Omnisense\Contracts\Policy\InMemoryRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/** The bundle passes a gateway's policy and usage, and the shared limiter, to the registry. */
final class OmnimeetBundlePolicyTest extends TestCase
{
    public function testAGatewaysPolicyAndTheSharedLimiterAreTheRegistrys(): void
    {
        $container = new ContainerBuilder();
        $container->register(StubFactory::class)->setAutoconfigured(true);
        $container->register('app.limiter', InMemoryRateLimiter::class)->setPublic(true);
        $extension = (new OmnimeetBundle())->getContainerExtension();
        self::assertNotNull($extension);
        $container->registerExtension($extension);
        $container->loadFromExtension('omnimeet', ['limiter' => 'app.limiter', 'gateways' => [
            'consultation' => [
                'factory' => 'stub',
                'options' => ['host' => 'meet.example.org'],
                'policy' => ['rateLimits' => [['max' => 1, 'window' => 'day', 'operations' => ['open']]]],
                'usage' => ['commercial' => true],
            ],
            'staff' => ['factory' => 'stub', 'options' => ['host' => 'staff.example.org']],
        ]]);
        $container->compile();

        $registry = $container->get(Registry::class);
        self::assertInstanceOf(Registry::class, $registry);
        self::assertCount(1, $registry->policy('consultation')->limitsFor('open'));
        self::assertSame([], $registry->policy('staff')->limitsFor('open'), 'nothing given, nothing added: the stub declares no limit');
        $gateway = $registry->guarded('consultation');
        $gateway->open(new Meeting('k'));
        $limiter = $container->get('app.limiter');
        self::assertInstanceOf(InMemoryRateLimiter::class, $limiter);
        self::assertSame(0, $registry->guard('consultation')->remaining('open'), 'counted in the application\'s limiter');

        $this->expectException(UsageLimitReachedException::class);
        $gateway->open(new Meeting('k2'));
    }
}
