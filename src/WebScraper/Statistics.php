<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\WebScraper;

/**
 * Statistics tracker for scraping performance and errors.
 */
class Statistics
{
    private int $totalRequests = 0;
    private int $successfulRequests = 0;
    private int $failedRequests = 0;
    private int $cachedRequests = 0;
    private int $retriedRequests = 0;

    /** @var array<int, int> Status code distribution */
    private array $statusCodes = [];

    /** @var array<string, float> Time tracking */
    private array $timings = [];

    private float $totalResponseTime = 0.0;
    private float $minResponseTime = PHP_FLOAT_MAX;
    private float $maxResponseTime = 0.0;

    /** @var array<string, int> Error types */
    private array $errors = [];

    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    /**
     * Record a successful request.
     *
     * @param int $statusCode
     * @param float $responseTime
     * @param bool $fromCache
     */
    public function recordSuccess(int $statusCode, float $responseTime, bool $fromCache = false): void
    {
        $this->totalRequests++;
        $this->successfulRequests++;

        if ($fromCache) {
            $this->cachedRequests++;
        }

        // Status code distribution
        if (!isset($this->statusCodes[$statusCode])) {
            $this->statusCodes[$statusCode] = 0;
        }
        $this->statusCodes[$statusCode]++;

        // Response time tracking
        $this->totalResponseTime += $responseTime;
        $this->minResponseTime = min($this->minResponseTime, $responseTime);
        $this->maxResponseTime = max($this->maxResponseTime, $responseTime);
    }

    /**
     * Record a failed request.
     *
     * @param string $errorType
     */
    public function recordFailure(string $errorType): void
    {
        $this->totalRequests++;
        $this->failedRequests++;

        if (!isset($this->errors[$errorType])) {
            $this->errors[$errorType] = 0;
        }
        $this->errors[$errorType]++;
    }

    /**
     * Record a retry attempt.
     */
    public function recordRetry(): void
    {
        $this->retriedRequests++;
    }

    /**
     * Record timing for a specific operation.
     *
     * @param string $operation
     * @param float $time
     */
    public function recordTiming(string $operation, float $time): void
    {
        if (!isset($this->timings[$operation])) {
            $this->timings[$operation] = 0.0;
        }
        $this->timings[$operation] += $time;
    }

    /**
     * Get comprehensive statistics report.
     *
     * @return array<string, mixed>
     */
    public function getReport(): array
    {
        $uptime = microtime(true) - $this->startTime;
        $successRate = $this->totalRequests > 0 ? ($this->successfulRequests / $this->totalRequests) * 100 : 0.0;
        $cacheHitRate = $this->successfulRequests > 0 ? ($this->cachedRequests / $this->successfulRequests) * 100 : 0.0;
        $avgResponseTime = $this->successfulRequests > 0 ? $this->totalResponseTime / $this->successfulRequests : 0.0;
        $requestsPerSecond = $uptime > 0 ? $this->totalRequests / $uptime : 0.0;

        return [
            'total_requests' => $this->totalRequests,
            'successful_requests' => $this->successfulRequests,
            'failed_requests' => $this->failedRequests,
            'cached_requests' => $this->cachedRequests,
            'retried_requests' => $this->retriedRequests,
            'success_rate_percent' => round($successRate, 2),
            'cache_hit_rate_percent' => round($cacheHitRate, 2),
            'response_time' => [
                'average_seconds' => round($avgResponseTime, 3),
                'min_seconds' => $this->minResponseTime === PHP_FLOAT_MAX ? 0.0 : round($this->minResponseTime, 3),
                'max_seconds' => round($this->maxResponseTime, 3),
                'total_seconds' => round($this->totalResponseTime, 3),
            ],
            'status_codes' => $this->statusCodes,
            'errors' => $this->errors,
            'timings' => array_map(fn($time) => round($time, 3), $this->timings),
            'uptime_seconds' => round($uptime, 2),
            'requests_per_second' => round($requestsPerSecond, 2),
        ];
    }

    /**
     * Get total requests count.
     *
     * @return int
     */
    public function getTotalRequests(): int
    {
        return $this->totalRequests;
    }

    /**
     * Get successful requests count.
     *
     * @return int
     */
    public function getSuccessfulRequests(): int
    {
        return $this->successfulRequests;
    }

    /**
     * Get failed requests count.
     *
     * @return int
     */
    public function getFailedRequests(): int
    {
        return $this->failedRequests;
    }

    /**
     * Get cached requests count.
     *
     * @return int
     */
    public function getCachedRequests(): int
    {
        return $this->cachedRequests;
    }

    /**
     * Get success rate percentage.
     *
     * @return float
     */
    public function getSuccessRate(): float
    {
        if ($this->totalRequests === 0) {
            return 0.0;
        }

        return ($this->successfulRequests / $this->totalRequests) * 100;
    }

    /**
     * Get average response time.
     *
     * @return float
     */
    public function getAverageResponseTime(): float
    {
        if ($this->successfulRequests === 0) {
            return 0.0;
        }

        return $this->totalResponseTime / $this->successfulRequests;
    }

    /**
     * Reset all statistics.
     */
    public function reset(): void
    {
        $this->totalRequests = 0;
        $this->successfulRequests = 0;
        $this->failedRequests = 0;
        $this->cachedRequests = 0;
        $this->retriedRequests = 0;
        $this->statusCodes = [];
        $this->timings = [];
        $this->totalResponseTime = 0.0;
        $this->minResponseTime = PHP_FLOAT_MAX;
        $this->maxResponseTime = 0.0;
        $this->errors = [];
        $this->startTime = microtime(true);
    }

    /**
     * Export statistics to JSON.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->getReport(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Save statistics to file.
     *
     * @param string $path
     */
    public function saveToFile(string $path): void
    {
        $directory = dirname($path);
        
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $this->toJson());
    }
}
