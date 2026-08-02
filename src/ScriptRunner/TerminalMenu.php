<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\ScriptRunner;

final readonly class TerminalMenu
{
    /**
     * @param Terminal $terminal
     */
    public function __construct(private Terminal $terminal)
    {
    }

    /** @param list<string> $items */
    public function choose(string $title, array $items, string $zeroLabel): int
    {
        while (true) {
            $this->terminal->writeln($title);
            $this->terminal->writeln();

            foreach ($items as $index => $item) {
                $this->terminal->writeln(sprintf('[%d] %s', $index + 1, $item));
            }

            $this->terminal->writeln();
            $this->terminal->writeln("[0] {$zeroLabel}");
            $this->terminal->writeln();
            $this->terminal->write('> ');

            $input = $this->terminal->read();
            if ($input === null) {
                return 0;
            }

            if (preg_match('/^(0|[1-9][0-9]*)$/D', $input) === 1) {
                $choice = (int)$input;
                if ($choice <= count($items)) {
                    return $choice;
                }
            }

            $this->terminal->writeln();
            $this->terminal->writeln('Opcao invalida. Tente novamente.');
            $this->terminal->writeln();
        }
    }
}
