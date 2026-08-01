<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Debug;

final class Debug
{
    public static function dump(mixed ...$values): void
    {
        $html = PHP_SAPI !== 'cli';

        if ($html) {
            echo '<pre>';
        }

        foreach ($values as $value) {
            var_dump($value);
        }

        if ($html) {
            echo '</pre>';
        }
    }

    /**
     * Short alias for dump(), kept for projects that already use ss().
     */
    public static function ss(mixed ...$values): void
    {
        self::dump(...$values);
    }

    /**
     * Dumps values and terminates with a non-zero status.
     */
    public static function dd(mixed ...$values): never
    {
        self::dump(...$values);
        exit(1);
    }
}
