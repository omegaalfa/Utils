<?php

declare(strict_types=1);

namespace Tests\Process;

use InvalidArgumentException;
use Omegaalfa\Utils\Process\Process;
use Omegaalfa\Utils\Process\ProcessResult;
use Omegaalfa\Utils\Process\ProcessTimedOutException;
use PHPUnit\Framework\TestCase;

final class ProcessTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $path = sys_get_temp_dir() . '/omega-process-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($path, 0700));
        $this->temporaryDirectory = $path;
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->temporaryDirectory);
    }

    public function testCapturesStreamsAndExitCodeIndependently(): void
    {
        $result = Process::run([
            PHP_BINARY,
            '-r',
            'fwrite(STDOUT, "out"); fwrite(STDERR, "err"); exit(7);',
        ]);

        self::assertInstanceOf(ProcessResult::class, $result);
        self::assertSame('out', $result->stdout());
        self::assertSame('err', $result->stderr());
        self::assertSame(7, $result->exitCode());
        self::assertFalse($result->ok());
        self::assertFalse($result->successful());
        self::assertSame([PHP_BINARY, '-r', 'fwrite(STDOUT, "out"); fwrite(STDERR, "err"); exit(7);'], $result->command());
    }

    public function testSuccessfulProcessReportsOk(): void
    {
        $result = Process::run([PHP_BINARY, '-r', 'echo "done";']);

        self::assertTrue($result->ok());
        self::assertTrue($result->successful());
        self::assertSame(0, $result->exitCode());
        self::assertSame('done', $result->stdout());
        self::assertSame('', $result->stderr());
    }

    public function testTimeoutKillsTheProcess(): void
    {
        $startedAt = microtime(true);

        try {
            Process::run([PHP_BINARY, '-r', 'sleep(30);'], timeoutSeconds: 0.25);
            self::fail('A process exceeding the timeout must throw.');
        } catch (ProcessTimedOutException $exception) {
            self::assertStringContainsString('exceeded the timeout', $exception->getMessage());
        }

        self::assertLessThan(5.0, microtime(true) - $startedAt);
    }

    public function testCommandArgumentsAreNotShellInterpreted(): void
    {
        $payload = 'hello; echo INJECTED $(whoami) `id`';

        $result = Process::run([PHP_BINARY, '-r', 'echo $argv[1] ?? "";', '--', $payload]);

        self::assertSame($payload, $result->stdout());
        self::assertStringNotContainsString('INJECTED ' . PHP_EOL, $result->stdout());
    }

    public function testUsesTheConfiguredWorkingDirectory(): void
    {
        $result = Process::run(
            [PHP_BINARY, '-r', 'echo basename(getcwd());'],
            workingDirectory: $this->temporaryDirectory,
        );

        self::assertSame(basename($this->temporaryDirectory), $result->stdout());
    }

    public function testLargeOutputsAreCapturedWithoutDeadlock(): void
    {
        $result = Process::run([
            PHP_BINARY,
            '-r',
            '$out = str_repeat("O", 2000000); $err = str_repeat("E", 2000000); fwrite(STDOUT, $out); fwrite(STDERR, $err); exit(23);',
        ]);

        self::assertSame(2_000_000, strlen($result->stdout()));
        self::assertSame(2_000_000, strlen($result->stderr()));
        self::assertSame('O', $result->stdout()[0]);
        self::assertSame('E', $result->stderr()[0]);
        self::assertSame(23, $result->exitCode());
    }

    public function testRejectsEmptyCommandAndInvalidArguments(): void
    {
        try {
            Process::run([]);
            self::fail('An empty command must be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        foreach ([
            [PHP_BINARY, ''],
            [PHP_BINARY, "bad\0arg"],
        ] as $command) {
            try {
                Process::run($command);
                self::fail('Invalid arguments must be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRejectsInvalidTimeoutsAndWorkingDirectories(): void
    {
        foreach ([0.0, -1.0] as $timeout) {
            try {
                Process::run([PHP_BINARY, '-v'], timeoutSeconds: $timeout);
                self::fail('Invalid timeouts must be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }

        foreach (['', "bad\0dir", $this->temporaryDirectory . '/missing'] as $directory) {
            try {
                Process::run([PHP_BINARY, '-v'], workingDirectory: $directory);
                self::fail('Invalid working directories must be rejected.');
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTemporaryOutputFilesAreRemoved(): void
    {
        $before = $this->temporaryOutputs();

        Process::run([PHP_BINARY, '-r', 'echo "ok";']);

        self::assertSame($before, $this->temporaryOutputs());
    }

    /** @return list<string> */
    private function temporaryOutputs(): array
    {
        $paths = array_merge(
            glob(sys_get_temp_dir() . '/omega-proc-out-*') ?: [],
            glob(sys_get_temp_dir() . '/omega-proc-err-*') ?: [],
        );
        sort($paths);

        return $paths;
    }
}
