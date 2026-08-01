<?php

declare(strict_types=1);

namespace Tests\Profiler;

use InvalidArgumentException;
use LogicException;
use Omegaalfa\Utils\Profiler\Measurement;
use Omegaalfa\Utils\Profiler\Profiler;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProfilerTest extends TestCase
{
    public function testStartAndStopReturnMeasurement(): void
    {
        $profiler = new Profiler();

        $profiler->start('database-query');
        usleep(1_000);
        $measurement = $profiler->stop('database-query');

        self::assertInstanceOf(Measurement::class, $measurement);
        self::assertSame('database-query', $measurement->name);
        self::assertGreaterThan(0, $measurement->durationNanoseconds);
        self::assertGreaterThan(0.0, $measurement->durationMilliseconds());
        self::assertFalse($profiler->isActive('database-query'));
    }

    public function testMeasuresRetainedMemoryDelta(): void
    {
        $profiler = new Profiler();
        $profiler->start('allocation');

        $allocated = str_repeat('x', 1_048_576);
        $measurement = $profiler->stop('allocation');

        self::assertSame(1_048_576, strlen($allocated));
        self::assertGreaterThan(1_000_000, $measurement->memoryDeltaBytes);
    }

    public function testAggregatesCallsDurationsAndMemory(): void
    {
        $profiler = new Profiler();

        $profiler->start('query');
        usleep(1_000);
        $first = $profiler->stop('query');

        $profiler->start('query');
        usleep(2_000);
        $second = $profiler->stop('query');

        $entry = $profiler->get('query');
        self::assertNotNull($entry);
        self::assertSame(2, $entry->calls);
        self::assertSame(
            $first->durationNanoseconds + $second->durationNanoseconds,
            $entry->totalDurationNanoseconds,
        );
        self::assertSame(
            min($first->durationNanoseconds, $second->durationNanoseconds),
            $entry->minimumDurationNanoseconds,
        );
        self::assertSame(
            max($first->durationNanoseconds, $second->durationNanoseconds),
            $entry->maximumDurationNanoseconds,
        );
        self::assertSame(
            $first->memoryDeltaBytes + $second->memoryDeltaBytes,
            $entry->totalMemoryDeltaBytes,
        );
        self::assertEquals(
            $entry->totalDurationNanoseconds / 2,
            $entry->averageDurationNanoseconds(),
        );
        self::assertSame(
            $entry->averageDurationNanoseconds() / 1_000_000,
            $entry->averageDurationMilliseconds(),
        );
        self::assertSame(
            $entry->totalDurationNanoseconds / 1_000_000,
            $entry->totalDurationMilliseconds(),
        );
    }

    public function testMeasureReturnsOperationResultAndRecordsCall(): void
    {
        $profiler = new Profiler();

        $result = $profiler->measure(
            'calculation',
            static fn (): int => 21 * 2,
        );

        self::assertSame(42, $result);
        self::assertSame(1, $profiler->get('calculation')?->calls);
    }

    public function testMeasureRecordsFailedOperationAndRethrowsSameException(): void
    {
        $profiler = new Profiler();
        $expected = new RuntimeException('repository failed');

        try {
            $profiler->measure('repository', static function () use ($expected): never {
                throw $expected;
            });
        } catch (RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertFalse($profiler->isActive('repository'));
        self::assertSame(1, $profiler->get('repository')?->calls);
    }

    public function testSupportsDifferentConcurrentStages(): void
    {
        $profiler = new Profiler();

        $profiler->start('request');
        $profiler->start('database');
        $profiler->stop('database');
        $profiler->stop('request');

        self::assertCount(2, $profiler->all());
    }

    public function testSlowestOrdersByTotalDurationAndAppliesLimit(): void
    {
        $profiler = new Profiler();

        $profiler->start('fast');
        usleep(1_000);
        $profiler->stop('fast');

        $profiler->start('slow');
        usleep(8_000);
        $profiler->stop('slow');

        $slowest = $profiler->slowest(1);

        self::assertCount(1, $slowest);
        self::assertSame('slow', $slowest[0]->name);
    }

    public function testResetClearsStatisticsAndActiveMeasurements(): void
    {
        $profiler = new Profiler();
        $profiler->measure('completed', static fn (): null => null);
        $profiler->start('active');

        $profiler->reset();

        self::assertSame([], $profiler->all());
        self::assertFalse($profiler->isActive('active'));
        self::assertNull($profiler->get('completed'));
    }

    public function testRejectsDuplicateActiveMeasurement(): void
    {
        $profiler = new Profiler();
        $profiler->start('duplicate');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('already active');
        $profiler->start('duplicate');
    }

    public function testStopRequiresActiveMeasurement(): void
    {
        $profiler = new Profiler();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('is not active');
        $profiler->stop('missing');
    }

    public function testRejectsEmptyName(): void
    {
        $profiler = new Profiler();

        $this->expectException(InvalidArgumentException::class);
        $profiler->start('');
    }

    public function testSlowestRequiresPositiveLimit(): void
    {
        $profiler = new Profiler();

        $this->expectException(InvalidArgumentException::class);
        $profiler->slowest(0);
    }
}
