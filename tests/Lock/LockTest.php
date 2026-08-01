<?php

declare(strict_types=1);

namespace Tests\Lock;

use InvalidArgumentException;
use Omegaalfa\Utils\Lock\FileLock;
use Omegaalfa\Utils\Lock\LockFactory;
use Omegaalfa\Utils\Lock\LockInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LockTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/omega-lock-test-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        foreach (new \FilesystemIterator($this->directory) as $file) {
            assert($file instanceof \SplFileInfo);
            unlink($file->getPathname());
        }
        rmdir($this->directory);
    }

    public function testFactoryCreatesCanonicalPrivateDirectoryAndLock(): void
    {
        $factory = new LockFactory($this->directory);
        $lock = $factory->create('generate-monthly-report');

        self::assertDirectoryExists($this->directory);
        self::assertSame(realpath($this->directory), $factory->getDirectory());
        self::assertInstanceOf(LockInterface::class, $lock);
        self::assertInstanceOf(FileLock::class, $lock);
    }

    public function testAcquireIsExclusiveAndReleaseIsIdempotent(): void
    {
        $factory = new LockFactory($this->directory);
        $first = $factory->create('operation');
        $second = $factory->create('operation');

        self::assertTrue($first->acquire());
        self::assertTrue($first->isAcquired());
        self::assertTrue($first->acquire());
        self::assertFalse($second->acquire());

        $first->release();
        $first->release();

        self::assertFalse($first->isAcquired());
        self::assertTrue($second->acquire());
        $second->release();
    }

    public function testDifferentNamesDoNotContend(): void
    {
        $factory = new LockFactory($this->directory);
        $first = $factory->create('first');
        $second = $factory->create('second');

        self::assertTrue($first->acquire());
        self::assertTrue($second->acquire());

        $first->release();
        $second->release();
    }

    public function testNameIsHashedAndCannotTraverseDirectories(): void
    {
        $factory = new LockFactory($this->directory);
        $lock = $factory->create('../../outside');
        self::assertTrue($lock->acquire());
        $lock->release();

        $files = iterator_to_array(new \FilesystemIterator($this->directory));
        self::assertCount(1, $files);

        $file = array_values($files)[0];
        assert($file instanceof \SplFileInfo);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}\.lock$/', $file->getFilename());
    }

    public function testDestructorReleasesLock(): void
    {
        $factory = new LockFactory($this->directory);
        $lock = $factory->create('destructor');
        self::assertTrue($lock->acquire());

        unset($lock);

        $replacement = $factory->create('destructor');
        self::assertTrue($replacement->acquire());
        $replacement->release();
    }

    public function testLockCoordinatesSeparateProcesses(): void
    {
        $factory = new LockFactory($this->directory);
        $lock = $factory->create('cross-process');
        self::assertTrue($lock->acquire());

        self::assertSame('blocked', $this->acquireFromChildProcess('cross-process'));

        $lock->release();

        self::assertSame('acquired', $this->acquireFromChildProcess('cross-process'));
    }

    public function testRejectsEmptyNameAndDirectory(): void
    {
        $factory = new LockFactory($this->directory);

        try {
            $factory->create('');
            self::fail('Empty lock name should be rejected.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }

        $this->expectException(InvalidArgumentException::class);
        new LockFactory('');
    }

    public function testRejectsSymbolicLinkDirectory(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Symbolic link semantics differ on Windows.');
        }

        $target = $this->directory . '-target';
        mkdir($target, 0700);
        symlink($target, $this->directory);

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('symbolic link');
            new LockFactory($this->directory);
        } finally {
            unlink($this->directory);
            rmdir($target);
        }
    }

    private function acquireFromChildProcess(string $name): string
    {
        $autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
        $script = sprintf(
            <<<'PHP'
            require %s;
            $factory = new \Omegaalfa\Utils\Lock\LockFactory(%s);
            $lock = $factory->create(%s);
            if (!$lock->acquire()) {
                echo 'blocked';
                exit;
            }
            echo 'acquired';
            $lock->release();
            PHP,
            var_export($autoload, true),
            var_export($this->directory, true),
            var_export($name, true),
        );

        $process = proc_open(
            [PHP_BINARY, '-r', $script],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
        );
        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, $stderr === false ? '' : $stderr);
        self::assertIsString($stdout);

        return $stdout;
    }
}
