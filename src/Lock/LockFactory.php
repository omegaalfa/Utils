<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Lock;

use InvalidArgumentException;
use RuntimeException;

final class LockFactory
{
    /**
     * @var string
     */
    private readonly string $directory;

    /**
     * @param string|null $directory
     */
    public function __construct(?string $directory = null)
    {
        $directory ??= sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'omegaalfa-locks';
        $directory = rtrim($directory, DIRECTORY_SEPARATOR . '/\\');

        if ($directory === '') {
            throw new InvalidArgumentException('Lock directory cannot be empty.');
        }
        if (is_link($directory)) {
            throw new RuntimeException('Lock directory cannot be a symbolic link.');
        }
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create lock directory: {$directory}");
        }

        $canonical = realpath($directory);
        if ($canonical === false || !is_dir($canonical) || !is_writable($canonical)) {
            throw new RuntimeException("Lock directory must be writable: {$directory}");
        }

        $this->directory = $canonical;
    }

    /**
     * @param string $name
     * @return LockInterface
     */
    public function create(string $name): LockInterface
    {
        if ($name === '') {
            throw new InvalidArgumentException('Lock name cannot be empty.');
        }

        $filename = hash('sha256', $name) . '.lock';

        return new FileLock(
            $name,
            $this->directory . DIRECTORY_SEPARATOR . $filename,
        );
    }

    /**
     * @return string
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }
}
