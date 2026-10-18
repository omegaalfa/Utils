<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\RateLimiter;

use InvalidArgumentException;
use RuntimeException;

/**
 * File-based sliding-window rate limiter safe for concurrent processes.
 *
 * Each key maps to one JSON file holding recent attempt timestamps. Every
 * mutation takes an exclusive lock so parallel workers cannot exceed the limit.
 */
final class FileRateLimiter
{
    private readonly string $directory;

    /**
     * @param string $directory Existing or creatable directory that stores one file per key.
     * @param int $maxAttempts Maximum attempts allowed per window.
     * @param int $decaySeconds Window length in seconds.
     *
     * @throws InvalidArgumentException When the directory or limits are invalid.
     * @throws RuntimeException When the directory cannot be created or resolved.
     */
    public function __construct(
        string $directory,
        private readonly int $maxAttempts = 60,
        private readonly int $decaySeconds = 60,
    ) {
        if ($directory === '' || str_contains($directory, "\0")) {
            throw new InvalidArgumentException('Rate limiter directory cannot be empty or contain NUL bytes.');
        }

        if (is_link($directory)) {
            throw new InvalidArgumentException("Symbolic links cannot be rate limiter directories: {$directory}");
        }

        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('Rate limiter max attempts must be at least one.');
        }

        if ($decaySeconds < 1) {
            throw new InvalidArgumentException('Rate limiter decay must be at least one second.');
        }

        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create rate limiter directory: {$directory}");
        }

        $canonical = realpath($directory);
        if ($canonical === false) {
            throw new RuntimeException("Unable to resolve rate limiter directory: {$directory}");
        }

        $this->directory = $canonical;
    }

    /**
     * Records one attempt and reports whether it is within the configured limit.
     *
     * The optional callback runs only when the attempt is allowed. A rejected
     * attempt does not consume budget and does not invoke the callback.
     *
     * @param null|callable(): void $callback
     *
     * @throws InvalidArgumentException When the key is invalid.
     * @throws RuntimeException When the limiter storage cannot be read or written.
     */
    public function attempt(string $key, ?callable $callback = null): bool
    {
        $allowed = $this->synchronized($key, function (array $hits): array {
            self::prune($hits, $this->decaySeconds);

            if (count($hits) >= $this->maxAttempts) {
                return [$hits, false];
            }

            $hits[] = microtime(true);

            return [$hits, true];
        });

        if ($allowed && $callback !== null) {
            $callback();
        }

        return $allowed;
    }

    /**
     * Reports whether the key already reached a custom limit without recording an attempt.
     *
     * The check is read-only: it never prunes stored timestamps, so it is safe
     * to call with a window different from the one configured for {@see self::attempt()}.
     *
     * @throws InvalidArgumentException When the key or limits are invalid.
     * @throws RuntimeException When the limiter storage cannot be read.
     */
    public function tooManyAttempts(string $key, int $maxAttempts, int $decaySeconds): bool
    {
        if ($maxAttempts < 1) {
            throw new InvalidArgumentException('Rate limiter max attempts must be at least one.');
        }

        if ($decaySeconds < 1) {
            throw new InvalidArgumentException('Rate limiter decay must be at least one second.');
        }

        return $this->synchronized($key, static function (array $hits) use ($maxAttempts, $decaySeconds): array {
            return [$hits, count(self::within($hits, $decaySeconds)) >= $maxAttempts];
        });
    }

    /**
     * Returns how many attempts are still available in the configured window.
     *
     * @throws InvalidArgumentException When the key is invalid.
     * @throws RuntimeException When the limiter storage cannot be read.
     */
    public function remaining(string $key): int
    {
        return $this->synchronized($key, function (array $hits): array {
            self::prune($hits, $this->decaySeconds);

            return [$hits, max(0, $this->maxAttempts - count($hits))];
        });
    }

    /**
     * Clears every stored attempt for the given key.
     *
     * @throws InvalidArgumentException When the key is invalid.
     * @throws RuntimeException When the limiter storage cannot be written.
     */
    public function reset(string $key): void
    {
        $this->synchronized($key, static function (array $hits): array {
            return [[], null];
        });
    }

    /**
     * @template TResult
     *
     * @param callable(list<float>): array{list<float>, TResult} $operation Returns the updated hit log and the result.
     *
     * @return TResult
     *
     * @throws InvalidArgumentException When the key is invalid.
     * @throws RuntimeException When the storage cannot be opened, locked or written.
     */
    private function synchronized(string $key, callable $operation): mixed
    {
        $path = $this->pathFor($key);

        $handle = @fopen($path, 'c+b');
        if ($handle === false) {
            throw new RuntimeException("Unable to open rate limiter file for key: {$key}");
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new RuntimeException("Unable to lock rate limiter file for key: {$key}");
            }

            $raw = stream_get_contents($handle);
            if ($raw === false) {
                throw new RuntimeException("Unable to read rate limiter file for key: {$key}");
            }

            $decoded = $raw === '' ? [] : json_decode($raw, true);
            $hits = is_array($decoded) ? self::timestamps($decoded) : [];

            [$hits, $result] = $operation($hits);

            $encoded = json_encode($hits);
            if ($encoded === false) {
                throw new RuntimeException("Unable to encode rate limiter file for key: {$key}");
            }

            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
                throw new RuntimeException("Unable to write rate limiter file for key: {$key}");
            }

            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function pathFor(string $key): string
    {
        self::assertKey($key);

        return $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function assertKey(string $key): void
    {
        if ($key === '' || str_contains($key, "\0")) {
            throw new InvalidArgumentException('Rate limiter key cannot be empty or contain NUL bytes.');
        }
    }

    /**
     * @param array<array-key, mixed> $decoded
     *
     * @return list<float>
     */
    private static function timestamps(array $decoded): array
    {
        $hits = [];

        foreach ($decoded as $value) {
            if (is_int($value) || is_float($value)) {
                $hits[] = (float) $value;
            }
        }

        return $hits;
    }

    /**
     * @param list<float> $hits
     */
    private static function prune(array &$hits, int $decaySeconds): void
    {
        $hits = self::within($hits, $decaySeconds);
    }

    /**
     * @param list<float> $hits
     *
     * @return list<float>
     */
    private static function within(array $hits, int $decaySeconds): array
    {
        $cutoff = microtime(true) - $decaySeconds;

        return array_values(array_filter($hits, static fn (float $hit): bool => $hit > $cutoff));
    }
}
