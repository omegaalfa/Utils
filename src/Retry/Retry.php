<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Retry;

use InvalidArgumentException;
use Throwable;

final class Retry
{
    /**
     * @template TResult
     * @param callable(): TResult $operation
     * @param list<string> $retryOn
     * @param null|callable(Throwable, int): bool $shouldRetry
     * @param null|callable(Throwable, int, bool): void $onFailure
     * @return TResult
     * @throws Throwable
     */
    public static function attempt(
        callable $operation,
        int $attempts = 3,
        int $delayMilliseconds = 0,
        float $multiplier = 2.0,
        bool $jitter = false,
        array $retryOn = [Throwable::class],
        ?callable $shouldRetry = null,
        ?callable $onFailure = null,
        int $maxDelayMilliseconds = 60_000,
    ): mixed {
        self::validate(
            $attempts,
            $delayMilliseconds,
            $multiplier,
            $retryOn,
            $maxDelayMilliseconds,
        );

        $delay = $delayMilliseconds;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                return $operation();
            } catch (Throwable $exception) {
                $eligible = self::matches($exception, $retryOn);
                $retry = $attempt < $attempts
                    && $eligible
                    && ($shouldRetry === null || $shouldRetry($exception, $attempt));

                if ($onFailure !== null) {
                    $onFailure($exception, $attempt, $retry);
                }

                if (!$retry) {
                    throw $exception;
                }

                $sleep = $jitter && $delay > 0
                    ? random_int(0, $delay)
                    : $delay;

                if ($sleep > 0) {
                    usleep($sleep * 1_000);
                }

                if ($delay >= $maxDelayMilliseconds) {
                    $delay = $maxDelayMilliseconds;
                    continue;
                }

                $nextDelay = $delay * $multiplier;
                $delay = $nextDelay >= $maxDelayMilliseconds
                    ? $maxDelayMilliseconds
                    : (int) ceil($nextDelay);
            }
        }

        throw new \LogicException('Retry loop terminated unexpectedly.');
    }

    /**
     * @param list<string> $retryOn
     */
    private static function validate(
        int $attempts,
        int $delay,
        float $multiplier,
        array $retryOn,
        int $maximumDelay,
    ): void {
        if ($attempts < 1) {
            throw new InvalidArgumentException('Attempts must be greater than zero.');
        }
        if ($delay < 0 || $maximumDelay < 0) {
            throw new InvalidArgumentException('Retry delays cannot be negative.');
        }
        if ($delay > $maximumDelay) {
            throw new InvalidArgumentException('Initial delay cannot exceed the maximum delay.');
        }
        if ($multiplier < 1.0 || !is_finite($multiplier)) {
            throw new InvalidArgumentException('Retry multiplier must be finite and at least 1.0.');
        }
        if ($retryOn === []) {
            throw new InvalidArgumentException('At least one retryable exception class is required.');
        }

        foreach ($retryOn as $class) {
            if (!is_a($class, Throwable::class, true)) {
                throw new InvalidArgumentException("Retry class must implement Throwable: {$class}");
            }
        }
    }

    /** @param list<string> $classes */
    private static function matches(Throwable $exception, array $classes): bool
    {
        foreach ($classes as $class) {
            if ($exception instanceof $class) {
                return true;
            }
        }

        return false;
    }
}
