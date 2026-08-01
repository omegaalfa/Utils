<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Profiler;

use InvalidArgumentException;
use LogicException;
use Throwable;

final class Profiler
{
    /** @var array<string, array{startedAt: int, memoryAtStart: int}> */
    private array $active = [];

    /**
     * @var array<string, array{
     *     calls: int,
     *     totalDuration: int,
     *     minimumDuration: int,
     *     maximumDuration: int,
     *     totalMemoryDelta: int
     * }>
     */
    private array $statistics = [];

    public function start(string $name): void
    {
        $this->assertName($name);

        if (isset($this->active[$name])) {
            throw new LogicException("Measurement '{$name}' is already active.");
        }

        $memoryAtStart = memory_get_usage();
        $this->active[$name] = [
            'startedAt' => (int) hrtime(true),
            'memoryAtStart' => $memoryAtStart,
        ];
    }

    public function stop(string $name): Measurement
    {
        $this->assertName($name);

        if (!isset($this->active[$name])) {
            throw new LogicException("Measurement '{$name}' is not active.");
        }

        $endedAt = (int) hrtime(true);
        $memoryAtEnd = memory_get_usage();
        $started = $this->active[$name];
        unset($this->active[$name]);

        $duration = $endedAt - $started['startedAt'];
        $memoryDelta = $memoryAtEnd - $started['memoryAtStart'];

        $this->record($name, $duration, $memoryDelta);

        return new Measurement($name, $duration, $memoryDelta);
    }

    /**
     * @template TResult
     * @param callable(): TResult $operation
     * @return TResult
     * @throws Throwable
     */
    public function measure(string $name, callable $operation): mixed
    {
        $this->start($name);

        try {
            return $operation();
        } finally {
            if (isset($this->active[$name])) {
                $this->stop($name);
            }
        }
    }

    public function isActive(string $name): bool
    {
        $this->assertName($name);

        return isset($this->active[$name]);
    }

    public function get(string $name): ?ProfileEntry
    {
        $this->assertName($name);
        $statistics = $this->statistics[$name] ?? null;

        return $statistics === null
            ? null
            : $this->entry($name, $statistics);
    }

    /** @return array<string, ProfileEntry> */
    public function all(): array
    {
        $entries = [];

        foreach ($this->statistics as $name => $statistics) {
            $entries[$name] = $this->entry($name, $statistics);
        }

        return $entries;
    }

    /**
     * Returns entries ordered by total measured duration, descending.
     *
     * @return list<ProfileEntry>
     */
    public function slowest(int $limit = 10): array
    {
        if ($limit < 1) {
            throw new InvalidArgumentException('Slowest operation limit must be greater than zero.');
        }

        $entries = array_values($this->all());
        usort(
            $entries,
            static fn (ProfileEntry $left, ProfileEntry $right): int =>
                $right->totalDurationNanoseconds <=> $left->totalDurationNanoseconds,
        );

        return array_slice($entries, 0, $limit);
    }

    public function reset(): void
    {
        $this->active = [];
        $this->statistics = [];
    }

    private function record(string $name, int $duration, int $memoryDelta): void
    {
        if (!isset($this->statistics[$name])) {
            $this->statistics[$name] = [
                'calls' => 1,
                'totalDuration' => $duration,
                'minimumDuration' => $duration,
                'maximumDuration' => $duration,
                'totalMemoryDelta' => $memoryDelta,
            ];
            return;
        }

        $statistics = $this->statistics[$name];
        $statistics['calls']++;
        $statistics['totalDuration'] += $duration;
        $statistics['minimumDuration'] = min($statistics['minimumDuration'], $duration);
        $statistics['maximumDuration'] = max($statistics['maximumDuration'], $duration);
        $statistics['totalMemoryDelta'] += $memoryDelta;
        $this->statistics[$name] = $statistics;
    }

    /**
     * @param array{
     *     calls: int,
     *     totalDuration: int,
     *     minimumDuration: int,
     *     maximumDuration: int,
     *     totalMemoryDelta: int
     * } $statistics
     */
    private function entry(string $name, array $statistics): ProfileEntry
    {
        return new ProfileEntry(
            $name,
            $statistics['calls'],
            $statistics['totalDuration'],
            $statistics['minimumDuration'],
            $statistics['maximumDuration'],
            $statistics['totalMemoryDelta'],
        );
    }

    private function assertName(string $name): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('Measurement name cannot be empty.');
        }
    }
}
