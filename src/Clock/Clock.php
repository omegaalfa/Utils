<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Clock;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
