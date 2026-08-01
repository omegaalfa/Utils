<?php

declare(strict_types=1);

use Omegaalfa\Utils\Debug\Debug;

if (!function_exists('dump_debug')) {
    function dump_debug(mixed ...$values): void
    {
        Debug::dump(...$values);
    }
}

if (!function_exists('ss')) {
    function ss(mixed ...$values): void
    {
        Debug::ss(...$values);
    }
}

if (!function_exists('dd')) {
    function dd(mixed ...$values): never
    {
        Debug::dd(...$values);
    }
}
