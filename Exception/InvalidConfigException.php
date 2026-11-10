<?php

namespace Omnimeet\Exception;

/**
 * A gateway misconfigured: an option missing, an unknown factory, a meeting
 * not opened yet. It is omnisense/contracts' InvalidConfigException (an
 * \InvalidArgumentException, so a \LogicException as before).
 */
final class InvalidConfigException extends \Omnisense\Contracts\Exception\InvalidConfigException implements OmnimeetException
{
}
