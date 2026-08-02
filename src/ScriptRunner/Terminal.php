<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\ScriptRunner;

use InvalidArgumentException;
use RuntimeException;

final readonly class Terminal
{
    /** @var resource */
    private mixed $input;

    /** @var resource */
    private mixed $output;

    /** @var resource */
    private mixed $error;

    /** @param resource|null $input @param resource|null $output @param resource|null $error */
    public function __construct(mixed $input = null, mixed $output = null, mixed $error = null)
    {
        $input ??= STDIN;
        $output ??= STDOUT;
        $error ??= STDERR;

        if (!is_resource($input) || get_resource_type($input) !== 'stream') {
            throw new InvalidArgumentException('Terminal input must be a stream resource.');
        }
        if (!is_resource($output) || get_resource_type($output) !== 'stream') {
            throw new InvalidArgumentException('Terminal output must be a stream resource.');
        }
        if (!is_resource($error) || get_resource_type($error) !== 'stream') {
            throw new InvalidArgumentException('Terminal error must be a stream resource.');
        }

        $this->input = $input;
        $this->output = $output;
        $this->error = $error;
    }

    /**
     * @return void
     */
    public function clear(): void
    {
        if (function_exists('stream_isatty') && stream_isatty($this->outputStream())) {
            $this->write("\033[2J\033[H");
        }
    }

    /**
     * @param string $title
     * @return void
     */
    public function title(string $title): void
    {
        $border = str_repeat('=', max(29, strlen($title) + 2));
        $this->writeln($border);
        $this->writeln(" {$title}");
        $this->writeln($border);
        $this->writeln();
    }

    /**
     * @param string $text
     * @return void
     */
    public function write(string $text): void
    {
        if (fwrite($this->outputStream(), $text) === false) {
            throw new RuntimeException('Unable to write to terminal output.');
        }
    }

    /**
     * @param string $text
     * @return void
     */
    public function writeln(string $text = ''): void
    {
        $this->write($text . PHP_EOL);
    }

    /**
     * @param string $text
     * @return void
     */
    public function error(string $text): void
    {
        if (fwrite($this->errorStream(), $text . PHP_EOL) === false) {
            throw new RuntimeException('Unable to write to terminal error output.');
        }
    }

    /**
     * @return string|null
     */
    public function read(): ?string
    {
        $line = fgets($this->inputStream());
        return $line === false ? null : trim($line);
    }

    /** @return resource */
    private function inputStream()
    {
        if (!is_resource($this->input)) {
            throw new RuntimeException('Terminal input is no longer available.');
        }
        return $this->input;
    }

    /** @return resource */
    private function outputStream()
    {
        if (!is_resource($this->output)) {
            throw new RuntimeException('Terminal output is no longer available.');
        }
        return $this->output;
    }

    /** @return resource */
    private function errorStream()
    {
        if (!is_resource($this->error)) {
            throw new RuntimeException('Terminal error output is no longer available.');
        }
        return $this->error;
    }
}
