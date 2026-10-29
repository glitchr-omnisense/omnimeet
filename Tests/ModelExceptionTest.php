<?php

namespace Omnimeet\Tests;

use Omnimeet\Exception\InvalidModelException;
use Omnimeet\Exception\OmnimeetException;
use Omnimeet\Model\Access;
use Omnimeet\Model\AccessKind;
use Omnimeet\Model\Meeting;
use Omnimeet\Model\Participant;
use PHPUnit\Framework\TestCase;

final class ModelExceptionTest extends TestCase
{
    /** Every model refusal is an OmnimeetException, and an \InvalidArgumentException as it was. */
    public function testAModelItCannotBuildIsAnOmnimeetException(): void
    {
        foreach ([
            'no key' => static fn () => new Meeting(''),
            'an end before the start' => static fn () => new Meeting('m', new \DateTimeImmutable('2026-10-29 10:00'), new \DateTimeImmutable('2026-10-29 09:00')),
            'no identifier' => static fn () => new Participant(''),
            'an engine without its script' => static fn () => new Access(AccessKind::ENGINE),
            'a link without an address' => static fn () => new Access(AccessKind::LINK),
        ] as $case => $build) {
            try {
                $build();
                self::fail($case.': refused.');
            } catch (OmnimeetException $e) {
                self::assertInstanceOf(InvalidModelException::class, $e, $case);
                self::assertInstanceOf(\InvalidArgumentException::class, $e, $case);
            }
        }
    }
}
