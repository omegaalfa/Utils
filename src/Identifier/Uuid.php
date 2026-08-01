<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Identifier;

use InvalidArgumentException;

final class Uuid
{
    private const int MAX_TIMESTAMP = 281_474_976_710_655;

    public static function v7(?int $timestampMilliseconds = null): string
    {
        $timestampMilliseconds ??= (int) floor(microtime(true) * 1_000);
        self::assertTimestamp($timestampMilliseconds);

        $high = intdiv($timestampMilliseconds, 4_294_967_296);
        $low = $timestampMilliseconds & 0xffffffff;
        $bytes = pack('nN', $high, $low) . random_bytes(10);

        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x70);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return substr($hex, 0, 8) . '-'
            . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-'
            . substr($hex, 20);
    }

    public static function isValid(string $uuid): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di',
            $uuid,
        ) === 1;
    }

    public static function timestamp(string $uuid): int
    {
        if (!self::isValid($uuid) || strtolower($uuid[14]) !== '7') {
            throw new InvalidArgumentException('Expected a valid UUID version 7.');
        }

        return (int) hexdec(substr(str_replace('-', '', $uuid), 0, 12));
    }

    private static function assertTimestamp(int $timestamp): void
    {
        if ($timestamp < 0 || $timestamp > self::MAX_TIMESTAMP) {
            throw new InvalidArgumentException('Timestamp must fit in 48 unsigned bits.');
        }
    }
}
