<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Profiler;

final readonly class ProfileEntry
{
    public function __construct(
        public string $name,
        public int $calls,
        public int $totalDurationNanoseconds,
        public int $minimumDurationNanoseconds,
        public int $maximumDurationNanoseconds,
        public int $totalMemoryDeltaBytes,
    ) {
    }

    public function totalDurationMilliseconds(): float
    {
        return $this->totalDurationNanoseconds / 1_000_000;
    }

    public function averageDurationNanoseconds(): float
    {
        return $this->totalDurationNanoseconds / $this->calls;
    }

    public function averageDurationMilliseconds(): float
    {
        return $this->averageDurationNanoseconds() / 1_000_000;
    }
}
