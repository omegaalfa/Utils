<?php

declare(strict_types=1);

namespace Omegaalfa\Utils;

class Arr
{
    public static function get(array $array, string|int $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        if (!str_contains((string) $key, '.')) {
            return $default;
        }

        foreach (explode('.', (string) $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    public static function set(array &$array, string|int $key, mixed $value): void
    {
        if (!str_contains((string) $key, '.')) {
            $array[$key] = $value;
            return;
        }

        $keys = explode('.', (string) $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    public static function has(array $array, string|int $key): bool
    {
        if (array_key_exists($key, $array)) {
            return true;
        }

        if (!str_contains((string) $key, '.')) {
            return false;
        }

        foreach (explode('.', (string) $key) as $segment) {
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return false;
            }
        }

        return true;
    }

    public static function forget(array &$array, string|int $key): void
    {
        if (array_key_exists($key, $array)) {
            unset($array[$key]);
            return;
        }

        if (!str_contains((string) $key, '.')) {
            return;
        }

        $keys = explode('.', (string) $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                unset($current[$segment]);
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    return;
                }
                $current = &$current[$segment];
            }
        }
    }

    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }

    public static function first(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            if (empty($array)) {
                return $default;
            }
            foreach ($array as $item) {
                return $item;
            }
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            return empty($array) ? $default : end($array);
        }

        return self::first(array_reverse($array, true), $callback, $default);
    }

    public static function flatten(array $array, int $depth = PHP_INT_MAX): array
    {
        $result = [];

        foreach ($array as $item) {
            if (!is_array($item)) {
                $result[] = $item;
            } elseif ($depth === 1) {
                $result = array_merge($result, array_values($item));
            } else {
                $result = array_merge($result, self::flatten($item, $depth - 1));
            }
        }

        return $result;
    }

    public static function pluck(array $array, string $value, ?string $key = null): array
    {
        $results = [];

        foreach ($array as $item) {
            $itemValue = self::get((array) $item, $value);

            if ($key === null) {
                $results[] = $itemValue;
            } else {
                $itemKey = self::get((array) $item, $key);
                $results[$itemKey] = $itemValue;
            }
        }

        return $results;
    }

    public static function where(array $array, callable $callback): array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    public static function shuffle(array $array): array
    {
        shuffle($array);
        return $array;
    }
}
