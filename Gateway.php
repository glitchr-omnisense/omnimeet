<?php

namespace Omnimeet;

use Omnimeet\Action\ActionInterface;
use Omnimeet\Exception\NotSupportedException;
use Omnimeet\Exception\ProviderException;
use Omnimeet\Model\Access;
use Omnimeet\Model\Capabilities;
use Omnimeet\Model\Meeting;
use Omnimeet\Model\Notification;
use Omnimeet\Model\Participant;
use Omnisense\Contracts\Policy\DeclaresUsagePolicyInterface;
use Omnisense\Contracts\Policy\PolicyGuard;
use Omnisense\Contracts\Policy\UsagePolicy;

/**
 * A gateway's actions behind one door: the first action supporting a request
 * answers it. Its usage policy is the provider's, as its factory declares it
 * ("omnimeet.policy"); behind a guard (Registry::guarded()) every request but
 * a callback's is checked against the limits that count its operation before
 * its action runs (UsageLimitReachedException, nothing done), counted after.
 *
 * Nothing here runs an action twice: a request refused, failed or answered
 * by an error is given back to the caller as it is - an open() is never
 * replayed.
 */
final class Gateway implements GatewayInterface, DeclaresUsagePolicyInterface
{
    /** Each request's operation: GatewayInterface's method name, the policy's "operations". */
    public const OPERATIONS = [
        Request\Open::class => 'open',
        Request\Join::class => 'join',
        Request\Close::class => 'close',
        Request\Fetch::class => 'fetch',
        Request\Notify::class => 'notify',
    ];

    private ?Capabilities $described = null;

    /**
     * @param ActionInterface[] $actions
     * @param ?UsagePolicy      $policy  the provider's, as it declares it (unknown when not given)
     * @param ?PolicyGuard      $guard   the policy every request but a callback's goes through (none: unguarded)
     */
    public function __construct(
        private readonly string $name,
        private readonly string $title,
        private readonly Capabilities $capabilities,
        private readonly array $actions,
        private readonly ?UsagePolicy $policy = null,
        private readonly ?PolicyGuard $guard = null,
    ) {
    }

    /** The same gateway, every request but a callback's behind this guard. */
    public function guardedBy(PolicyGuard $guard): self
    {
        return new self($this->name, $this->title, $this->capabilities, $this->actions, $this->policy, $guard);
    }

    /** The operation a request counts as: "open", "join"... (its short name, lower-cased first, for another one). */
    public static function operation(Request\Request $request): string
    {
        return self::OPERATIONS[$request::class] ?? lcfirst((new \ReflectionClass($request))->getShortName());
    }

    public function usagePolicy(): UsagePolicy
    {
        return $this->policy ?? UsagePolicy::unknown($this->name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    /** What its meetings are, with the operations it answers and its usage policy. */
    public function capabilities(): Capabilities
    {
        if (null === $this->described) {
            $operations = [];
            foreach (self::OPERATIONS as $request => $operation) {
                if ($this->supports($request)) {
                    $operations[] = $operation;
                }
            }
            $this->described = $this->capabilities->describing($operations, $this->usagePolicy());
        }

        return $this->described;
    }

    public function supports(string $request): bool
    {
        $probe = (new \ReflectionClass($request))->newInstanceWithoutConstructor();
        foreach ($this->actions as $action) {
            if ($action->supports($probe)) {
                return true;
            }
        }

        return false;
    }

    public function execute(Request\Request $request): Request\Request
    {
        if (null === $this->guard || $request instanceof Request\Notify) {
            // A callback is the provider's own word: never refused by a limit.
            return $this->answer($request);
        }

        return $this->guard->call(self::operation($request), fn () => $this->answer($request));
    }

    public function open(Meeting $meeting): Meeting
    {
        return $this->execute(new Request\Open($meeting))->getMeeting();
    }

    public function join(Meeting $meeting, Participant $participant): Access
    {
        return $this->execute(new Request\Join($meeting, $participant))->getAccess();
    }

    public function close(Meeting $meeting): Meeting
    {
        return $this->execute(new Request\Close($meeting))->getMeeting();
    }

    public function fetch(Meeting $meeting): Meeting
    {
        return $this->execute(new Request\Fetch($meeting))->getMeeting();
    }

    public function notify(string $body, array $headers = []): Notification
    {
        return $this->execute(new Request\Notify($body, $headers))->getNotification();
    }

    /**
     * @template T of Request\Request
     *
     * @param T $request
     *
     * @return T
     */
    private function answer(Request\Request $request): Request\Request
    {
        foreach ($this->actions as $action) {
            if ($action->supports($request)) {
                $action->execute($request);
                if (!$request->isAnswered()) {
                    throw new ProviderException($this->name, \sprintf('%s left the request unanswered.', $action::class));
                }

                return $request;
            }
        }

        throw NotSupportedException::for($request, $this->name);
    }
}
