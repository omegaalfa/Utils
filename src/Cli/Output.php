<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Cli;

use InvalidArgumentException;
use RuntimeException;

final class Output
{
    private const array COLORS = [
        'black' => '30',
        'red' => '31',
        'green' => '32',
        'yellow' => '33',
        'blue' => '34',
        'magenta' => '35',
        'cyan' => '36',
        'white' => '37',
    ];

    /** @var resource */
    private mixed $stdout;

    /** @var resource */
    private mixed $stderr;

    /** @var resource */
    private mixed $stdin;

    public function __construct(
        mixed $stdout = null,
        mixed $stderr = null,
        mixed $stdin = null,
        private readonly ?bool $colors = null,
    ) {
        $this->stdout = $this->resource($stdout ?? STDOUT, 'stdout');
        $this->stderr = $this->resource($stderr ?? STDERR, 'stderr');
        $this->stdin = $this->resource($stdin ?? STDIN, 'stdin');
    }

    public function write(string $text): void
    {
        $this->send($this->stdout, $text);
    }

    public function writeln(string $text = ''): void
    {
        $this->write($text . PHP_EOL);
    }

    public function error(string $text): void
    {
        $this->send($this->stderr, $this->style($text, 'red') . PHP_EOL);
    }

    public function success(string $text): void
    {
        $this->writeln($this->style($text, 'green'));
    }

    public function style(string $text, string $color): string
    {
        if (!isset(self::COLORS[$color])) {
            throw new InvalidArgumentException("Unknown terminal color: {$color}");
        }
        if (!$this->supportsColors()) {
            return $text;
        }

        return "\033[" . self::COLORS[$color] . "m{$text}\033[0m";
    }

    public function ask(string $question, ?string $default = null): ?string
    {
        $suffix = $default === null ? ': ' : " [{$default}]: ";
        $this->write($question . $suffix);

        $answer = fgets($this->stdin);
        if ($answer === false) {
            return $default;
        }

        $answer = trim($answer);
        return $answer === '' ? $default : $answer;
    }

    /**
     * @param list<string> $headers
     * @param list<list<string|int|float|bool|null>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $normalized = [array_map(self::stringify(...), $headers)];
        foreach ($rows as $row) {
            $normalized[] = array_map(self::stringify(...), $row);
        }

        $columns = count($headers);
        /** @var array<int, int> $widths */
        $widths = array_fill(0, $columns, 0);

        foreach ($normalized as $row) {
            if (count($row) !== $columns) {
                throw new InvalidArgumentException('Every table row must match the header column count.');
            }
            foreach ($row as $column => $value) {
                $widths[$column] = max($widths[$column], mb_strwidth($value, 'UTF-8'));
            }
        }

        $this->tableBorder($widths);
        $this->tableRow($normalized[0], $widths);
        $this->tableBorder($widths);
        foreach (array_slice($normalized, 1) as $row) {
            $this->tableRow($row, $widths);
        }
        $this->tableBorder($widths);
    }

    private function supportsColors(): bool
    {
        if ($this->colors !== null) {
            return $this->colors;
        }

        return function_exists('stream_isatty') && stream_isatty($this->stdout);
    }

    /** @param resource $resource */
    private function send(mixed $resource, string $text): void
    {
        if (fwrite($resource, $text) === false) {
            throw new RuntimeException('Unable to write CLI output.');
        }
    }

    /** @param array<int, int> $widths */
    private function tableBorder(array $widths): void
    {
        $parts = array_map(static fn (int $width): string => str_repeat('-', $width + 2), $widths);
        $this->writeln('+' . implode('+', $parts) . '+');
    }

    /**
     * @param list<string> $row
     * @param array<int, int> $widths
     */
    private function tableRow(array $row, array $widths): void
    {
        $cells = [];
        foreach ($row as $index => $value) {
            $padding = $widths[$index] - mb_strwidth($value, 'UTF-8');
            $cells[] = ' ' . $value . str_repeat(' ', $padding + 1);
        }
        $this->writeln('|' . implode('|', $cells) . '|');
    }

    private static function stringify(string|int|float|bool|null $value): string
    {
        return match (true) {
            $value === null => '',
            $value === true => 'true',
            $value === false => 'false',
            default => (string) $value,
        };
    }

    /** @return resource */
    private function resource(mixed $resource, string $name)
    {
        if (!is_resource($resource) || get_resource_type($resource) !== 'stream') {
            throw new InvalidArgumentException("CLI {$name} must be a stream resource.");
        }

        return $resource;
    }
}
