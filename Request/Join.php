<?php

namespace Omnimeet\Request;

use Omnimeet\Model\Access;
use Omnimeet\Model\Meeting;
use Omnimeet\Model\Participant;

/**
 * Let one participant into an opened meeting. Result: the Access - an
 * engine and its options, a frame, or an address - made for that
 * participant, now. Whether they may enter at all was decided before, by
 * the application.
 */
final class Join extends Request
{
    public function __construct(public readonly Meeting $meeting, public readonly Participant $participant)
    {
    }

    /** The answer, when it is one: null for none or for anything else. */
    public function getAccess(): ?Access
    {
        $result = $this->getResult();

        return $result instanceof Access ? $result : null;
    }
}
