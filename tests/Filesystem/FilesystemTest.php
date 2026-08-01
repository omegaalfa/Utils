<?php

declare(strict_types=1);

namespace Tests\Filesystem;

use InvalidArgumentException;
use Omegaalfa\Utils\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FilesystemTest extends TestCase
{
    private string $root;
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/omega-fs-' . bin2hex(random_bytes(8));
        $this->filesystem = new Filesystem($this->root);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            assert($item instanceof \SplFileInfo);
            $item->isDir() && !$item->isLink()
                ? rmdir($item->getPathname())
                : unlink($item->getPathname());
        }
        rmdir($this->root);
    }

    public function testWritesReadsAndReportsMetadata(): void
    {
        $this->filesystem->write('storage/log.txt', 'Conteúdo');

        self::assertTrue($this->filesystem->exists('storage/log.txt'));
        self::assertSame('Conteúdo', $this->filesystem->read('storage/log.txt'));
        self::assertSame(strlen('Conteúdo'), $this->filesystem->size('storage/log.txt'));
        self::assertGreaterThan(0, $this->filesystem->lastModified('storage/log.txt'));
        self::assertSame(realpath($this->root), $this->filesystem->getRoot());
    }

    public function testSupportsNonAtomicWriteAndPermissions(): void
    {
        $this->filesystem->write('plain.txt', 'first', 0600, atomic: false);
        $this->filesystem->write('plain.txt', 'second', 0640, atomic: false);

        self::assertSame('second', $this->filesystem->read('plain.txt'));

        if (PHP_OS_FAMILY !== 'Windows') {
            self::assertSame(0640, $this->filesystem->permissions('plain.txt'));
            $this->filesystem->changePermissions('plain.txt', 0600);
            self::assertSame(0600, $this->filesystem->permissions('plain.txt'));
        }
    }

    public function testCopiesMovesAndDeletesFiles(): void
    {
        $this->filesystem->write('source.txt', 'payload');
        $this->filesystem->copy('source.txt', 'backup/source.txt');

        self::assertSame('payload', $this->filesystem->read('backup/source.txt'));

        $this->filesystem->move('backup/source.txt', 'archive/final.txt');
        self::assertFalse($this->filesystem->exists('backup/source.txt'));
        self::assertSame('payload', $this->filesystem->read('archive/final.txt'));

        $this->filesystem->delete('source.txt');
        $this->filesystem->delete('source.txt');
        self::assertFalse($this->filesystem->exists('source.txt'));
    }

    public function testOverwriteMustBeExplicit(): void
    {
        $this->filesystem->write('first.txt', 'first');
        $this->filesystem->write('second.txt', 'second');

        try {
            $this->filesystem->copy('first.txt', 'second.txt');
            self::fail('Copy should not overwrite by default.');
        } catch (RuntimeException) {
            self::addToAssertionCount(1);
        }

        $this->filesystem->copy('first.txt', 'second.txt', overwrite: true);
        self::assertSame('first', $this->filesystem->read('second.txt'));

        $this->filesystem->write('third.txt', 'third');
        $this->filesystem->move('third.txt', 'second.txt', overwrite: true);
        self::assertSame('third', $this->filesystem->read('second.txt'));
    }

    public function testFindsFilesDeterministically(): void
    {
        $this->filesystem->write('root.txt', '');
        $this->filesystem->write('nested/b.txt', '');
        $this->filesystem->write('nested/a.txt', '');

        self::assertSame(['root.txt'], $this->filesystem->files());
        self::assertSame(
            ['nested/a.txt', 'nested/b.txt', 'root.txt'],
            $this->filesystem->files('.', recursive: true),
        );
    }

    public function testCreatesDirectoriesAndStreamsFiles(): void
    {
        $this->filesystem->createDirectory('data/deep');
        $stream = $this->filesystem->stream('data/deep/value.txt', 'w+b');

        $stream->write('streamed');
        $stream->rewind();
        self::assertSame('streamed', $stream->getContents());
        $stream->close();
    }

    public function testRejectsTraversalAbsolutePathsAndNulBytes(): void
    {
        foreach (['../secret', '/etc/passwd', "bad\0path"] as $path) {
            try {
                $this->filesystem->read($path);
                self::fail("Path {$path} should be rejected.");
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testRejectsSymlinkEscape(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Symbolic link setup differs on Windows.');
        }

        $outside = sys_get_temp_dir() . '/omega-fs-outside-' . bin2hex(random_bytes(4));
        mkdir($outside, 0700);
        file_put_contents($outside . '/secret.txt', 'secret');
        symlink($outside, $this->root . '/link');

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('does not exist inside filesystem root');
            $this->filesystem->read('link/secret.txt');
        } finally {
            unlink($this->root . '/link');
            unlink($outside . '/secret.txt');
            rmdir($outside);
        }
    }

    public function testRefusesDirectoryDeletionThroughFileApi(): void
    {
        $this->filesystem->createDirectory('directory');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to delete directory');
        $this->filesystem->delete('directory');
    }

    public function testRejectsInvalidPermissions(): void
    {
        $this->filesystem->write('file.txt', 'data');

        $this->expectException(InvalidArgumentException::class);
        $this->filesystem->changePermissions('file.txt', 01000);
    }
}
