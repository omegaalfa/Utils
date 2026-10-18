<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Arr;

use ArrayAccess;
use InvalidArgumentException;

/**
 * Immutable array helpers with dot-notation support.
 *
 * All read operations accept plain arrays, {@see ArrayAccess} objects and
 * objects with public properties. All write operations return a new array and
 * never mutate the original.
 */
final class Arr
{
    private function __construct()
    {
    }

    /**
     * Returns the value at a dot-notation path, or $default when the path is absent.
     *
     * @param array<array-key, mixed> $array
     */
    public static function get(array $array, string|int $key, mixed $default = null): mixed
    {
        [$found, $value] = self::lookup($array, $key);

        return $found ? $value : $default;
    }

    /**
     * Reports whether a dot-notation path exists, including paths whose value is null.
     *
     * @param array<array-key, mixed> $array
     */
    public static function has(array $array, string|int $key): bool
    {
        [$found] = self::lookup($array, $key);

        return $found;
    }

    /**
     * Returns a copy with $value stored at the given path, creating nested arrays as needed.
     *
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    public static function set(array $array, string|int $key, mixed $value): array
    {
        return self::writeValue($array, self::segments($key), $value);
    }

    /**
     * Returns a copy without the given dot-notation paths; missing paths are ignored.
     *
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    public static function forget(array $array, string|int ...$keys): array
    {
        foreach ($keys as $key) {
            $array = self::removePath($array, self::segments($key));
        }

        return $array;
    }

    /**
     * Extracts one value per element, keyed like the original list.
     *
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    public static function pluck(array $array, string|int $key, mixed $default = null): array
    {
        $result = [];

        foreach ($array as $index => $item) {
            [$found, $value] = self::lookup($item, $key);
            $result[$index] = $found ? $value : $default;
        }

        return $result;
    }

    /**
     * Groups elements by a dot-notation path or a callback returning a string or integer key.
     *
     * @param array<array-key, mixed> $array
     * @param string|int|callable(mixed, array-key): mixed $by
     *
     * @return array<string|int, array<array-key, mixed>>
     *
     * @throws InvalidArgumentException When the path is missing for an element or the callback returns an invalid key.
     */
    public static function groupBy(array $array, string|int|callable $by): array
    {
        $groups = [];

        foreach ($array as $index => $item) {
            if (is_callable($by)) {
                $groupKey = $by($item, $index);
                if (!is_string($groupKey) && !is_int($groupKey)) {
                    throw new InvalidArgumentException('Group keys must be strings or integers.');
                }
            } else {
                [$found, $value] = self::lookup($item, $by);
                if (!$found || (!is_string($value) && !is_int($value))) {
                    throw new InvalidArgumentException("Unable to group by missing or non-scalar path: {$by}");
                }
                $groupKey = $value;
            }

            $groups[$groupKey][] = $item;
        }

        return $groups;
    }

    /**
     * Returns a copy containing only the given keys, preserving nested structure.
     *
     * @param array<array-key, mixed> $array
     * @param list<string|int> $keys
     *
     * @return array<array-key, mixed>
     */
    public static function only(array $array, array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            [$found, $value] = self::lookup($array, $key);
            if ($found) {
                $result = self::writeValue($result, self::segments($key), $value);
            }
        }

        return $result;
    }

    /**
     * Flattens nested arrays into dot-notation keys; empty arrays are kept as leaves.
     *
     * @param array<array-key, mixed> $array
     *
     * @return array<string, mixed>
     */
    public static function dot(array $array): array
    {
        return self::flatten($array, '');
    }

    /**
     * Expands dot-notation keys back into nested arrays.
     *
     * @param array<array-key, mixed> $array
     *
     * @return array<array-key, mixed>
     */
    public static function undot(array $array): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $result = self::writeValue($result, self::segments((string) $key), $value);
        }

        return $result;
    }

    /**
     * Returns the first element (or first match), or $default when none exists.
     *
     * @param array<array-key, mixed> $array
     * @param null|callable(mixed, array-key): bool $callback
     */
    public static function first(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        foreach ($array as $key => $value) {
            if ($callback === null || $callback($value, $key)) {
                return $value;
            }
        }

        return $default;
    }

    /**
     * Returns the last element (or last match), or $default when none exists.
     *
     * @param array<array-key, mixed> $array
     * @param null|callable(mixed, array-key): bool $callback
     */
    public static function last(array $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if ($callback === null) {
            $key = array_key_last($array);

            return $key === null ? $default : $array[$key];
        }

        $found = false;
        $result = null;

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                $found = true;
                $result = $value;
            }
        }

        return $found ? $result : $default;
    }

    /**
     * @return array{bool, mixed}
     */
    private static function lookup(mixed $target, string|int $key): array
    {
        foreach (self::segments($key) as $segment) {
            [$found, $target] = self::read($target, $segment);
            if (!$found) {
                return [false, null];
            }
        }

        return [true, $target];
    }

    /**
     * @return array{bool, mixed}
     */
    private static function read(mixed $target, string $segment): array
    {
        if (is_array($target)) {
            return array_key_exists($segment, $target)
                ? [true, $target[$segment]]
                : [false, null];
        }

        if ($target instanceof ArrayAccess) {
            return $target->offsetExists($segment)
                ? [true, $target->offsetGet($segment)]
                : [false, null];
        }

        if (is_object($target)) {
            $properties = get_object_vars($target);

            return array_key_exists($segment, $properties)
                ? [true, $properties[$segment]]
                : [false, null];
        }

        return [false, null];
    }

    /**
     * @param array<array-key, mixed> $array
     * @param list<string> $segments
     *
     * @return array<array-key, mixed>
     */
    private static function writeValue(array $array, array $segments, mixed $value): array
    {
        $segment = array_shift($segments);
        if ($segment === null) {
            throw new InvalidArgumentException('Array key path cannot be empty.');
        }

        if ($segments === []) {
            $array[$segment] = $value;

            return $array;
        }

        $child = $array[$segment] ?? [];
        $array[$segment] = self::writeValue(is_array($child) ? $child : [], $segments, $value);

        return $array;
    }

    /**
     * @param array<array-key, mixed> $array
     * @param list<string> $segments
     *
     * @return array<array-key, mixed>
     */
    private static function removePath(array $array, array $segments): array
    {
        $segment = array_shift($segments);
        if ($segment === null) {
            return $array;
        }

        if (!array_key_exists($segment, $array)) {
            return $array;
        }

        if ($segments === []) {
            unset($array[$segment]);

            return $array;
        }

        $child = $array[$segment];
        if (is_array($child)) {
            $array[$segment] = self::removePath($child, $segments);
        }

        return $array;
    }

    /**
     * @param array<array-key, mixed> $array
     *
     * @return array<string, mixed>
     */
    private static function flatten(array $array, string $prefix): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

            if (is_array($value) && $value !== []) {
                foreach (self::flatten($value, $path) as $nestedKey => $nestedValue) {
                    $result[$nestedKey] = $nestedValue;
                }

                continue;
            }

            $result[$path] = $value;
        }

        return $result;
    }

    /**
     * @return list<string>
     *
     * @throws InvalidArgumentException When the path is empty or contains empty segments.
     */
    private static function segments(string|int $key): array
    {
        if (is_int($key)) {
            return [(string) $key];
        }

        if ($key === '' || str_contains($key, "\0")) {
            throw new InvalidArgumentException('Array key path cannot be empty or contain NUL bytes.');
        }

        $segments = explode('.', $key);
        foreach ($segments as $segment) {
            if ($segment === '') {
                throw new InvalidArgumentException("Array key path cannot contain empty segments: {$key}");
            }
        }

        return $segments;
    }
}
