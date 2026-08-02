<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\ScriptRunner;

use InvalidArgumentException;

final readonly class Script
{
    private int $device;
    private int $inode;

    public function __construct(public string $path, public string $relativePath)
    {
        if (is_link($path)) {
            throw new InvalidArgumentException("Symbolic links cannot be selected as scripts: {$path}");
        }
        $metadata = @stat($path);
        if ($metadata === false || !is_file($path)) {
            throw new InvalidArgumentException("Script does not exist: {$path}");
        }
        $this->device = $metadata['dev'];
        $this->inode = $metadata['ino'];
    }

    public function name(): string
    {
        return basename($this->path);
    }

    public function hasSameIdentity(string $path): bool
    {
        $metadata = @stat($path);
        return $metadata !== false
            && $metadata['dev'] === $this->device
            && $metadata['ino'] === $this->inode;
    }
}
