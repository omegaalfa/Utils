<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Str;

use InvalidArgumentException;
use Omegaalfa\Utils\Helpers\LatinText;
use RuntimeException;

final class Str
{
    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function contains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return str_ends_with($haystack, $needle);
    }

    /**
     * @param string $text
     * @param string $divider
     * @return string
     */
    public static function slugify(string $text, string $divider = '-'): string
    {
        if (strlen($divider) !== 1) {
            throw new InvalidArgumentException('Slug divider must contain exactly one ASCII character.');
        }
        if (str_contains('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', $divider)) {
            throw new InvalidArgumentException('Slug divider cannot be alphanumeric.');
        }

        $text = LatinText::transliterate($text);

        $slug = preg_replace('/[^A-Za-z0-9]+/', $divider, $text);
        if ($slug === null) {
            throw new RuntimeException('Unable to build slug from the supplied text.');
        }

        return trim(strtolower($slug), $divider);
    }

    /**
     * @param string $text
     * @param int $length
     * @param string $append
     * @return string
     */
    public static function truncate(
        string $text,
        int    $length,
        string $append = '...',
    ): string
    {
        if ($length < 0) {
            throw new InvalidArgumentException('Truncation length cannot be negative.');
        }
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length, 'UTF-8') . $append;
    }

    /**
     * @param int $length
     * @return string
     * @throws \Random\RandomException
     */
    public static function random(int $length = 16): string
    {
        if ($length < 0) {
            throw new InvalidArgumentException('Random string length cannot be negative.');
        }
        if ($length === 0) {
            return '';
        }

        /** @var positive-int $bytes */
        $bytes = intdiv($length, 2) + ($length % 2);

        return substr(bin2hex(random_bytes($bytes)), 0, $length);
    }

    /**
     * @param string $string
     * @param string $character
     * @param int $index
     * @param int|null $length
     * @return string
     */
    public static function mask(
        string $string,
        string $character,
        int    $index,
        ?int   $length = null,
    ): string
    {
        if (strlen($character) !== 1) {
            throw new InvalidArgumentException('Mask character must contain exactly one byte.');
        }
        if ($length !== null && $length < 0) {
            throw new InvalidArgumentException('Mask length cannot be negative.');
        }

        $stringLength = strlen($string);
        $offset = $index >= 0
            ? min($index, $stringLength)
            : max($stringLength + $index, 0);
        $available = $stringLength - $offset;
        $maskLength = $length === null
            ? $available
            : min($length, $available);

        if ($maskLength === 0) {
            return $string;
        }

        return substr_replace(
            $string,
            str_repeat($character, $maskLength),
            $offset,
            $maskLength,
        );
    }

    /**
     * Masks a range of UTF-8 characters without splitting multibyte sequences.
     *
     * Offsets and lengths are counted in characters (not bytes). A negative
     * index counts from the end of the string, matching {@see self::mask()}.
     */
    public static function maskUtf8(
        string $string,
        string $character,
        int    $index,
        ?int   $length = null,
    ): string {
        if (!mb_check_encoding($string, 'UTF-8')) {
            throw new InvalidArgumentException('Mask input must be valid UTF-8.');
        }
        if (mb_strlen($character, 'UTF-8') !== 1) {
            throw new InvalidArgumentException('Mask character must contain exactly one UTF-8 character.');
        }
        if ($length !== null && $length < 0) {
            throw new InvalidArgumentException('Mask length cannot be negative.');
        }

        $stringLength = mb_strlen($string, 'UTF-8');
        $offset = $index >= 0
            ? min($index, $stringLength)
            : max($stringLength + $index, 0);
        $available = $stringLength - $offset;
        $maskLength = $length === null
            ? $available
            : min($length, $available);

        if ($maskLength === 0) {
            return $string;
        }

        return mb_substr($string, 0, $offset, 'UTF-8')
            . str_repeat($character, $maskLength)
            . mb_substr($string, $offset + $maskLength, null, 'UTF-8');
    }
}
