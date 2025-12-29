<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Tests;

use DateTime;
use Omegaalfa\Utils\Date;
use PHPUnit\Framework\TestCase;

class DateTest extends TestCase
{
    public function testNow(): void
    {
        $now = Date::now();
        $this->assertInstanceOf(DateTime::class, $now);
    }

    public function testParse(): void
    {
        $date = Date::parse('2024-01-01');
        $this->assertSame('2024-01-01', $date->format('Y-m-d'));
    }

    public function testFormat(): void
    {
        $date = new DateTime('2024-01-01 12:00:00');
        $this->assertSame('2024-01-01 12:00:00', Date::format($date));
        $this->assertSame('2024-01-01', Date::format($date, 'Y-m-d'));
    }

    public function testTimestamp(): void
    {
        $date = new DateTime('2024-01-01 00:00:00');
        $timestamp = Date::timestamp($date);
        $this->assertIsInt($timestamp);
    }

    public function testFromTimestamp(): void
    {
        $timestamp = strtotime('2024-01-01 00:00:00');
        $date = Date::fromTimestamp($timestamp);
        $this->assertSame('2024-01-01', $date->format('Y-m-d'));
    }

    public function testDiff(): void
    {
        $date1 = new DateTime('2024-01-01');
        $date2 = new DateTime('2024-01-10');
        $diff = Date::diff($date1, $date2);
        $this->assertSame(9, $diff->days);
    }

    public function testDiffInDays(): void
    {
        $date1 = new DateTime('2024-01-01');
        $date2 = new DateTime('2024-01-10');
        $this->assertSame(9, Date::diffInDays($date1, $date2));
    }

    public function testAddDays(): void
    {
        $date = new DateTime('2024-01-01');
        $newDate = Date::addDays($date, 5);
        $this->assertSame('2024-01-06', $newDate->format('Y-m-d'));
    }

    public function testAddHours(): void
    {
        $date = new DateTime('2024-01-01 12:00:00');
        $newDate = Date::addHours($date, 3);
        $this->assertSame('2024-01-01 15:00:00', $newDate->format('Y-m-d H:i:s'));
    }

    public function testAddMinutes(): void
    {
        $date = new DateTime('2024-01-01 12:00:00');
        $newDate = Date::addMinutes($date, 30);
        $this->assertSame('2024-01-01 12:30:00', $newDate->format('Y-m-d H:i:s'));
    }

    public function testStartOfDay(): void
    {
        $date = new DateTime('2024-01-01 12:30:45');
        $startOfDay = Date::startOfDay($date);
        $this->assertSame('2024-01-01 00:00:00', $startOfDay->format('Y-m-d H:i:s'));
    }

    public function testEndOfDay(): void
    {
        $date = new DateTime('2024-01-01 12:30:45');
        $endOfDay = Date::endOfDay($date);
        $this->assertSame('2024-01-01 23:59:59', $endOfDay->format('Y-m-d H:i:s'));
    }

    public function testIsToday(): void
    {
        $today = new DateTime();
        $yesterday = new DateTime('yesterday');
        
        $this->assertTrue(Date::isToday($today));
        $this->assertFalse(Date::isToday($yesterday));
    }

    public function testIsPast(): void
    {
        $yesterday = new DateTime('yesterday');
        $tomorrow = new DateTime('tomorrow');
        
        $this->assertTrue(Date::isPast($yesterday));
        $this->assertFalse(Date::isPast($tomorrow));
    }

    public function testIsFuture(): void
    {
        $tomorrow = new DateTime('tomorrow');
        $yesterday = new DateTime('yesterday');
        
        $this->assertTrue(Date::isFuture($tomorrow));
        $this->assertFalse(Date::isFuture($yesterday));
    }

    public function testIsBetween(): void
    {
        $date = new DateTime('2024-01-15');
        $start = new DateTime('2024-01-01');
        $end = new DateTime('2024-01-31');
        
        $this->assertTrue(Date::isBetween($date, $start, $end));
        
        $outOfRange = new DateTime('2024-02-01');
        $this->assertFalse(Date::isBetween($outOfRange, $start, $end));
    }
}
