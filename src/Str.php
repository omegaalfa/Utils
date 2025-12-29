<?php

declare(strict_types=1);

namespace Omegaalfa\Utils;

class Str
{
    public static function contains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    public static function endsWith(string $haystack, string $needle): bool
    {
        return str_ends_with($haystack, $needle);
    }

    public static function camelCase(string $string): string
    {
        return lcfirst(self::studlyCase($string));
    }

    public static function studlyCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
    }

    public static function snakeCase(string $string): string
    {
        if (!ctype_lower($string)) {
            $string = preg_replace('/\s+/u', '', ucwords($string));
            $string = preg_replace('/(.)(?=[A-Z])/u', '$1_', $string);
        }
        return strtolower($string);
    }

    public static function kebabCase(string $string): string
    {
        return str_replace('_', '-', self::snakeCase($string));
    }

    public static function limit(string $string, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($string) <= $limit) {
            return $string;
        }
        return mb_substr($string, 0, $limit) . $end;
    }

    public static function random(int $length = 16): string
    {
        $bytes = random_bytes((int) ceil($length / 2));
        return substr(bin2hex($bytes), 0, $length);
    }

    public static function slug(string $string, string $separator = '-'): string
    {
        $string = preg_replace('/[^\p{L}\p{Nd}]+/u', $separator, $string);
        $string = preg_replace('/[' . preg_quote($separator) . ']+/', $separator, $string);
        $string = trim($string, $separator);
        return strtolower($string);
    }

    public static function upper(string $string): string
    {
        return mb_strtoupper($string);
    }

    public static function lower(string $string): string
    {
        return mb_strtolower($string);
    }

    public static function title(string $string): string
    {
        return mb_convert_case($string, MB_CASE_TITLE);
    }

    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }
        $position = strpos($subject, $search);
        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }
        return $subject;
    }

    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }
        $position = strrpos($subject, $search);
        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }
        return $subject;
    }

    public static function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }
        $result = strstr($subject, $search, true);
        return $result === false ? $subject : $result;
    }

    public static function after(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }
        $position = strpos($subject, $search);
        if ($position === false) {
            return $subject;
        }
        return substr($subject, $position + strlen($search));
    }
}
