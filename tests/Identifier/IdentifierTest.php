<?php

declare(strict_types=1);

namespace Tests\Identifier;

use InvalidArgumentException;
use Omegaalfa\Utils\Identifier\Ulid;
use Omegaalfa\Utils\Identifier\Uuid;
use PHPUnit\Framework\TestCase;

final class IdentifierTest extends TestCase
{
    public function testGeneratesValidUuidVersionSeven(): void
    {
        $uuid = Uuid::v7();

        self::assertTrue(Uuid::isValid($uuid));
        self::assertSame('7', $uuid[14]);
        self::assertContains($uuid[19], ['8', '9', 'a', 'b']);
        self::assertSame(36, strlen($uuid));
    }

    public function testUuidContainsProvidedTimestampAndSortsByTime(): void
    {
        $first = Uuid::v7(1_700_000_000_000);
        $second = Uuid::v7(1_700_000_000_001);

        self::assertSame(1_700_000_000_000, Uuid::timestamp($first));
        self::assertLessThan(0, strcmp($first, $second));
    }

    public function testUuidRandomComponentsProduceUniqueValues(): void
    {
        self::assertNotSame(
            Uuid::v7(1_700_000_000_000),
            Uuid::v7(1_700_000_000_000),
        );
    }

    public function testUuidRejectsInvalidInputAndTimestamp(): void
    {
        self::assertFalse(Uuid::isValid('not-a-uuid'));

        try {
            Uuid::v7(-1);
            self::fail('Negative timestamp should be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        Uuid::timestamp('550e8400-e29b-41d4-a716-446655440000');
    }

    public function testGeneratesCanonicalUlid(): void
    {
        $ulid = Ulid::generate();

        self::assertTrue(Ulid::isValid($ulid));
        self::assertSame(26, strlen($ulid));
        self::assertSame(strtoupper($ulid), $ulid);
    }

    public function testUlidContainsProvidedTimestampAndSortsByTime(): void
    {
        $first = Ulid::generate(1_700_000_000_000);
        $second = Ulid::generate(1_700_000_000_001);

        self::assertSame(1_700_000_000_000, Ulid::timestamp($first));
        self::assertLessThan(0, strcmp($first, $second));
    }

    public function testUlidRandomComponentsProduceUniqueValues(): void
    {
        self::assertNotSame(
            Ulid::generate(1_700_000_000_000),
            Ulid::generate(1_700_000_000_000),
        );
    }

    public function testUlidRejectsAmbiguousAndInvalidValues(): void
    {
        self::assertFalse(Ulid::isValid('01ARZ3NDEKTSV4RRFFQ69G5FAI'));
        self::assertFalse(Ulid::isValid(strtolower(Ulid::generate())));

        try {
            Ulid::generate(281_474_976_710_656);
            self::fail('Oversized timestamp should be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        Ulid::timestamp('invalid');
    }
}
