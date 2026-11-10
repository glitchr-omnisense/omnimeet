<?php

namespace Omnimeet\Model;

use Omnisense\Contracts\Policy\UsagePolicy;
use Omnisense\Contracts\Provider\CapabilitiesInterface;

/**
 * What a gateway's meetings are, said before one is opened: how many
 * people, through whom, with what. The operations it answers are asked of
 * the gateway itself (supports()); the gateway gives them here too
 * (contracts()), with its usage policy (policy()), as omnisense/contracts'
 * CapabilitiesInterface says.
 */
final readonly class Capabilities implements CapabilitiesInterface
{
    /**
     * @param int|null         $maxParticipants how many people one meeting takes; null: the provider's own limit, not known here
     * @param bool             $thirdParty      whether anyone but the participants and the application carries or sees the call
     * @param bool             $endToEnd        whether the media are encrypted from one participant to the other, with nobody in between able to read them
     * @param bool             $recording       whether the gateway can record a meeting
     * @param bool             $tokens          whether each participant enters with a credential made for them
     * @param list<AccessKind> $access          how it lets someone in
     * @param list<string>     $operations      the requests the gateway answers ("open", "join"...): Gateway fills it
     * @param UsagePolicy|null $policy          the provider's usage policy as it declares it: Gateway fills it (unknown when null)
     */
    public function __construct(
        public ?int $maxParticipants = null,
        public bool $thirdParty = true,
        public bool $endToEnd = false,
        public bool $recording = false,
        public bool $screenSharing = false,
        public bool $chat = false,
        public bool $tokens = false,
        public array $access = [],
        public array $operations = [],
        public ?UsagePolicy $policy = null,
    ) {
    }

    public function takes(int $participants): bool
    {
        return null === $this->maxParticipants || $participants <= $this->maxParticipants;
    }

    public function letsInBy(AccessKind $kind): bool
    {
        return \in_array($kind, $this->access, true);
    }

    /**
     * The same capabilities, with the operations a gateway answers and its policy.
     *
     * @param list<string> $operations
     */
    public function describing(array $operations, UsagePolicy $policy): self
    {
        return new self($this->maxParticipants, $this->thirdParty, $this->endToEnd, $this->recording, $this->screenSharing, $this->chat, $this->tokens, $this->access, $operations, $policy);
    }

    /** @return list<string> the requests the gateway answers */
    public function contracts(): array
    {
        return $this->operations;
    }

    public function policy(): UsagePolicy
    {
        return $this->policy ?? UsagePolicy::unknown(UsagePolicy::UNNAMED);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'access' => array_map(static fn (AccessKind $kind) => $kind->value, $this->access),
            'maxParticipants' => $this->maxParticipants,
            'thirdParty' => $this->thirdParty,
            'endToEnd' => $this->endToEnd,
            'recording' => $this->recording,
            'screenSharing' => $this->screenSharing,
            'chat' => $this->chat,
            'tokens' => $this->tokens,
            'operations' => $this->operations,
            'policy' => $this->policy?->toArray(),
        ];
    }
}
