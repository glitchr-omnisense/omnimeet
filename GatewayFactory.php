<?php

namespace Omnimeet;

use Omnimeet\Action\ActionInterface;
use Omnimeet\Action\ApiAwareInterface;
use Omnimeet\Exception\InvalidConfigException;
use Omnimeet\Exception\UsageLimitReachedException;
use Omnimeet\Model\Capabilities;
use Omnisense\Contracts\Http\Redaction;
use Omnisense\Contracts\Policy\UsagePolicy;
use Omnisense\Contracts\Provider\Config as BaseConfig;
use Omnisense\Contracts\Provider\ProviderFactory;

/**
 * The Omnibus way: a gateway's factory fills a Config - its name, title,
 * capabilities, the options it needs, its client ("omnimeet.api", a closure
 * of the Config: a provider's API, a token signer, a store), its actions
 * ("omnimeet.action.<name>") and its usage policy ("omnimeet.policy": the
 * path of its policy.json, a UsagePolicy, or a closure of the Config giving
 * one) - and the gateway is those actions, the client handed to the ones
 * that ask for it.
 *
 * It is omnisense/contracts' ProviderFactory: before the client is built,
 * the Config holds the provider's Backoff (Config::backoff(), over the
 * registry's limiter); guard() gives the gateway's PolicyGuard, its declared
 * policy tightened by the "policy" option and checked against "usage"
 * (Registry::guarded() puts the gateway behind it).
 *
 * Nothing here calls anything: a factory whose provider is reached over
 * HTTP takes its client in its own constructor.
 */
abstract class GatewayFactory extends ProviderFactory implements GatewayFactoryInterface
{
    public function create(array $options = []): GatewayInterface
    {
        try {
            $gateway = parent::create($options);
        } catch (\Throwable $e) {
            // The options hold the secrets (a relay's, an instance's): no trace of the failure keeps them in its arguments.
            throw Redaction::withoutArguments($e);
        }

        return $gateway instanceof GatewayInterface ? $gateway : throw new \LogicException(static::class.' built no gateway.');
    }

    public function createConfig(array $options = []): Config
    {
        $config = parent::createConfig($options);

        return $config instanceof Config ? $config : throw new \LogicException('An omnimeet factory fills an Omnimeet\Config.');
    }

    abstract protected function populateConfig(Config $config): void;

    protected function newConfig(array $options): Config
    {
        return new Config($options);
    }

    protected function configure(BaseConfig $c): void
    {
        \assert($c instanceof Config);
        $this->populateConfig($c);
    }

    protected function make(BaseConfig $c): GatewayInterface
    {
        \assert($c instanceof Config);
        $name = $c->string('omnimeet.factory_name') ?? static::class;

        $api = $c->get('omnimeet.api');
        if ($api instanceof \Closure) {
            $api = $api($c);
        }
        $capabilities = $c->get('omnimeet.capabilities');
        if ($capabilities instanceof \Closure) {
            $capabilities = $capabilities($c);
        }
        if (!$capabilities instanceof Capabilities) {
            throw new InvalidConfigException(\sprintf('The "%s" factory does not say what its meetings are (omnimeet.capabilities).', $name));
        }

        $actions = [];
        foreach ($c as $key => $action) {
            if (!str_starts_with((string) $key, 'omnimeet.action.')) {
                continue;
            }
            if ($action instanceof \Closure) {
                $action = $action($c);
            }
            if (!$action instanceof ActionInterface) {
                continue;
            }
            if ($action instanceof ApiAwareInterface && \is_object($api)) {
                $action->setApi($api);
            }
            $actions[] = $action;
        }

        $policy = $c->get('omnimeet.policy');
        if ($policy instanceof \Closure) {
            $policy = $policy($c);
        }

        return new Gateway($name, $c->string('omnimeet.factory_title') ?? $name, $capabilities, $actions, self::policy($policy, $name));
    }

    protected function limitException(): string
    {
        return UsageLimitReachedException::class;
    }

    /** The provider's declared policy: a UsagePolicy, the path of a policy.json, or unknown. */
    private static function policy(mixed $policy, string $name): UsagePolicy
    {
        return match (true) {
            $policy instanceof UsagePolicy => $policy,
            \is_string($policy) && '' !== $policy => UsagePolicy::fromFile($policy),
            default => UsagePolicy::unknown($name),
        };
    }
}
