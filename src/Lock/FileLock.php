<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Lock;

use RuntimeException;
use Throwable;

final class FileLock implements LockInterface
{
    /** @var resource|null */
    private mixed $handle = null;

    /**
     * @param string $name
     * @param string $path
     */
    public function __construct(
        private readonly string $name,
        private readonly string $path,
    )
    {
    }

    /**
     *
     */
    public function __destruct()
    {
        try {
            $this->release();
        } catch (Throwable) {
            // Destructors must never interrupt application shutdown.
        }
    }

    /**
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * @param bool $blocking
     * @return bool
     */
    public function acquire(bool $blocking = false): bool
    {
        if ($this->isAcquired()) {
            return true;
        }

        $handle = @fopen($this->path, 'c+b');
        if ($handle === false) {
            throw new RuntimeException("Unable to open lock file for '{$this->name}'.");
        }

        $wouldBlock = 0;
        $operation = LOCK_EX | ($blocking ? 0 : LOCK_NB);
        if (!flock($handle, $operation, $wouldBlock)) {
            fclose($handle);

            if (!$blocking && $wouldBlock === 1) {
                return false;
            }

            throw new RuntimeException("Unable to acquire lock '{$this->name}'.");
        }

        $this->handle = $handle;
        return true;
    }

    /**
     * @return void
     */
    public function release(): void
    {
        if (!$this->isAcquired()) {
            return;
        }

        $handle = $this->handle;
        $this->handle = null;
        if (!is_resource($handle)) {
            return;
        }

        $released = flock($handle, LOCK_UN);
        fclose($handle);

        if (!$released) {
            throw new RuntimeException("Unable to release lock '{$this->name}'.");
        }
    }

    /**
     * @return bool
     */
    public function isAcquired(): bool
    {
        return is_resource($this->handle);
    }
}
