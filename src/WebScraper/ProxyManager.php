<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\WebScraper;

/**
 * Proxy manager with rotation support.
 *
 * Supports HTTP/HTTPS proxies with automatic rotation per request or on failure.
 */
class ProxyManager
{
    /** @var list<string> */
    private array $proxies = [];

    private int $currentIndex = 0;
    private bool $rotateOnRequest = false;
    private bool $rotateOnFailure = true;

    /** @var array<string, int> Failure count per proxy */
    private array $failureCounts = [];

    private int $maxFailures = 3;

    /**
     * @param list<string> $proxies List of proxy URLs (e.g., "http://proxy.com:8080")
     */
    public function __construct(array $proxies = [])
    {
        $this->proxies = array_values($proxies);
    }

    /**
     * Add a proxy to the list.
     *
     * @param string $proxy
     */
    public function addProxy(string $proxy): void
    {
        $this->proxies[] = $proxy;
    }

    /**
     * Set proxy list.
     *
     * @param list<string> $proxies
     */
    public function setProxies(array $proxies): void
    {
        $this->proxies = array_values($proxies);
        $this->currentIndex = 0;
    }

    /**
     * Get current proxy.
     *
     * @return string|null
     */
    public function getCurrentProxy(): ?string
    {
        if (empty($this->proxies)) {
            return null;
        }

        return $this->proxies[$this->currentIndex];
    }

    /**
     * Get next proxy (rotate).
     *
     * @return string|null
     */
    public function getNextProxy(): ?string
    {
        if (empty($this->proxies)) {
            return null;
        }

        if ($this->rotateOnRequest) {
            $this->rotate();
        }

        return $this->getCurrentProxy();
    }

    /**
     * Rotate to next proxy.
     */
    public function rotate(): void
    {
        if (empty($this->proxies)) {
            return;
        }

        $this->currentIndex = ($this->currentIndex + 1) % count($this->proxies);
    }

    /**
     * Mark current proxy as failed.
     *
     * If failure count exceeds threshold, proxy is removed from rotation.
     */
    public function markFailure(): void
    {
        $proxy = $this->getCurrentProxy();
        
        if ($proxy === null) {
            return;
        }

        if (!isset($this->failureCounts[$proxy])) {
            $this->failureCounts[$proxy] = 0;
        }

        $this->failureCounts[$proxy]++;

        // Remove proxy if exceeded max failures
        if ($this->failureCounts[$proxy] >= $this->maxFailures) {
            $this->removeCurrentProxy();
        } elseif ($this->rotateOnFailure) {
            $this->rotate();
        }
    }

    /**
     * Mark current proxy as successful (reset failure count).
     */
    public function markSuccess(): void
    {
        $proxy = $this->getCurrentProxy();
        
        if ($proxy !== null) {
            $this->failureCounts[$proxy] = 0;
        }
    }

    /**
     * Remove current proxy from rotation.
     */
    private function removeCurrentProxy(): void
    {
        if (empty($this->proxies)) {
            return;
        }

        array_splice($this->proxies, $this->currentIndex, 1);

        // Adjust index
        if ($this->currentIndex >= count($this->proxies) && !empty($this->proxies)) {
            $this->currentIndex = 0;
        }
    }

    /**
     * Check if proxies are configured.
     *
     * @return bool
     */
    public function hasProxies(): bool
    {
        return !empty($this->proxies);
    }

    /**
     * Get total proxy count.
     *
     * @return int
     */
    public function getProxyCount(): int
    {
        return count($this->proxies);
    }

    /**
     * Enable rotation on each request.
     *
     * @param bool $enabled
     * @return self
     */
    public function withRotationOnRequest(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->rotateOnRequest = $enabled;
        return $clone;
    }

    /**
     * Enable rotation on failure.
     *
     * @param bool $enabled
     * @return self
     */
    public function withRotationOnFailure(bool $enabled = true): self
    {
        $clone = clone $this;
        $clone->rotateOnFailure = $enabled;
        return $clone;
    }

    /**
     * Set maximum failures before removing proxy.
     *
     * @param int $maxFailures
     * @return self
     */
    public function withMaxFailures(int $maxFailures): self
    {
        $clone = clone $this;
        $clone->maxFailures = max(1, $maxFailures);
        return $clone;
    }

    /**
     * Get failure statistics.
     *
     * @return array<string, int>
     */
    public function getFailureStats(): array
    {
        return $this->failureCounts;
    }

    /**
     * Reset failure counts.
     */
    public function resetFailures(): void
    {
        $this->failureCounts = [];
    }

    /**
     * Get all proxies.
     *
     * @return list<string>
     */
    public function getProxies(): array
    {
        return $this->proxies;
    }
}
