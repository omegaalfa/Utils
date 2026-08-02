<?php

declare(strict_types=1);

namespace Tests\Clock;

use DateInterval;
use DateTimeImmutable;
use Omegaalfa\Utils\Clock\Clock;
use Omegaalfa\Utils\Clock\FrozenClock;
use Omegaalfa\Utils\Clock\OffsetClock;
use Omegaalfa\Utils\Clock\SystemClock;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    public function testSystemClockReturnsCurrentImmutableTime(): void
    {
        $clock = new SystemClock();
        $before = new DateTimeImmutable();
        $now = $clock->now();
        $after = new DateTimeImmutable();

        self::assertInstanceOf(Clock::class, $clock);
        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
    }

    public function testSystemClockNeverMovesBackwardsAcrossSuccessiveCalls(): void
    {
        $clock = new SystemClock();
        $previous = $clock->now();

        for ($call = 0; $call < 10; $call++) {
            $current = $clock->now();
            self::assertGreaterThanOrEqual($previous, $current);
            $previous = $current;
        }
    }

    public function testFrozenClockAlwaysReturnsTheExactSameInstance(): void
    {
        $instant = new DateTimeImmutable('2026-08-01 10:00:00 UTC');
        $clock = new FrozenClock($instant);

        self::assertSame($instant, $clock->now());
        self::assertSame($clock->now(), $clock->now());
        self::assertEquals($instant, $clock->now());
    }

    public function testOffsetClockAppliesPositiveOffset(): void
    {
        $clock = new OffsetClock(
            new FrozenClock(new DateTimeImmutable('2026-08-01 10:00:00 UTC')),
            new DateInterval('PT2H'),
        );

        self::assertSame('2026-08-01 12:00:00', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testOffsetClockAppliesNegativeOffset(): void
    {
        $offset = new DateInterval('PT2H');
        $offset->invert = 1;
        $clock = new OffsetClock(
            new FrozenClock(new DateTimeImmutable('2026-08-01 10:00:00 UTC')),
            $offset,
        );

        self::assertSame('2026-08-01 08:00:00', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function testOffsetClockAppliesZeroOffset(): void
    {
        $instant = new DateTimeImmutable('2026-08-01 10:00:00 UTC');
        $clock = new OffsetClock(new FrozenClock($instant), new DateInterval('PT0S'));

        self::assertEquals($instant, $clock->now());
        self::assertNotSame($instant, $clock->now());
    }

    public function testOffsetClockDefensivelyCopiesInterval(): void
    {
        $offset = new DateInterval('PT1H');
        $clock = new OffsetClock(
            new FrozenClock(new DateTimeImmutable('2026-08-01 10:00:00 UTC')),
            $offset,
        );
        $offset->h = 8;

        self::assertSame('11:00:00', $clock->now()->format('H:i:s'));
    }

    public function testOffsetClockComposesWithSystemClock(): void
    {
        $clock = new OffsetClock(new SystemClock(), new DateInterval('PT1S'));
        $minimum = (new DateTimeImmutable())->add(new DateInterval('PT1S'));

        self::assertGreaterThanOrEqual($minimum, $clock->now());
    }
}
