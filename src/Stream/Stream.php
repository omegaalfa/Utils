<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Stream;

use Generator;
use InvalidArgumentException;
use RuntimeException;

/**
 * Small, dependency-free wrapper around a PHP stream resource.
 */
final class Stream
{
    /** @var resource|null */
    private mixed $resource = null;

    /**
     * @param mixed $resource
     * @param string $mode
     */
    public function __construct(mixed $resource = 'php://temp', string $mode = 'r+b')
    {
        $this->attach($resource, $mode);
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->close();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        try {
            if (!$this->isReadable()) {
                return '';
            }
            if ($this->isSeekable()) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (RuntimeException) {
            return '';
        }
    }

    /**
     * @param mixed $resource
     * @param string $mode
     * @return void
     */
    public function attach(mixed $resource, string $mode = 'r+b'): void
    {
        if (is_string($resource)) {
            $opened = @fopen($resource, $mode);
            if ($opened === false) {
                throw new RuntimeException("Unable to open stream: {$resource}");
            }
            $resource = $opened;
        }

        if (!is_resource($resource) || get_resource_type($resource) !== 'stream') {
            throw new InvalidArgumentException('Expected a stream resource or stream URI.');
        }

        $this->close();
        $this->resource = $resource;
    }

    /** @return resource */
    public function getResource()
    {
        return $this->requireResource();
    }

    /** @return resource|null */
    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;

        return is_resource($resource) ? $resource : null;
    }

    /**
     * @return void
     */
    public function close(): void
    {
        $resource = $this->detach();
        if ($resource !== null) {
            fclose($resource);
        }
    }

    /**
     * @return int|null
     */
    public function getSize(): ?int
    {
        $statistics = fstat($this->requireResource());

        return $statistics === false ? null : $statistics['size'];
    }

    /**
     * @return int
     */
    public function tell(): int
    {
        $position = ftell($this->requireResource());
        if ($position === false) {
            throw new RuntimeException('Unable to determine the stream position.');
        }

        return $position;
    }

    /**
     * @return bool
     */
    public function eof(): bool
    {
        return feof($this->requireResource());
    }

    /**
     * @return bool
     */
    public function isSeekable(): bool
    {
        return (bool)$this->metadata()['seekable'];
    }

    /**
     * @param int $offset
     * @param int $whence
     * @return void
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable() || fseek($this->requireResource(), $offset, $whence) !== 0) {
            throw new RuntimeException('Unable to seek in the stream.');
        }
    }

    /**
     * @return void
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * @return bool
     */
    public function isWritable(): bool
    {
        $mode = $this->mode();
        return str_contains($mode, '+') || str_contains('waxc', $mode[0] ?? '');
    }

    /**
     * @param string $data
     * @return int
     */
    public function write(string $data): int
    {
        if (!$this->isWritable()) {
            throw new RuntimeException('Stream is not writable.');
        }

        $written = fwrite($this->requireResource(), $data);
        if ($written === false) {
            throw new RuntimeException('Unable to write to the stream.');
        }

        return $written;
    }

    /**
     * @return bool
     */
    public function isReadable(): bool
    {
        $mode = $this->mode();
        return ($mode[0] ?? '') === 'r' || str_contains($mode, '+');
    }

    /**
     * @param int $length
     * @return string
     */
    public function read(int $length): string
    {
        if ($length < 0) {
            throw new InvalidArgumentException('Read length must be greater than or equal to zero.');
        }
        if ($length === 0) {
            return '';
        }
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable.');
        }

        $contents = fread($this->requireResource(), $length);
        if ($contents === false) {
            throw new RuntimeException('Unable to read from the stream.');
        }

        return $contents;
    }

    /**
     * @return string
     */
    public function getContents(): string
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable.');
        }

        $contents = stream_get_contents($this->requireResource());
        if ($contents === false) {
            throw new RuntimeException('Unable to read from the stream.');
        }

        return $contents;
    }

    /** @return array<string, mixed>|mixed|null */
    public function getMetadata(?string $key = null): mixed
    {
        $metadata = $this->metadata();

        return $key === null ? $metadata : ($metadata[$key] ?? null);
    }

    /** @return Generator<int, string> */
    public function lines(bool $rewind = true): Generator
    {
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable.');
        }
        if ($rewind) {
            $this->rewind();
        }

        while (($line = fgets($this->requireResource())) !== false) {
            yield $line;
        }
    }

    /** @return Generator<int, list<string|null>> */
    public function csvRows(
        string $separator = ',',
        string $enclosure = '"',
        string $escape = '',
        bool   $rewind = true,
    ): Generator
    {
        if (strlen($separator) !== 1 || strlen($enclosure) !== 1 || strlen($escape) > 1) {
            throw new InvalidArgumentException('CSV controls must be one-byte characters.');
        }
        if (!$this->isReadable()) {
            throw new RuntimeException('Stream is not readable.');
        }
        if ($rewind) {
            $this->rewind();
        }

        while (($row = fgetcsv($this->requireResource(), null, $separator, $enclosure, $escape)) !== false) {
            yield $row;
        }
    }

    /**
     * @return int
     */
    public function countLines(): int
    {
        $count = 0;
        foreach ($this->lines() as $_) {
            $count++;
        }

        return $count;
    }

    /** @return resource */
    private function requireResource()
    {
        if (!is_resource($this->resource) || get_resource_type($this->resource) !== 'stream') {
            throw new RuntimeException('Stream is detached or closed.');
        }

        return $this->resource;
    }

    /** @return array<string, mixed> */
    private function metadata(): array
    {
        return stream_get_meta_data($this->requireResource());
    }

    /**
     * @return string
     */
    private function mode(): string
    {
        $mode = $this->metadata()['mode'] ?? '';

        return is_string($mode) ? $mode : '';
    }
}
