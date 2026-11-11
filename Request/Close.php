<?php

namespace Omnimeet\Request;

use Omnimeet\Model\Meeting;

/**
 * Close a meeting: nobody gets in any more, what the gateway kept of it
 * goes. Result: the Meeting, ENDED.
 */
final class Close extends Request
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
