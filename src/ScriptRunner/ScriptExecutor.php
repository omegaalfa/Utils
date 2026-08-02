<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\ScriptRunner;

use InvalidArgumentException;
use RuntimeException;

final readonly class ScriptExecutor
{
    /** @var list<string> */
    private array $allowedDirectories;
    private string $phpBinary;

    /** @param list<string> $allowedDirectories */
    public function __construct(array $allowedDirectories, string $phpBinary = PHP_BINARY)
    {
        if ($phpBinary === '' || str_contains($phpBinary, "\0")) {
            throw new InvalidArgumentException('PHP binary cannot be empty or contain NUL bytes.');
        }
        if ($allowedDirectories === []) {
            throw new InvalidArgumentException('At least one allowed directory is required.');
        }

        $canonical = [];
        foreach ($allowedDirectories as $directory) {
            if (is_link($directory)) {
                throw new InvalidArgumentException("Symbolic links cannot be allowed directories: {$directory}");
            }
            $path = realpath($directory);
            if ($path === false || !is_dir($path)) {
                throw new InvalidArgumentException("Allowed directory does not exist: {$directory}");
            }
            $path = rtrim($path, DIRECTORY_SEPARATOR);
            if (!in_array($path, $canonical, true)) {
                $canonical[] = $path;
            }
        }
        $this->allowedDirectories = $canonical;
        $this->phpBinary = $phpBinary;
    }

    public function execute(Script $script): ScriptExecutionResult
    {
        $path = $this->validatedPath($script);
        $stdoutPath = tempnam(sys_get_temp_dir(), 'omega-run-out-');
        $stderrPath = tempnam(sys_get_temp_dir(), 'omega-run-err-');
        if ($stdoutPath === false || $stderrPath === false) {
            $this->removeTemporary($stdoutPath);
            $this->removeTemporary($stderrPath);
            throw new RuntimeException('Unable to create process output files.');
        }

        try {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['file', $stdoutPath, 'wb'],
                2 => ['file', $stderrPath, 'wb'],
            ];

            $path = $this->validatedPath($script);
            $process = @proc_open(
                [$this->phpBinary, $path],
                $descriptors,
                $pipes,
                dirname($path),
                null,
                ['bypass_shell' => true],
            );
            if (!is_resource($process)) {
                throw new RuntimeException("Unable to start PHP process: {$this->phpBinary}");
            }
            if (isset($pipes[0]) && is_resource($pipes[0])) {
                fclose($pipes[0]);
            }

            $exitCode = proc_close($process);
            $stdout = file_get_contents($stdoutPath);
            $stderr = file_get_contents($stderrPath);
            if ($stdout === false || $stderr === false) {
                throw new RuntimeException('Unable to read process output.');
            }
            return new ScriptExecutionResult($script, $stdout, $stderr, $exitCode);
        } finally {
            $this->removeTemporary($stdoutPath);
            $this->removeTemporary($stderrPath);
        }
    }

    private function validatedPath(Script $script): string
    {
        if (is_link($script->path)) {
            throw new RuntimeException("Symbolic links cannot be executed: {$script->path}");
        }
        $path = realpath($script->path);
        if (
            $path === false
            || !is_file($path)
            || !is_readable($path)
            || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php'
        ) {
            throw new RuntimeException("Script is not a readable PHP file: {$script->path}");
        }
        if (!$this->isAllowed($path)) {
            throw new RuntimeException('Refusing to execute a script outside the registered directories.');
        }
        if (!$script->hasSameIdentity($path)) {
            throw new RuntimeException('The selected script was replaced before execution.');
        }
        return $path;
    }

    private function isAllowed(string $path): bool
    {
        foreach ($this->allowedDirectories as $directory) {
            if ($path === $directory || str_starts_with($path, $directory . DIRECTORY_SEPARATOR)) {
                return true;
            }
        }
        return false;
    }

    private function removeTemporary(string|false $path): void
    {
        if (is_string($path) && is_file($path)) {
            @unlink($path);
        }
    }
}
