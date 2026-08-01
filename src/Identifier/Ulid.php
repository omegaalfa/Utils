<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Identifier;

use InvalidArgumentException;

final class Ulid
{
    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const int MAX_TIMESTAMP = 281_474_976_710_655;

    public static function generate(?int $timestampMilliseconds = null): string
    {
        $timestampMilliseconds ??= (int) floor(microtime(true) * 1_000);
        self::assertTimestamp($timestampMilliseconds);

        return self::encodeTimestamp($timestampMilliseconds)
            . self::encodeRandomness(random_bytes(10));
    }

    public static function isValid(string $ulid): bool
    {
        return preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $ulid) === 1;
    }

    public static function timestamp(string $ulid): int
    {
        if (!self::isValid($ulid)) {
            throw new InvalidArgumentException('Expected a valid canonical ULID.');
        }

        $timestamp = 0;
        for ($index = 0; $index < 10; $index++) {
            $value = strpos(self::ALPHABET, $ulid[$index]);
            assert($value !== false);
            $timestamp = ($timestamp * 32) + $value;
        }

        return $timestamp;
    }

    private static function encodeTimestamp(int $timestamp): string
    {
        $encoded = array_fill(0, 10, '0');

        for ($index = 9; $index >= 0; $index--) {
            $encoded[$index] = self::ALPHABET[$timestamp & 31];
            $timestamp = intdiv($timestamp, 32);
        }

        return implode('', $encoded);
    }

    private static function encodeRandomness(string $bytes): string
    {
        $encoded = '';
        $buffer = 0;
        $bits = 0;

        for ($index = 0; $index < 10; $index++) {
            $buffer = ($buffer << 8) | ord($bytes[$index]);
            $bits += 8;

            while ($bits >= 5) {
                $bits -= 5;
                $encoded .= self::ALPHABET[($buffer >> $bits) & 31];
            }

            $buffer = $bits === 0 ? 0 : $buffer & ((1 << $bits) - 1);
        }

        return $encoded;
    }

    private static function assertTimestamp(int $timestamp): void
    {
        if ($timestamp < 0 || $timestamp > self::MAX_TIMESTAMP) {
            throw new InvalidArgumentException('Timestamp must fit in 48 unsigned bits.');
        }
    }
}
