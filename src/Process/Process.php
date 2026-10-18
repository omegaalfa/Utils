<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Process;

use InvalidArgumentException;
use RuntimeException;

/**
 * Executes argv-style commands without a shell and captures their output.
 */
final class Process
{
    private const int POLL_MICROSECONDS = 10_000;

    private function __construct()
    {
    }

    /**
     * Runs a command, optionally killing it after a timeout.
     *
     * @param list<string> $command The program followed by its arguments; never interpreted by a shell.
     * @param null|float $timeoutSeconds Maximum wall-clock time in seconds, or null to wait indefinitely.
     * @param null|string $workingDirectory Directory to run in; defaults to the current working directory.
     *
     * @throws InvalidArgumentException When the command, timeout or working directory is invalid.
     * @throws ProcessTimedOutException When the process exceeds the timeout and is killed.
     * @throws RuntimeException When the process cannot be started or its output cannot be read.
     */
    public static function run(
        array $command,
        ?float $timeoutSeconds = 60.0,
        ?string $workingDirectory = null,
    ): ProcessResult {
        self::validate($command, $timeoutSeconds, $workingDirectory);

        $stdoutPath = self::temporaryFile('omega-proc-out-');
        $stderrPath = self::temporaryFile('omega-proc-err-');

        try {
            $process = @proc_open(
                $command,
                [
                    0 => ['pipe', 'r'],
                    1 => ['file', $stdoutPath, 'wb'],
                    2 => ['file', $stderrPath, 'wb'],
                ],
                $pipes,
                $workingDirectory,
                null,
                ['bypass_shell' => true],
            );

            if (!is_resource($process)) {
                throw new RuntimeException('Unable to start process: ' . implode(' ', $command));
            }

            if (isset($pipes[0]) && is_resource($pipes[0])) {
                fclose($pipes[0]);
            }

            $exitCode = self::await($process, $timeoutSeconds, $command);

            $stdout = file_get_contents($stdoutPath);
            $stderr = file_get_contents($stderrPath);
            if ($stdout === false || $stderr === false) {
                throw new RuntimeException('Unable to read process output.');
            }

            return new ProcessResult($command, $stdout, $stderr, $exitCode);
        } finally {
            self::removeTemporary($stdoutPath);
            self::removeTemporary($stderrPath);
        }
    }

    /**
     * @param list<string> $command
     *
     * @throws InvalidArgumentException
     */
    private static function validate(array $command, ?float $timeoutSeconds, ?string $workingDirectory): void
    {
        if ($command === []) {
            throw new InvalidArgumentException('Process command cannot be empty.');
        }

        foreach ($command as $argument) {
            if ($argument === '' || str_contains($argument, "\0")) {
                throw new InvalidArgumentException('Process arguments must be non-empty strings without NUL bytes.');
            }
        }

        if ($timeoutSeconds !== null && $timeoutSeconds <= 0) {
            throw new InvalidArgumentException('Process timeout must be greater than zero.');
        }

        if ($workingDirectory !== null) {
            if ($workingDirectory === '' || str_contains($workingDirectory, "\0")) {
                throw new InvalidArgumentException('Process working directory cannot be empty or contain NUL bytes.');
            }
            if (!is_dir($workingDirectory)) {
                throw new InvalidArgumentException("Process working directory does not exist: {$workingDirectory}");
            }
        }
    }

    /**
     * @param resource $process
     * @param list<string> $command
     *
     * @throws ProcessTimedOutException
     */
    private static function await($process, ?float $timeoutSeconds, array $command): int
    {
        $startedAt = hrtime(true);

        while (true) {
            $status = proc_get_status($process);

            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                $closed = proc_close($process);

                return $exitCode >= 0 ? $exitCode : $closed;
            }

            if ($timeoutSeconds !== null) {
                $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;

                if ($elapsedSeconds >= $timeoutSeconds) {
                    proc_terminate($process, 9);
                    proc_close($process);

                    throw new ProcessTimedOutException(
                        sprintf('Process exceeded the timeout of %s seconds: %s', $timeoutSeconds, implode(' ', $command)),
                    );
                }
            }

            usleep(self::POLL_MICROSECONDS);
        }
    }

    private static function temporaryFile(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) {
            throw new RuntimeException('Unable to create process output files.');
        }

        return $path;
    }

    private static function removeTemporary(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
