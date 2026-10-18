<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Cli;

use InvalidArgumentException;

/**
 * Dependency-free ASCII progress bar rendered through {@see Output}.
 *
 * Intermediate redraws use a carriage return so the same terminal line is
 * refreshed; {@see self::finish()} prints the completed bar and a newline.
 */
final class ProgressBar
{
    private int $current = 0;

    /**
     * @param Output $output Destination of every redraw (stdout, stderr or a memory stream).
     * @param int $max Total number of steps; must be at least one.
     * @param int $width Bar width in characters; must be at least one.
     *
     * @throws InvalidArgumentException When the maximum or width is not positive.
     */
    public function __construct(
        private readonly Output $output,
        private readonly int $max,
        private readonly int $width = 30,
    ) {
        if ($max < 1) {
            throw new InvalidArgumentException('Progress bar maximum must be at least one.');
        }

        if ($width < 1) {
            throw new InvalidArgumentException('Progress bar width must be at least one.');
        }
    }

    /**
     * Resets the bar to zero and draws the first frame.
     */
    public function start(): void
    {
        $this->current = 0;
        $this->render();
    }

    /**
     * Advances the bar by the given number of steps and redraws it.
     *
     * @throws InvalidArgumentException When the step is negative.
     */
    public function advance(int $step = 1): void
    {
        if ($step < 0) {
            throw new InvalidArgumentException('Progress bar step cannot be negative.');
        }

        $this->current = min($this->current + $step, $this->max);
        $this->render();
    }

    /**
     * Completes the bar and terminates the progress line.
     */
    public function finish(): void
    {
        $this->current = $this->max;
        $this->output->writeln($this->bar());
    }

    /**
     * Returns the number of completed steps.
     */
    public function current(): int
    {
        return $this->current;
    }

    private function render(): void
    {
        $this->output->write("\r" . $this->bar());
    }

    private function bar(): string
    {
        $filled = (int) floor($this->width * $this->current / $this->max);
        $bar = str_repeat('=', $filled) . str_repeat(' ', $this->width - $filled);
        $percent = (int) floor($this->current * 100 / $this->max);

        return sprintf('[%s] %3d%% (%d/%d)', $bar, $percent, $this->current, $this->max);
    }
}
