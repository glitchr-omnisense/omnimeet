<?php

namespace Omnimeet\Tests;

use Omnimeet\Action\ActionInterface;
use Omnimeet\Config;
use Omnimeet\Exception\OmnimeetException;
use Omnimeet\Exception\ProviderException;
use Omnimeet\Exception\UsageLimitReachedException;
use Omnimeet\Gateway;
use Omnimeet\GatewayFactory;
use Omnimeet\Model\Access;
use Omnimeet\Model\AccessKind;
use Omnimeet\Model\Capabilities;
use Omnimeet\Model\Event;
use Omnimeet\Model\Meeting;
use Omnimeet\Model\Notification;
use Omnimeet\Model\Participant;
use Omnimeet\Model\Status;
use Omnimeet\Registry;
use Omnimeet\Request\Join;
use Omnimeet\Request\Notify;
use Omnimeet\Request\Open;
use Omnimeet\Request\Request;
use Omnisense\Contracts\Policy\InMemoryRateLimiter;
use Omnisense\Contracts\Policy\InvalidPolicyException;
use Omnisense\Contracts\Policy\UsageNotPermitted;
use Omnisense\Contracts\Policy\UsagePolicy;
use PHPUnit\Framework\TestCase;

/**
 * A gateway's usage policy: declared by its factory, tightened by the site,
 * enforced around every request but a callback's - and never a reason to
 * run a request twice. An open() refused, answered 429 or answered 5xx has
 * run its action once at most: nothing in omnimeet replays a room's creation.
 */
final class PolicyTest extends TestCase
{
    public function testASiteLimitRefusesTheOpenOverItBeforeItsActionRuns(): void
    {
        $calls = new Calls();
        $registry = self::registry($calls, ['policy' => ['rateLimits' => [['max' => 1, 'window' => 'day', 'operations' => ['open']]]]]);
        $gateway = $registry->guarded('rooms');

        self::assertTrue($gateway->open(new Meeting('first'))->isOpened());
        try {
            $gateway->open(new Meeting('second'));
            self::fail('the second open is over the limit');
        } catch (UsageLimitReachedException $e) {
            self::assertInstanceOf(OmnimeetException::class, $e);
            self::assertGreaterThan(0, $e->retryAfter);
        }
        self::assertSame(['open'], $calls->log, 'the open over the limit never ran');
        $gateway->join(new Meeting('first', reference: 'room-first'), Participant::guest('u2'));
        self::assertSame(['open', 'join'], $calls->log, 'join is not counted by an open limit');
        self::assertTrue($registry->get('rooms')->open(new Meeting('third'))->isOpened(), 'get() gives the gateway unguarded, as before');
        self::assertSame(['open', 'join', 'open'], $calls->log);
    }

    public function testACreateAnswered429IsSentOnceAndHoldsTheNextOnes(): void
    {
        $calls = new Calls(fail: 429);
        $gateway = self::registry($calls)->guarded('rooms');

        try {
            $gateway->open(new Meeting('k'));
            self::fail('the provider answered 429');
        } catch (UsageLimitReachedException $e) {
            self::assertSame(30, $e->retryAfter);
        }
        self::assertSame(['open'], $calls->log, 'sent once: no retry, no replay');

        try {
            $gateway->open(new Meeting('k'));
            self::fail('the provider\'s wait is not over');
        } catch (UsageLimitReachedException $e) {
            self::assertGreaterThan(0, $e->retryAfter);
        }
        self::assertSame(['open'], $calls->log, 'held by the wait: not sent at all');
    }

    public function testACreateAnsweredWithAServerErrorIsSentOnce(): void
    {
        $calls = new Calls(fail: 503);
        $gateway = self::registry($calls)->guarded('rooms');

        try {
            $gateway->open(new Meeting('k'));
            self::fail('the provider answered 503');
        } catch (ProviderException $e) {
            self::assertSame('503', $e->providerCode);
        }
        self::assertSame(['open'], $calls->log, 'sent once: the caller decides whether to ask again');
    }

    public function testACallbackIsNeverRefusedByALimit(): void
    {
        $calls = new Calls();
        $gateway = self::registry($calls, ['policy' => ['rateLimits' => [['max' => 0, 'window' => 'day']]]])->guarded('rooms');

        self::assertSame(Event::OPENED, $gateway->notify('{}')->event);
        $this->expectException(UsageLimitReachedException::class);
        $gateway->open(new Meeting('k'));
    }

    public function testThePolicyIsTheFactorysTightenedByTheSiteNeverLooser(): void
    {
        $registry = self::registry(new Calls(), ['policy' => ['rateLimits' => [['max' => 5, 'window' => 'day', 'operations' => ['open']]]]]);

        $declared = $registry->get('rooms')->capabilities()->policy();
        self::assertSame('stub-policy', $declared->provider);
        self::assertSame(['open', 'join', 'notify'], $registry->get('rooms')->capabilities()->contracts());
        self::assertCount(2, $registry->policy('rooms')->limitsFor('open'), 'the declared limit and the site\'s');
        self::assertSame(['rooms'], array_keys($registry->policies()));
        self::assertSame($declared->toArray(), $registry->get('rooms')->capabilities()->toArray()['policy']);

        $this->expectException(InvalidPolicyException::class);
        self::registry(new Calls(), ['policy' => ['rateLimits' => [['max' => 20, 'window' => 'day', 'operations' => ['open']]]]])->policy('rooms');
    }

    public function testACommercialSiteWithoutAContractIsRefusedAtOnce(): void
    {
        $this->expectException(UsageNotPermitted::class);
        self::registry(new Calls(), ['usage' => ['commercial' => true]])->guarded('rooms');
    }

    public function testAGatewayWithoutAPolicyHasAnUnknownOne(): void
    {
        $gateway = (new StubFactory())->create(['host' => 'meet.example.org']);

        self::assertInstanceOf(Gateway::class, $gateway);
        self::assertNull($gateway->usagePolicy()->rateLimits, 'unknown: nobody declared any');
        self::assertSame('stub', $gateway->capabilities()->policy()->provider);
    }

    /** @param array{policy?: array<string, mixed>, usage?: array<string, mixed>} $entry */
    private static function registry(Calls $calls, array $entry = []): Registry
    {
        return new Registry([new CountingFactory()], ['rooms' => ['factory' => 'counting', 'options' => ['calls' => $calls], ...$entry]], new InMemoryRateLimiter());
    }
}

/** What a provider was asked, and how it answers an open (fail: 429, 503). */
final class Calls
{
    /** @var list<string> */
    public array $log = [];

    public function __construct(public readonly ?int $fail = null)
    {
    }
}

/**
 * A gateway whose open() would create a room at a provider: each call
 * logged, answered as the provider would (a 429 through the Backoff an HTTP
 * client feeds, a 503 as a ProviderException). Its declared policy: 10
 * opens a day, commercial use under contract.
 */
final class CountingFactory extends GatewayFactory
{
    protected function populateConfig(Config $config): void
    {
        $config->defaults([
            'omnimeet.factory_name' => 'counting',
            'omnimeet.factory_title' => 'Counting',
            'omnimeet.required_options' => ['calls'],
            'omnimeet.capabilities' => new Capabilities(maxParticipants: 10, access: [AccessKind::LINK]),
            'omnimeet.policy' => static fn (Config $c) => UsagePolicy::fromArray([
                'provider' => 'stub-policy',
                'rateLimits' => [['max' => 10, 'window' => 'day', 'operations' => ['open']]],
                'rights' => ['commercial' => 'contract'],
            ]),
            'omnimeet.action.counting' => static fn (Config $c) => new CountingAction($c),
        ]);
    }
}

final class CountingAction implements ActionInterface
{
    public function __construct(private readonly Config $config)
    {
    }

    public function supports(Request $request): bool
    {
        return $request instanceof Open || $request instanceof Join || $request instanceof Notify;
    }

    public function execute(Request $request): void
    {
        $calls = $this->config['calls'];
        \assert($calls instanceof Calls);
        if ($request instanceof Notify) {
            $request->setResult(new Notification(Event::OPENED, 'opened'));

            return;
        }
        if ($request instanceof Join) {
            $calls->log[] = 'join';
            $request->setResult(Access::link('https://meet.example.org/'.$request->meeting->reference()));

            return;
        }
        \assert($request instanceof Open);
        $calls->log[] = 'open';
        if (429 === $calls->fail) {
            // What omnisense/contracts' JsonClient does with the answer: the Backoff reads it, and throws.
            $this->config->backoff()?->observe(429, ['retry-after' => ['30']]);
        }
        if (503 === $calls->fail) {
            throw new ProviderException('counting', 'Service Unavailable', '503');
        }
        $request->setResult($request->meeting->with('room-'.$request->meeting->key, Status::OPEN));
    }
}
