<?php

namespace Omnimeet\Request;

use Omnimeet\Model\Meeting;

/**
 * Open a meeting on the gateway. Result: the Meeting with its reference
 * there - what the application keeps to let people in, close and fetch.
 * Opening the same meeting (the same key) twice gives the same reference.
 */
final class Open extends Request
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
