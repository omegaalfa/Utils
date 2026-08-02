<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\ScriptRunner;

use DirectoryIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final readonly class ScriptFinder
{
    /** @var list<string> */
    private array $roots;

    /** @param list<string> $directories */
    public function __construct(array $directories)
    {
        if ($directories === []) {
            throw new InvalidArgumentException('At least one script directory is required.');
        }

        $roots = [];
        foreach ($directories as $directory) {
            if ($directory === '' || str_contains($directory, "\0")) {
                throw new InvalidArgumentException('Script directory cannot be empty or contain NUL bytes.');
            }

            if (is_link($directory)) {
                throw new InvalidArgumentException("Symbolic links cannot be script roots: {$directory}");
            }

            $root = realpath($directory);
            if ($root === false || !is_dir($root) || !is_readable($root)) {
                throw new InvalidArgumentException("Script directory is not readable: {$directory}");
            }

            if (!in_array($root, $roots, true)) {
                $roots[] = rtrim($root, DIRECTORY_SEPARATOR);
            }
        }

        $this->roots = $roots;
    }

    /** @return list<array{name: string, path: string}> */
    public function roots(): array
    {
        $roots = [];
        foreach ($this->roots as $root) {
            $roots[] = ['name' => basename($root), 'path' => $root];
        }

        usort(
            $roots,
            static fn(array $left, array $right): int => strnatcasecmp($left['name'], $right['name']),
        );

        return $roots;
    }

    /**
     * @return array{
     *     directories: list<array{name: string, path: string}>,
     *     scripts: list<Script>
     * }
     */
    public function entries(string $directory): array
    {
        $resolved = $this->resolveDirectory($directory);
        $root = $this->rootFor($resolved);
        $directories = [];
        $scripts = [];

        foreach (new DirectoryIterator($resolved) as $entry) {
            if ($entry->isDot() || str_starts_with($entry->getFilename(), '.') || $entry->isLink()) {
                continue;
            }

            $path = $entry->getRealPath();
            if ($path === false || !$this->isWithin($path, $root)) {
                continue;
            }

            if ($entry->isDir()) {
                $directories[] = ['name' => $entry->getFilename(), 'path' => $path];
                continue;
            }

            if ($entry->isFile() && strtolower($entry->getExtension()) === 'php') {
                $scripts[] = new Script($path, $this->relativePath($path, $root));
            }
        }

        usort(
            $directories,
            static fn(array $left, array $right): int => strnatcasecmp($left['name'], $right['name']),
        );
        usort(
            $scripts,
            static fn(Script $left, Script $right): int => strnatcasecmp($left->name(), $right->name()),
        );

        return ['directories' => $directories, 'scripts' => $scripts];
    }

    /**
     * @return bool
     */
    public function hasScripts(): bool
    {
        foreach ($this->roots as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $entry) {
                assert($entry instanceof SplFileInfo);
                if (
                    !$entry->isLink()
                    && $entry->isFile()
                    && strtolower($entry->getExtension()) === 'php'
                    && !$this->hasHiddenSegment($entry->getPathname(), $root)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<string> */
    public function allowedDirectories(): array
    {
        return $this->roots;
    }

    /**
     * @param string $directory
     * @return string
     */
    private function resolveDirectory(string $directory): string
    {
        $resolved = realpath($directory);
        if ($resolved === false || !is_dir($resolved)) {
            throw new RuntimeException("Directory does not exist: {$directory}");
        }

        $this->rootFor($resolved);
        return $resolved;
    }

    /**
     * @param string $path
     * @return string
     */
    private function rootFor(string $path): string
    {
        foreach ($this->roots as $root) {
            if ($this->isWithin($path, $root)) {
                return $root;
            }
        }

        throw new RuntimeException('Path is outside the registered script directories.');
    }

    /**
     * @param string $path
     * @param string $root
     * @return bool
     */
    private function isWithin(string $path, string $root): bool
    {
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    /**
     * @param string $path
     * @param string $root
     * @return string
     */
    private function relativePath(string $path, string $root): string
    {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root) + 1));
    }

    /**
     * @param string $path
     * @param string $root
     * @return bool
     */
    private function hasHiddenSegment(string $path, string $root): bool
    {
        $relative = substr($path, strlen($root) + 1);
        foreach (explode(DIRECTORY_SEPARATOR, $relative) as $segment) {
            if (str_starts_with($segment, '.')) {
                return true;
            }
        }

        return false;
    }
}
