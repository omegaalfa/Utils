<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\WebScraper;

use Omegaalfa\Utils\WebScraper\Exception\RateLimitExceededException;

/**
 * Rate limiter with per-domain RPS control and Retry-After support.
 */
class RateLimiter
{
    /** @var array<string, array{lastRequest: float, requestCount: int, windowStart: float}> */
    private array $domains = [];

    private float $requestsPerSecond;
    private float $burstSize;

    /**
     * @param float $requestsPerSecond Maximum requests per second per domain
     * @param float $burstSize Burst size (default = RPS, allows short bursts)
     */
    public function __construct(float $requestsPerSecond = 10.0, float $burstSize = 0.0)
    {
        $this->requestsPerSecond = max(0.1, $requestsPerSecond);
        $this->burstSize = $burstSize > 0 ? $burstSize : $this->requestsPerSecond;
    }

    /**
     * Check if request is allowed for domain.
     *
     * @param string $domain
     * @throws RateLimitExceededException
     */
    public function checkLimit(string $domain): void
    {
        $domain = $this->normalizeDomain($domain);
        $now = microtime(true);

        if (!isset($this->domains[$domain])) {
            $this->domains[$domain] = [
                'lastRequest' => $now,
                'requestCount' => 1,
                'windowStart' => $now,
            ];
            return;
        }

        $data = &$this->domains[$domain];
        
        // Reset window if 1 second has passed
        if ($now - $data['windowStart'] >= 1.0) {
            $data['requestCount'] = 1;
            $data['windowStart'] = $now;
            $data['lastRequest'] = $now;
            return;
        }

        // Check if within burst limit
        if ($data['requestCount'] >= $this->burstSize) {
            $retryAfter = 1.0 - ($now - $data['windowStart']);
            throw RateLimitExceededException::create($domain, $retryAfter);
        }

        // Check minimum delay between requests
        $minDelay = 1.0 / $this->requestsPerSecond;
        $timeSinceLastRequest = $now - $data['lastRequest'];
        
        if ($timeSinceLastRequest < $minDelay) {
            $retryAfter = $minDelay - $timeSinceLastRequest;
            throw RateLimitExceededException::create($domain, $retryAfter);
        }

        $data['requestCount']++;
        $data['lastRequest'] = $now;
    }

    /**
     * Wait (non-blocking check) until request is allowed.
     *
     * Returns delay needed before making request.
     *
     * @param string $domain
     * @return float Seconds to wait (0 = can proceed immediately)
     */
    public function getDelay(string $domain): float
    {
        $domain = $this->normalizeDomain($domain);
        $now = microtime(true);

        if (!isset($this->domains[$domain])) {
            return 0.0;
        }

        $data = $this->domains[$domain];
        
        // Reset window if 1 second has passed
        if ($now - $data['windowStart'] >= 1.0) {
            return 0.0;
        }

        // Check if within burst limit
        if ($data['requestCount'] >= $this->burstSize) {
            return 1.0 - ($now - $data['windowStart']);
        }

        // Check minimum delay between requests
        $minDelay = 1.0 / $this->requestsPerSecond;
        $timeSinceLastRequest = $now - $data['lastRequest'];
        
        if ($timeSinceLastRequest < $minDelay) {
            return $minDelay - $timeSinceLastRequest;
        }

        return 0.0;
    }

    /**
     * Respect Retry-After header from response.
     *
     * @param string $domain
     * @param string|int $retryAfter Retry-After header value (seconds or HTTP date)
     */
    public function setRetryAfter(string $domain, string|int $retryAfter): void
    {
        $domain = $this->normalizeDomain($domain);
        
        if (is_numeric($retryAfter)) {
            $delay = (float)$retryAfter;
        } else {
            $timestamp = strtotime($retryAfter);
            if ($timestamp === false) {
                return;
            }
            $delay = max(0.0, $timestamp - time());
        }

        $now = microtime(true);

        $this->domains[$domain] = [
            'lastRequest' => $now,
            'requestCount' => (int)$this->burstSize, // Block further requests
            'windowStart' => $now + $delay - 1.0, // Will reset after delay
        ];
    }

    /**
     * Reset rate limit for domain.
     *
     * @param string $domain
     */
    public function reset(string $domain): void
    {
        $domain = $this->normalizeDomain($domain);
        unset($this->domains[$domain]);
    }

    /**
     * Reset all rate limits.
     */
    public function resetAll(): void
    {
        $this->domains = [];
    }

    /**
     * Get statistics for domain.
     *
     * @param string $domain
     * @return array{requestCount: int, windowStart: float, lastRequest: float}|null
     */
    public function getStats(string $domain): ?array
    {
        $domain = $this->normalizeDomain($domain);
        
        return $this->domains[$domain] ?? null;
    }

    /**
     * Normalize domain (lowercase, remove scheme and path).
     *
     * @param string $domain
     * @return string
     */
    private function normalizeDomain(string $domain): string
    {
        // Extract host from URL if full URL provided
        if (str_contains($domain, '://')) {
            $parsed = parse_url($domain);
            $domain = $parsed['host'] ?? $domain;
        }

        return strtolower(trim($domain));
    }

    /**
     * Get current RPS limit.
     *
     * @return float
     */
    public function getRps(): float
    {
        return $this->requestsPerSecond;
    }

    /**
     * Set new RPS limit.
     *
     * @param float $rps
     * @return self
     */
    public function withRps(float $rps): self
    {
        $clone = clone $this;
        $clone->requestsPerSecond = max(0.1, $rps);
        return $clone;
    }

    /**
     * Set burst size.
     *
     * @param float $burstSize
     * @return self
     */
    public function withBurstSize(float $burstSize): self
    {
        $clone = clone $this;
        $clone->burstSize = max(1.0, $burstSize);
        return $clone;
    }
}
