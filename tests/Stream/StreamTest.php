<?php

declare(strict_types=1);

namespace Tests\Stream;

use InvalidArgumentException;
use Omegaalfa\Utils\Stream\Stream;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class StreamTest extends TestCase
{
    public function testDefaultTemporaryStreamCanBeWrittenAndRead(): void
    {
        $stream = new Stream();

        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertSame(11, $stream->write('Omega Utils'));
        self::assertSame(11, $stream->getSize());

        $stream->rewind();
        self::assertSame('Omega', $stream->read(5));
        self::assertSame(' Utils', $stream->getContents());
        self::assertTrue($stream->eof());
    }

    public function testStringConversionReadsFromBeginning(): void
    {
        $stream = $this->streamContaining('complete content');
        $stream->read(8);

        self::assertSame('complete content', (string) $stream);
        $stream->close();
        self::assertSame('', (string) $stream);
    }

    public function testWrapsAnExistingResourceWithoutCopyingIt(): void
    {
        $resource = fopen('php://memory', 'r+b');
        self::assertIsResource($resource);
        fwrite($resource, 'resource');

        $stream = new Stream($resource);
        self::assertSame($resource, $stream->getResource());
    }

    public function testDetachTransfersOwnershipWithoutClosingResource(): void
    {
        $stream = $this->streamContaining('detached');
        $resource = $stream->detach();

        self::assertIsResource($resource);
        rewind($resource);
        self::assertSame('detached', stream_get_contents($resource));
        fclose($resource);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('detached or closed');
        $stream->tell();
    }

    public function testCloseIsIdempotent(): void
    {
        $stream = new Stream();
        $stream->close();
        $stream->close();

        $this->expectException(RuntimeException::class);
        $stream->getResource();
    }

    public function testAttachReplacesAndClosesPreviousResource(): void
    {
        $first = fopen('php://memory', 'r+b');
        $second = fopen('php://temp', 'r+b');
        self::assertIsResource($first);
        self::assertIsResource($second);

        $stream = new Stream($first);
        $stream->attach($second);

        self::assertFalse(is_resource($first));
        self::assertSame($second, $stream->getResource());
    }

    public function testRejectsInvalidResourcesAndUnreadablePaths(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Stream(123);
    }

    public function testRejectsPathThatCannotBeOpened(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to open stream');
        new Stream('/directory-that-does-not-exist/file.txt', 'rb');
    }

    public function testReadValidatesLength(): void
    {
        $stream = new Stream();
        self::assertSame('', $stream->read(0));

        $this->expectException(InvalidArgumentException::class);
        $stream->read(-1);
    }

    public function testEnforcesReadAndWriteModes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'omega-stream-');
        self::assertNotFalse($path);

        try {
            file_put_contents($path, 'read only');
            $stream = new Stream($path, 'rb');
            self::assertTrue($stream->isReadable());
            self::assertFalse($stream->isWritable());
            self::assertSame('read only', $stream->getContents());

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('not writable');
            $stream->write('no');
        } finally {
            unlink($path);
        }
    }

    public function testIteratesLinesWithoutClosingStream(): void
    {
        $stream = $this->streamContaining("first\nsecond\nthird");

        self::assertSame(["first\n", "second\n", 'third'], iterator_to_array($stream->lines()));
        self::assertSame(3, $stream->countLines());
    }

    public function testParsesCsvLazily(): void
    {
        $stream = $this->streamContaining("name,active\nOmega,true\n");

        self::assertSame(
            [['name', 'active'], ['Omega', 'true']],
            iterator_to_array($stream->csvRows())
        );
    }

    public function testRejectsInvalidCsvControls(): void
    {
        $stream = new Stream();
        $this->expectException(InvalidArgumentException::class);

        iterator_to_array($stream->csvRows(separator: '::'));
    }

    public function testSeekRejectsNonSeekableStream(): void
    {
        $sockets = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertNotFalse($sockets);
        $stream = new Stream($sockets[0]);

        try {
            self::assertFalse($stream->isSeekable());
            $this->expectException(RuntimeException::class);
            $stream->rewind();
        } finally {
            fclose($sockets[1]);
        }
    }

    private function streamContaining(string $contents): Stream
    {
        $stream = new Stream();
        $stream->write($contents);
        $stream->rewind();

        return $stream;
    }
}
