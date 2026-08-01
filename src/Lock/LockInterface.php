<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Lock;

interface LockInterface
{
    public function acquire(bool $blocking = false): bool;

    public function release(): void;

    public function isAcquired(): bool;
}
