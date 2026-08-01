<?php

declare(strict_types=1);

namespace Tests\Fibers;

use Fiber;
use InvalidArgumentException;
use LogicException;
use Omegaalfa\Utils\Fibers\FiberScheduler;
use OverflowException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FiberSchedulerTest extends TestCase
{
    public function testRunsTasksInCooperativeRoundRobinOrder(): void
    {
        $scheduler = new FiberScheduler();
        $events = [];

        $first = $scheduler->schedule(static function () use (&$events): string {
            $events[] = 'first:start';
            Fiber::suspend();
            $events[] = 'first:end';
            return 'first result';
        });
        $second = $scheduler->schedule(static function () use (&$events): string {
            $events[] = 'second:start';
            Fiber::suspend();
            $events[] = 'second:end';
            return 'second result';
        });

        self::assertSame(2, $scheduler->pendingCount());
        $results = $scheduler->run();

        self::assertSame([
            'first:start',
            'second:start',
            'first:end',
            'second:end',
        ], $events);
        self::assertSame([
            $first => 'first result',
            $second => 'second result',
        ], $results);
    }

    public function testReturnsResultsInSchedulingOrder(): void
    {
        $scheduler = new FiberScheduler();
        $first = $scheduler->schedule(static fn (): array => ['value' => 1]);
        $second = $scheduler->schedule(static fn (): bool => true);

        self::assertSame([
            $first => ['value' => 1],
            $second => true,
        ], $scheduler->run());
        self::assertTrue($scheduler->isEmpty());
        self::assertSame(0, $scheduler->pendingCount());
    }

    public function testEmptySchedulerReturnsImmediately(): void
    {
        $scheduler = new FiberScheduler();

        self::assertSame([], $scheduler->run());
        self::assertTrue($scheduler->isEmpty());
    }

    public function testTasksMayScheduleMoreTasks(): void
    {
        $scheduler = new FiberScheduler();
        $events = [];

        $scheduler->schedule(static function () use ($scheduler, &$events): void {
            $events[] = 'parent';
            $scheduler->schedule(static function () use (&$events): void {
                $events[] = 'child';
            });
        });

        $results = $scheduler->run();

        self::assertSame(['parent', 'child'], $events);
        self::assertCount(2, $results);
    }

    public function testRejectsInvalidCapacity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new FiberScheduler(0);
    }

    public function testEnforcesTaskCapacity(): void
    {
        $scheduler = new FiberScheduler(1);
        $scheduler->schedule(static fn (): null => null);

        $this->expectException(OverflowException::class);
        $this->expectExceptionMessage('capacity of 1');
        $scheduler->schedule(static fn (): null => null);
    }

    public function testPropagatesSameExceptionAndDiscardsRemainingTasks(): void
    {
        $scheduler = new FiberScheduler();
        $expected = new RuntimeException('task failed');
        $scheduler->schedule(static function () use ($expected): never {
            throw $expected;
        });
        $scheduler->schedule(static fn (): string => 'never executed');

        try {
            $scheduler->run();
            self::fail('Expected task exception was not thrown.');
        } catch (RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertTrue($scheduler->isEmpty());
        self::assertSame([], $scheduler->run());

        $scheduler->schedule(static fn (): string => 'reused');
        self::assertSame([2 => 'reused'], $scheduler->run());
    }

    public function testRejectsNestedRun(): void
    {
        $scheduler = new FiberScheduler();
        $scheduler->schedule(static function () use ($scheduler): void {
            $scheduler->run();
        });

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('already running');
        $scheduler->run();
    }

}
