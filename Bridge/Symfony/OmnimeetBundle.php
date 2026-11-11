<?php

namespace Omnimeet\Bridge\Symfony;

use Omnimeet\Direct\DirectGatewayFactory;
use Omnimeet\GatewayFactoryInterface;
use Omnimeet\GatewayInterface;
use Omnimeet\Jitsi\JitsiGatewayFactory;
use Omnimeet\Registry;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Omnimeet in a Symfony application: the gateway packages installed
 * (omnimeet/direct, omnimeet/jitsi) registered, the gateways built from
 * configuration and injectable by their name:
 *
 *     omnimeet:
 *         gateways:
 *             direct:
 *                 factory: direct
 *                 options: { turn_secret: '%env(TURN_SECRET)%', turn_urls: '%env(TURN_URLS)%' }
 *             group:
 *                 factory: jitsi
 *                 options: { domain: meet.example.org, app_id: '%env(JITSI_APP_ID)%', app_secret: '%env(JITSI_APP_SECRET)%' }
 *
 *     public function __construct(GatewayInterface $direct, GatewayInterface $group) {}
 *
 * Nothing is built, nor checked, when the container compiles: a gateway is
 * built the first time it is asked for, and an option left empty only shows
 * then (InvalidConfigException).
 *
 * A factory is autowired: what its constructor asks for - omnimeet/direct's
 * SignalStoreInterface, an HTTP client - is the application's service of
 * that type, when it has one. An application's own factories (a
 * GatewayFactoryInterface) are registered too, autoconfigured.
 *
 * Beside a gateway's options, "policy" tightens its usage policy (never
 * looser) and "usage" says what the site is (commercial, open_source,
 * contract); "limiter" names the service (an omnisense/contracts
 * RateLimiterInterface) every gateway counts in, shared across processes:
 *
 *     omnimeet:
 *         limiter: app.omnimeet_limiter
 *         gateways:
 *             group:
 *                 factory: jitsi
 *                 options: { domain: 8x8.vc, ... }
 *                 usage: { commercial: true, contract: true }
 *
 * The gateways injected by name are the registry's get(): unguarded, as
 * before; Registry::guarded() gives one behind its policy.
 *
 * @suggested symfony/http-kernel (the Symfony bridge: loaded only in a Symfony application)
 */
final class OmnimeetBundle extends AbstractBundle
{
    protected string $extensionAlias = 'omnimeet';

    /** The gateway packages this bundle knows, registered when installed. */
    private const FACTORIES = [DirectGatewayFactory::class, JitsiGatewayFactory::class];

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('limiter')
                    ->info('The id of a service implementing Omnisense\\Contracts\\Policy\\RateLimiterInterface every gateway counts in; null: in memory.')
                    ->defaultNull()
                ->end()
                ->arrayNode('gateways')
                    ->info('The gateways, by name: a factory (direct, jitsi...) and its options.')
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('factory')->isRequired()->cannotBeEmpty()->end()
                            ->variableNode('options')->defaultValue([])->end()
                            ->variableNode('policy')->info('The site\'s tightening of the gateway\'s usage policy, in the schema\'s shape: never looser.')->end()
                            ->variableNode('usage')->info('What the site is: commercial, open_source, contract.')->end()
                        ->end()
                    ->end()
                ->end()
            ->end();
    }

    /** @param array{limiter?: ?string, gateways: array<string, array{factory: string, options: array<string, mixed>, policy?: array<string, mixed>, usage?: array<string, mixed>}>} $config */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(GatewayFactoryInterface::class)->addTag('omnimeet.gateway_factory');

        $services = $container->services();
        foreach (self::FACTORIES as $factory) {
            if (class_exists($factory)) {
                $services->set($factory)->autowire()->tag('omnimeet.gateway_factory');
            }
        }

        $services->set(Registry::class)
            ->args([tagged_iterator('omnimeet.gateway_factory'), $config['gateways'], null !== ($config['limiter'] ?? null) ? service($config['limiter']) : null])
            ->public();

        foreach (array_keys($config['gateways']) as $name) {
            $id = 'omnimeet.gateway.'.$name;
            $services->set($id, GatewayInterface::class)->factory([service(Registry::class), 'get'])->args([$name]);
            $builder->registerAliasForArgument($id, GatewayInterface::class, $name);
        }
    }
}
