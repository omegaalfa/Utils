<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Process;

/**
 * Immutable snapshot of a finished process.
 */
final readonly class ProcessResult
{
    /**
     * @param list<string> $command The argv-style command that was executed.
     * @param string $stdout Captured standard output.
     * @param string $stderr Captured standard error output.
     * @param int $exitCode The process exit code.
     */
    public function __construct(
        private array $command,
        private string $stdout,
        private string $stderr,
        private int $exitCode,
    ) {
    }

    /**
     * Returns the argv-style command that produced this result.
     *
     * @return list<string>
     */
    public function command(): array
    {
        return $this->command;
    }

    /**
     * Reports whether the process finished with exit code zero.
     */
    public function ok(): bool
    {
        return $this->successful();
    }

    /**
     * Alias of {@see self::ok()} for call sites that prefer the verbose name.
     */
    public function successful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * Returns the process exit code.
     */
    public function exitCode(): int
    {
        return $this->exitCode;
    }

    /**
     * Returns the captured standard output.
     */
    public function stdout(): string
    {
        return $this->stdout;
    }

    /**
     * Returns the captured standard error output.
     */
    public function stderr(): string
    {
        return $this->stderr;
    }
}
