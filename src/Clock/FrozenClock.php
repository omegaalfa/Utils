<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Clock;

use DateTimeImmutable;

final readonly class FrozenClock implements Clock
{
    public function __construct(
        private DateTimeImmutable $instant,
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return $this->instant;
    }
}
