<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\Fibers;

use Fiber;
use InvalidArgumentException;
use LogicException;
use OverflowException;
use SplQueue;
use Throwable;

/**
 * Minimal cooperative round-robin scheduler for native PHP Fibers.
 *
 * Tasks must call Fiber::suspend() themselves to yield execution. This class
 * does not provide parallelism, timers, or asynchronous I/O.
 */
final class FiberScheduler
{
    /** @var SplQueue<array{id: int, fiber: Fiber<mixed, mixed, mixed, mixed>}> */
    private SplQueue $queue;

    /**
     * @var int
     */
    private int $nextId = 0;

    /**
     * @var int
     */
    private int $taskCount = 0;

    /**
     * @var bool
     */
    private bool $running = false;

    /**
     * @param int $maxTasks
     */
    public function __construct(private readonly int $maxTasks = 1_000)
    {
        if ($maxTasks < 1) {
            throw new InvalidArgumentException('Maximum task count must be greater than zero.');
        }

        $this->queue = new SplQueue();
    }

    /**
     * @param callable(): mixed $task
     * @return int Stable task identifier used as the result key.
     */
    public function schedule(callable $task): int
    {
        if ($this->taskCount >= $this->maxTasks) {
            throw new OverflowException("Fiber scheduler capacity of {$this->maxTasks} tasks was reached.");
        }

        $id = $this->nextId++;
        $this->queue->enqueue([
            'id' => $id,
            'fiber' => new Fiber($task),
        ]);
        $this->taskCount++;

        return $id;
    }

    /**
     * Runs scheduled tasks until all terminate.
     *
     * Results are keyed by the identifiers returned from schedule(). If a task
     * throws, all remaining scheduled tasks are discarded and the exception is
     * propagated unchanged.
     *
     * @return array<int, mixed>
     * @throws Throwable
     */
    public function run(): array
    {
        if ($this->running) {
            throw new LogicException('Fiber scheduler is already running.');
        }

        $this->running = true;
        $results = [];

        try {
            while (!$this->queue->isEmpty()) {
                $entry = $this->queue->dequeue();
                $fiber = $entry['fiber'];

                if (!$fiber->isStarted()) {
                    $fiber->start();
                } elseif ($fiber->isSuspended()) {
                    $fiber->resume();
                }

                if ($fiber->isTerminated()) {
                    $results[$entry['id']] = $fiber->getReturn();
                    $this->taskCount--;
                } else {
                    $this->queue->enqueue($entry);
                }
            }
        } catch (Throwable $exception) {
            $this->reset();
            throw $exception;
        } finally {
            $this->running = false;
        }

        return $results;
    }

    /**
     * @return int
     */
    public function pendingCount(): int
    {
        return $this->taskCount;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->taskCount === 0;
    }

    /**
     * @return void
     */
    private function reset(): void
    {
        $this->queue = new SplQueue();
        $this->taskCount = 0;
    }
}
