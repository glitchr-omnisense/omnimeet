<?php

namespace Omnimeet\Request;

use Omnimeet\Model\Meeting;

/**
 * The meeting as the gateway has it now. Result: the Meeting with its
 * status and who is in.
 */
final class Fetch extends Request
{
    public function __construct(public readonly Meeting $meeting)
    {
    }

    /** The answer, when it is one: null for none or for anything else. */
    public function getMeeting(): ?Meeting
    {
        $result = $this->getResult();

        return $result instanceof Meeting ? $result : null;
    }
}
