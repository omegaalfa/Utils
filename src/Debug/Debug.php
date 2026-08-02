<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Debug;

final class Debug
{
    public static function dump(mixed ...$values): void
    {
        if ($values === []) {
            return;
        }

        $html = PHP_SAPI !== 'cli';
        $colors = !$html && self::usesTerminalColors();

        if ($html) {
            echo '<pre style="background:#0f172a;color:#e2e8f0;padding:12px 16px;'
                . 'border-radius:8px;overflow:auto;line-height:1.45">';
        }

        $index = 1;
        foreach ($values as $value) {
            self::writeHeader($index, $html, $colors);
            var_dump($value);
            $index++;
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

    private static function usesTerminalColors(): bool
    {
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        $forced = getenv('OMEGA_DEBUG_COLORS');
        if ($forced !== false) {
            return $forced === '1';
        }

        return defined('STDOUT')
            && function_exists('stream_isatty')
            && stream_isatty(STDOUT);
    }

    private static function writeHeader(int $index, bool $html, bool $colors): void
    {
        $label = "Debug #{$index}";

        if ($html) {
            echo '<span style="color:#22d3ee;font-weight:700">'
                . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '</span>' . PHP_EOL;
            return;
        }

        if ($colors) {
            echo "\033[1;36m {$label} \033[0m", PHP_EOL;
            return;
        }

        echo " {$label} ", PHP_EOL;
    }
}
