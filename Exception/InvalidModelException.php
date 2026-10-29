<?php

namespace Omnimeet\Exception;

/**
 * A model built with what it cannot hold: a meeting without a key or
 * ending before it starts, a participant without an identifier, an access
 * without its script or its address. An \InvalidArgumentException, as the
 * models threw before.
 */
final class InvalidModelException extends \InvalidArgumentException implements OmnimeetException
{
}
