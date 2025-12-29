<?php

declare(strict_types=1);

namespace Omegaalfa\Utils;

use DateTime;
use DateTimeZone;
use Exception;

class Date
{
    public static function now(?DateTimeZone $timezone = null): DateTime
    {
        return new DateTime('now', $timezone);
    }

    public static function parse(string $datetime, ?DateTimeZone $timezone = null): DateTime
    {
        return new DateTime($datetime, $timezone);
    }

    public static function format(DateTime $date, string $format = 'Y-m-d H:i:s'): string
    {
        return $date->format($format);
    }

    public static function timestamp(DateTime $date): int
    {
        return $date->getTimestamp();
    }

    public static function fromTimestamp(int $timestamp, ?DateTimeZone $timezone = null): DateTime
    {
        $date = new DateTime('now', $timezone);
        $date->setTimestamp($timestamp);
        return $date;
    }

    public static function diff(DateTime $date1, DateTime $date2): \DateInterval
    {
        return $date1->diff($date2);
    }

    public static function diffInDays(DateTime $date1, DateTime $date2): int
    {
        return (int) $date1->diff($date2)->format('%r%a');
    }

    public static function diffInHours(DateTime $date1, DateTime $date2): int
    {
        $diff = $date1->diff($date2);
        return (int) (($diff->days * 24) + $diff->h) * ($diff->invert ? -1 : 1);
    }

    public static function diffInMinutes(DateTime $date1, DateTime $date2): int
    {
        $diff = $date1->diff($date2);
        return (int) ((($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i) * ($diff->invert ? -1 : 1));
    }

    public static function addDays(DateTime $date, int $days): DateTime
    {
        $newDate = clone $date;
        $newDate->modify(sprintf('%+d days', $days));
        return $newDate;
    }

    public static function addHours(DateTime $date, int $hours): DateTime
    {
        $newDate = clone $date;
        $newDate->modify(sprintf('%+d hours', $hours));
        return $newDate;
    }

    public static function addMinutes(DateTime $date, int $minutes): DateTime
    {
        $newDate = clone $date;
        $newDate->modify(sprintf('%+d minutes', $minutes));
        return $newDate;
    }

    public static function startOfDay(DateTime $date): DateTime
    {
        $newDate = clone $date;
        $newDate->setTime(0, 0, 0);
        return $newDate;
    }

    public static function endOfDay(DateTime $date): DateTime
    {
        $newDate = clone $date;
        $newDate->setTime(23, 59, 59);
        return $newDate;
    }

    public static function isToday(DateTime $date): bool
    {
        return $date->format('Y-m-d') === (new DateTime())->format('Y-m-d');
    }

    public static function isPast(DateTime $date): bool
    {
        return $date < new DateTime();
    }

    public static function isFuture(DateTime $date): bool
    {
        return $date > new DateTime();
    }

    public static function isBetween(DateTime $date, DateTime $start, DateTime $end): bool
    {
        return $date >= $start && $date <= $end;
    }
}
