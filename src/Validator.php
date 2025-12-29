<?php

declare(strict_types=1);

namespace Omegaalfa\Utils;

class Validator
{
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function url(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public static function ip(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public static function ipv4(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    public static function ipv6(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }

    public static function numeric(mixed $value): bool
    {
        return is_numeric($value);
    }

    public static function alpha(string $value): bool
    {
        return preg_match('/^[a-zA-Z]+$/', $value) === 1;
    }

    public static function alphaNumeric(string $value): bool
    {
        return preg_match('/^[a-zA-Z0-9]+$/', $value) === 1;
    }

    public static function alphaDash(string $value): bool
    {
        return preg_match('/^[a-zA-Z0-9_-]+$/', $value) === 1;
    }

    public static function length(string $value, int $min, ?int $max = null): bool
    {
        $length = mb_strlen($value);
        if ($max === null) {
            return $length >= $min;
        }
        return $length >= $min && $length <= $max;
    }

    public static function min(mixed $value, int|float $min): bool
    {
        if (is_numeric($value)) {
            return $value >= $min;
        }
        if (is_string($value)) {
            return mb_strlen($value) >= $min;
        }
        if (is_array($value)) {
            return count($value) >= $min;
        }
        return false;
    }

    public static function max(mixed $value, int|float $max): bool
    {
        if (is_numeric($value)) {
            return $value <= $max;
        }
        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }
        if (is_array($value)) {
            return count($value) <= $max;
        }
        return false;
    }

    public static function between(mixed $value, int|float $min, int|float $max): bool
    {
        return self::min($value, $min) && self::max($value, $max);
    }

    public static function in(mixed $value, array $values): bool
    {
        return in_array($value, $values, true);
    }

    public static function notIn(mixed $value, array $values): bool
    {
        return !self::in($value, $values);
    }

    public static function regex(string $value, string $pattern): bool
    {
        return preg_match($pattern, $value) === 1;
    }

    public static function json(string $value): bool
    {
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    public static function date(string $value, ?string $format = null): bool
    {
        if ($format === null) {
            return strtotime($value) !== false;
        }

        $dateTime = \DateTime::createFromFormat($format, $value);
        return $dateTime && $dateTime->format($format) === $value;
    }
}
