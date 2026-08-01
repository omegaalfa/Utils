<?php

declare(strict_types=1);

namespace Tests\Retry;

use InvalidArgumentException;
use LogicException;
use Omegaalfa\Utils\Retry\Retry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RetryTest extends TestCase
{
    public function testReturnsFirstSuccessfulResult(): void
    {
        $calls = 0;

        $result = Retry::attempt(
            operation: static function () use (&$calls): string {
                $calls++;
                return 'success';
            },
            attempts: 3,
        );

        self::assertSame('success', $result);
        self::assertSame(1, $calls);
    }

    public function testRetriesUntilOperationSucceeds(): void
    {
        $calls = 0;

        $result = Retry::attempt(
            operation: static function () use (&$calls): int {
                $calls++;
                if ($calls < 3) {
                    throw new RuntimeException('temporary');
                }
                return 42;
            },
            attempts: 3,
        );

        self::assertSame(42, $result);
        self::assertSame(3, $calls);
    }

    public function testRethrowsSameExceptionAfterLastAttempt(): void
    {
        $expected = new RuntimeException('still failing');
        $calls = 0;

        try {
            Retry::attempt(
                operation: static function () use (&$calls, $expected): never {
                    $calls++;
                    throw $expected;
                },
                attempts: 2,
            );
        } catch (RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(2, $calls);
    }

    public function testFiltersRetryableExceptionClasses(): void
    {
        $calls = 0;
        $expected = new LogicException('not retryable');

        try {
            Retry::attempt(
                operation: static function () use (&$calls, $expected): never {
                    $calls++;
                    throw $expected;
                },
                attempts: 5,
                retryOn: [RuntimeException::class],
            );
        } catch (LogicException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(1, $calls);
    }

    public function testShouldRetryCanCancelFurtherAttempts(): void
    {
        $calls = 0;
        $decisions = 0;

        try {
            Retry::attempt(
                operation: static function () use (&$calls): never {
                    $calls++;
                    throw new RuntimeException('cancel');
                },
                attempts: 5,
                shouldRetry: static function (\Throwable $exception, int $attempt) use (&$decisions): bool {
                    $decisions++;
                    return $attempt < 2;
                },
            );
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        self::assertSame(2, $calls);
        self::assertSame(2, $decisions);
    }

    public function testFailureCallbackReceivesRetryDecision(): void
    {
        $calls = 0;
        $failures = [];

        $result = Retry::attempt(
            operation: static function () use (&$calls): string {
                $calls++;
                if ($calls < 2) {
                    throw new RuntimeException('once');
                }
                return 'done';
            },
            attempts: 2,
            onFailure: static function (
                \Throwable $exception,
                int $attempt,
                bool $willRetry,
            ) use (&$failures): void {
                $failures[] = [$exception->getMessage(), $attempt, $willRetry];
            },
        );

        self::assertSame('done', $result);
        self::assertSame([['once', 1, true]], $failures);
    }

    public function testAppliesFixedDelay(): void
    {
        $calls = 0;
        $start = hrtime(true);

        Retry::attempt(
            operation: static function () use (&$calls): bool {
                $calls++;
                if ($calls === 1) {
                    throw new RuntimeException('retry');
                }
                return true;
            },
            attempts: 2,
            delayMilliseconds: 5,
            multiplier: 1.0,
        );

        self::assertGreaterThanOrEqual(5_000_000, hrtime(true) - $start);
    }

    public function testSupportsJitterAndMaximumDelay(): void
    {
        $calls = 0;

        $result = Retry::attempt(
            operation: static function () use (&$calls): bool {
                $calls++;
                if ($calls < 2) {
                    throw new RuntimeException('retry');
                }
                return true;
            },
            attempts: 2,
            delayMilliseconds: 1,
            multiplier: 10.0,
            jitter: true,
            maxDelayMilliseconds: 1,
        );

        self::assertSame(2, $calls);
    }

    public function testRejectsInvalidConfiguration(): void
    {
        $invalid = [
            static fn () => Retry::attempt(static fn () => null, attempts: 0),
            static fn () => Retry::attempt(static fn () => null, delayMilliseconds: -1),
            static fn () => Retry::attempt(static fn () => null, multiplier: 0.5),
            static fn () => Retry::attempt(static fn () => null, retryOn: []),
            static fn () => Retry::attempt(static fn () => null, retryOn: ['stdClass']),
        ];

        foreach ($invalid as $operation) {
            try {
                $operation();
                self::fail('Invalid retry configuration should fail.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
