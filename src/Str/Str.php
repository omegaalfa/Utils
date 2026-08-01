<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Str;

use InvalidArgumentException;
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

        $text = transliterator_transliterate(
            'Any-Latin; Latin-ASCII; Lower()',
            $text,
        );
        if ($text === false) {
            throw new RuntimeException('Unable to transliterate the supplied text.');
        }

        $slug = preg_replace('/[^a-z0-9]+/', $divider, $text);
        if ($slug === null) {
            throw new RuntimeException('Unable to build slug from the supplied text.');
        }

        return trim($slug, $divider);
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
}
