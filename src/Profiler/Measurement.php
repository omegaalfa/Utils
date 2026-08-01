<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Profiler;

final readonly class Measurement
{
    public function __construct(
        public string $name,
        public int $durationNanoseconds,
        public int $memoryDeltaBytes,
    ) {
    }

    public function durationMilliseconds(): float
    {
        return $this->durationNanoseconds / 1_000_000;
    }
}
