<?php

declare(strict_types=1);

namespace Tests\Hasher;

use InvalidArgumentException;
use Omegaalfa\Utils\Hasher\Hasher;
use PHPUnit\Framework\TestCase;

final class HasherTest extends TestCase
{
    public function testHashesAndVerifiesPasswords(): void
    {
        $hash = Hasher::passwordHash('correct horse');

        self::assertNotSame('correct horse', $hash);
        self::assertTrue(Hasher::passwordVerify('correct horse', $hash));
        self::assertFalse(Hasher::passwordVerify('wrong horse', $hash));
        self::assertFalse(Hasher::passwordNeedsRehash($hash));
    }

    public function testPasswordNeedsRehashDetectsParameterChanges(): void
    {
        $hash = Hasher::passwordHash('secret', PASSWORD_DEFAULT, ['cost' => 4]);

        self::assertFalse(Hasher::passwordNeedsRehash($hash, PASSWORD_DEFAULT, ['cost' => 4]));
        self::assertTrue(Hasher::passwordNeedsRehash($hash, PASSWORD_DEFAULT, ['cost' => 5]));
    }

    public function testPasswordHashRejectsUnsupportedAlgorithm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Hasher::passwordHash('secret', 'invalid-algo');
    }

    public function testProducesAndVerifiesHmacSignatures(): void
    {
        $signature = Hasher::hmac('payload', 'key');

        self::assertSame($signature, Hasher::hmac('payload', 'key'));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $signature);
        self::assertTrue(Hasher::hmacVerify('payload', 'key', $signature));
        self::assertFalse(Hasher::hmacVerify('tampered', 'key', $signature));
        self::assertFalse(Hasher::hmacVerify('payload', 'other', $signature));
        self::assertFalse(Hasher::hmacVerify('payload', 'key', 'deadbeef'));
    }

    public function testHmacSupportsRawOutputAndAlternateAlgorithms(): void
    {
        $raw = Hasher::hmac('payload', 'key', 'sha256', raw: true);

        self::assertSame(32, strlen($raw));
        self::assertTrue(Hasher::hmacVerify('payload', 'key', $raw, 'sha256', raw: true));

        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/', Hasher::hmac('payload', 'key', 'md5'));
    }

    public function testHmacRejectsEmptyKeyAndUnsupportedAlgorithm(): void
    {
        try {
            Hasher::hmac('payload', '');
            self::fail('An empty HMAC key must be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        Hasher::hmac('payload', 'key', 'not-a-hash');
    }

    public function testEqualsComparesStrings(): void
    {
        self::assertTrue(Hasher::equals('same', 'same'));
        self::assertFalse(Hasher::equals('same', 'diff'));
        self::assertFalse(Hasher::equals('abc', 'abd'));
    }
}
