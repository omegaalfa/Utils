<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Cli;

use InvalidArgumentException;
use Throwable;

final class Application
{
    /**
     * @var array<string, array{
     *     description: string,
     *     handler: callable(Input, Output): int
     * }>
     */
    private array $commands = [];

    public function __construct(
        private readonly string $name = 'Console',
        private readonly string $version = '1.0.0',
    ) {
    }

    /** @param callable(Input, Output): int $handler */
    public function command(string $name, string $description, callable $handler): self
    {
        if (preg_match('/^[a-z][a-z0-9]*(?::[a-z][a-z0-9-]*)*$/D', $name) !== 1) {
            throw new InvalidArgumentException("Invalid command name: {$name}");
        }
        if (isset($this->commands[$name])) {
            throw new InvalidArgumentException("Command is already registered: {$name}");
        }

        $this->commands[$name] = [
            'description' => $description,
            'handler' => $handler,
        ];

        return $this;
    }

    /** @param list<string>|null $argv */
    public function run(?array $argv = null, ?Output $output = null): int
    {
        $output ??= new Output();
        if ($argv === null) {
            $argv = [];
            $serverArguments = $_SERVER['argv'] ?? [];
            if (is_array($serverArguments)) {
                foreach ($serverArguments as $argument) {
                    if (is_string($argument)) {
                        $argv[] = $argument;
                    }
                }
            }
        }
        array_shift($argv);
        $name = array_shift($argv);

        if ($name === null || $name === 'list' || $name === '--help' || $name === '-h') {
            $this->renderHelp($output);
            return 0;
        }

        if (!isset($this->commands[$name])) {
            $output->error("Command not found: {$name}");
            $this->renderHelp($output);
            return 1;
        }

        if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
            $output->writeln($name . '  ' . $this->commands[$name]['description']);
            return 0;
        }

        try {
            return ($this->commands[$name]['handler'])(new Input($argv), $output);
        } catch (Throwable $exception) {
            $output->error($exception->getMessage());
            return 1;
        }
    }

    private function renderHelp(Output $output): void
    {
        $output->writeln("{$this->name} {$this->version}");
        $output->writeln('Usage: php console <command> [arguments] [options]');
        $output->writeln();

        $rows = [];
        foreach ($this->commands as $name => $command) {
            $rows[] = [$name, $command['description']];
        }

        if ($rows !== []) {
            $output->table(['Command', 'Description'], $rows);
        }
    }
}
