<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\ScriptRunner;

final readonly class ScriptExecutionResult
{
    /**
     * @param Script $script
     * @param string $stdout
     * @param string $stderr
     * @param int $exitCode
     */
    public function __construct(
        public Script $script,
        public string $stdout,
        public string $stderr,
        public int    $exitCode,
    )
    {
    }
}
