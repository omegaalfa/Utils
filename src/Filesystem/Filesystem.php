<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Filesystem;

use InvalidArgumentException;
use Omegaalfa\Utils\Stream\Stream;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class Filesystem
{
    private readonly string $root;

    public function __construct(?string $root = null)
    {
        $root ??= getcwd() ?: throw new RuntimeException('Unable to determine working directory.');

        if (!is_dir($root) && !@mkdir($root, 0755, true) && !is_dir($root)) {
            throw new RuntimeException("Unable to create filesystem root: {$root}");
        }

        $canonical = realpath($root);
        if ($canonical === false || !is_dir($canonical)) {
            throw new RuntimeException("Invalid filesystem root: {$root}");
        }

        $this->root = rtrim($canonical, DIRECTORY_SEPARATOR);
    }

    public function getRoot(): string
    {
        return $this->root;
    }

    public function exists(string $path): bool
    {
        return file_exists($this->path($path)) || is_link($this->path($path));
    }

    public function read(string $path): string
    {
        $resolved = $this->existingPath($path);
        if (!is_file($resolved) || !is_readable($resolved)) {
            throw new RuntimeException("Path is not a readable file: {$path}");
        }

        $contents = file_get_contents($resolved);
        if ($contents === false) {
            throw new RuntimeException("Unable to read file: {$path}");
        }

        return $contents;
    }

    public function write(
        string $path,
        string $contents,
        int $permissions = 0644,
        bool $atomic = true,
    ): void {
        $this->validatePermissions($permissions);
        $destination = $this->destinationPath($path);

        if (!$atomic) {
            if (file_put_contents($destination, $contents, LOCK_EX) === false) {
                throw new RuntimeException("Unable to write file: {$path}");
            }
            $this->applyPermissions($destination, $permissions);
            return;
        }

        $temporary = tempnam(dirname($destination), '.omega-write-');
        if ($temporary === false) {
            throw new RuntimeException("Unable to create temporary file for: {$path}");
        }

        try {
            if (file_put_contents($temporary, $contents, LOCK_EX) === false) {
                throw new RuntimeException("Unable to write temporary file for: {$path}");
            }
            $this->applyPermissions($temporary, $permissions);

            if (!@rename($temporary, $destination)) {
                throw new RuntimeException("Unable to atomically replace file: {$path}");
            }
        } finally {
            if (is_file($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function createDirectory(
        string $path,
        int $permissions = 0755,
        bool $recursive = true,
    ): void {
        $this->validatePermissions($permissions);
        $directory = $this->path($path);

        if (is_dir($directory)) {
            return;
        }
        if (!@mkdir($directory, $permissions, $recursive) && !is_dir($directory)) {
            throw new RuntimeException("Unable to create directory: {$path}");
        }
    }

    public function copy(string $source, string $destination, bool $overwrite = false): void
    {
        $sourcePath = $this->existingPath($source);
        if (!is_file($sourcePath)) {
            throw new RuntimeException("Source is not a file: {$source}");
        }

        $destinationPath = $this->destinationPath($destination);
        if (!$overwrite && (file_exists($destinationPath) || is_link($destinationPath))) {
            throw new RuntimeException("Destination already exists: {$destination}");
        }
        if (!@copy($sourcePath, $destinationPath)) {
            throw new RuntimeException("Unable to copy {$source} to {$destination}.");
        }
    }

    public function move(string $source, string $destination, bool $overwrite = false): void
    {
        $sourcePath = $this->existingPath($source);
        $destinationPath = $this->destinationPath($destination);

        if (!file_exists($destinationPath) && !is_link($destinationPath)) {
            if (!@rename($sourcePath, $destinationPath)) {
                throw new RuntimeException("Unable to move {$source} to {$destination}.");
            }
            return;
        }

        if (!$overwrite) {
            throw new RuntimeException("Destination already exists: {$destination}");
        }
        if (is_dir($destinationPath) || !is_file($sourcePath)) {
            throw new RuntimeException('Overwrite move supports files only.');
        }

        if (@rename($sourcePath, $destinationPath)) {
            return;
        }

        $backup = tempnam(dirname($destinationPath), '.omega-move-');
        if ($backup === false || !@unlink($backup)) {
            throw new RuntimeException("Unable to prepare destination backup: {$destination}");
        }
        if (!@rename($destinationPath, $backup)) {
            throw new RuntimeException("Unable to preserve destination: {$destination}");
        }

        if (!@rename($sourcePath, $destinationPath)) {
            if (!@rename($backup, $destinationPath)) {
                throw new RuntimeException(
                    "Move failed and destination rollback also failed: {$destination}"
                );
            }
            throw new RuntimeException("Unable to move {$source} to {$destination}; destination restored.");
        }

        if (!@unlink($backup)) {
            throw new RuntimeException(
                "Move completed but the destination backup could not be removed: {$backup}"
            );
        }
    }

    public function delete(string $path): void
    {
        $candidate = $this->path($path);
        if (!file_exists($candidate) && !is_link($candidate)) {
            return;
        }
        if (is_dir($candidate) && !is_link($candidate)) {
            throw new RuntimeException("Refusing to delete directory as file: {$path}");
        }
        if (!@unlink($candidate)) {
            throw new RuntimeException("Unable to delete file: {$path}");
        }
    }

    /** @return list<string> */
    public function files(string $directory = '.', bool $recursive = false): array
    {
        $resolved = $this->existingPath($directory);
        if (!is_dir($resolved)) {
            throw new RuntimeException("Path is not a directory: {$directory}");
        }

        $iterator = $recursive
            ? new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($resolved, RecursiveDirectoryIterator::SKIP_DOTS),
            )
            : new \IteratorIterator(new \FilesystemIterator($resolved, \FilesystemIterator::SKIP_DOTS));

        $files = [];
        foreach ($iterator as $file) {
            assert($file instanceof SplFileInfo);
            if ($file->isFile()) {
                $files[] = str_replace(
                    DIRECTORY_SEPARATOR,
                    '/',
                    substr($file->getPathname(), strlen($this->root) + 1),
                );
            }
        }

        sort($files, SORT_STRING);
        return $files;
    }

    public function size(string $path): int
    {
        $size = filesize($this->existingPath($path));
        if ($size === false) {
            throw new RuntimeException("Unable to determine file size: {$path}");
        }

        return $size;
    }

    public function lastModified(string $path): int
    {
        $timestamp = filemtime($this->existingPath($path));
        if ($timestamp === false) {
            throw new RuntimeException("Unable to determine modification time: {$path}");
        }

        return $timestamp;
    }

    public function permissions(string $path): int
    {
        $permissions = fileperms($this->existingPath($path));
        if ($permissions === false) {
            throw new RuntimeException("Unable to determine permissions: {$path}");
        }

        return $permissions & 0777;
    }

    public function changePermissions(string $path, int $permissions): void
    {
        $resolved = $this->existingPath($path);
        $this->applyPermissions($resolved, $permissions);
    }

    public function stream(string $path, string $mode = 'rb'): Stream
    {
        $resolved = str_contains($mode, 'w')
            || str_contains($mode, 'a')
            || str_contains($mode, 'x')
            || str_contains($mode, 'c')
            || str_contains($mode, '+')
            ? $this->destinationPath($path)
            : $this->existingPath($path);

        return new Stream($resolved, $mode);
    }

    private function path(string $path): string
    {
        if ($path === '' || str_contains($path, "\0")) {
            throw new InvalidArgumentException('Filesystem path cannot be empty or contain NUL bytes.');
        }

        $path = str_replace('\\', '/', $path);
        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:\//D', $path) === 1) {
            throw new InvalidArgumentException('Filesystem path must be relative to the configured root.');
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                throw new InvalidArgumentException('Parent directory traversal is not allowed.');
            }
            $segments[] = $segment;
        }

        return $segments === []
            ? $this->root
            : $this->root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments);
    }

    private function existingPath(string $path): string
    {
        $candidate = $this->path($path);
        $resolved = realpath($candidate);

        if ($resolved === false || !$this->isWithinRoot($resolved)) {
            throw new RuntimeException("Path does not exist inside filesystem root: {$path}");
        }

        return $resolved;
    }

    private function destinationPath(string $path): string
    {
        $destination = $this->path($path);
        $parent = dirname($destination);
        $relativeParent = substr($parent, strlen($this->root));
        $this->createDirectory($relativeParent === '' ? '.' : ltrim($relativeParent, DIRECTORY_SEPARATOR));

        $canonicalParent = realpath($parent);
        if ($canonicalParent === false || !$this->isWithinRoot($canonicalParent)) {
            throw new RuntimeException("Destination escapes filesystem root: {$path}");
        }

        return $destination;
    }

    private function isWithinRoot(string $path): bool
    {
        return $path === $this->root
            || str_starts_with($path, $this->root . DIRECTORY_SEPARATOR);
    }

    private function applyPermissions(string $path, int $permissions): void
    {
        $this->validatePermissions($permissions);
        if (!@chmod($path, $permissions)) {
            throw new RuntimeException("Unable to change permissions: {$path}");
        }
    }

    private function validatePermissions(int $permissions): void
    {
        if ($permissions < 0 || $permissions > 0777) {
            throw new InvalidArgumentException('Permissions must be between 0000 and 0777.');
        }
    }
}
