<?php

namespace Omnimeet\Exception;

use Omnisense\Contracts\Policy\UsageLimitReached;

/**
 * A request refused before the gateway's action ran: a rate limit of its
 * usage policy is reached (as the site runs it), or the provider asked to
 * wait and the wait is not over. Nothing was opened, nobody was let in: the
 * same request may be made again after $retryAfter seconds - by the caller,
 * never by omnimeet, which retries nothing.
 */
final class UsageLimitReachedException extends UsageLimitReached implements OmnimeetException
{
}
