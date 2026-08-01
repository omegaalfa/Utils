<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Cli;

final class Input
{
    /** @var list<string> */
    private array $arguments = [];

    /** @var array<string, string|bool> */
    private array $options = [];

    /** @param list<string> $tokens */
    public function __construct(array $tokens)
    {
        $parseOptions = true;

        foreach ($tokens as $token) {
            if ($parseOptions && $token === '--') {
                $parseOptions = false;
                continue;
            }
            if ($parseOptions && str_starts_with($token, '--')) {
                $this->parseLongOption(substr($token, 2));
                continue;
            }
            if ($parseOptions && str_starts_with($token, '-') && $token !== '-') {
                $this->parseShortOption(substr($token, 1));
                continue;
            }

            $this->arguments[] = $token;
        }
    }

    public function argument(int $index, ?string $default = null): ?string
    {
        return $this->arguments[$index] ?? $default;
    }

    /** @return list<string> */
    public function arguments(): array
    {
        return $this->arguments;
    }

    public function option(string $name, string|bool|null $default = null): string|bool|null
    {
        return $this->options[$name] ?? $default;
    }

    public function hasOption(string $name): bool
    {
        return array_key_exists($name, $this->options);
    }

    /** @return array<string, string|bool> */
    public function options(): array
    {
        return $this->options;
    }

    private function parseLongOption(string $option): void
    {
        if ($option === '') {
            return;
        }

        $parts = explode('=', $option, 2);
        $name = $parts[0];
        $this->options[$name] = $parts[1] ?? true;
    }

    private function parseShortOption(string $option): void
    {
        if ($option === '') {
            return;
        }
        if (str_contains($option, '=')) {
            [$name, $value] = explode('=', $option, 2);
            $this->options[$name] = $value;
            return;
        }

        $length = strlen($option);
        for ($index = 0; $index < $length; $index++) {
            $this->options[$option[$index]] = true;
        }
    }
}
