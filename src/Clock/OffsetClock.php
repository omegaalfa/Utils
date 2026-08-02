<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Clock;

use DateInterval;
use DateTimeImmutable;

final readonly class OffsetClock implements Clock
{
    private DateInterval $offset;

    public function __construct(
        private Clock $clock,
        DateInterval $offset,
    ) {
        $this->offset = clone $offset;
    }

    public function now(): DateTimeImmutable
    {
        return $this->clock->now()->add($this->offset);
    }
}
