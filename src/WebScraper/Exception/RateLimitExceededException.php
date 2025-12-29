<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\WebScraper\Exception;

use RuntimeException;

/**
 * Exception thrown when rate limit is exceeded.
 */
class RateLimitExceededException extends RuntimeException
{
    /**
     * @param string $message
     * @param string $domain
     * @param float $retryAfter
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message,
        private readonly string $domain,
        private readonly float $retryAfter = 0.0,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the domain that caused the exception.
     *
     * @return string
     */
    public function getDomain(): string
    {
        return $this->domain;
    }

    /**
     * Get the time to wait before retrying.
     *
     * @return float
     */
    public function getRetryAfter(): float
    {
        return $this->retryAfter;
    }

    /**
     * Create exception for rate limit exceeded.
     *
     * @param string $domain
     * @param float $retryAfter
     * @return self
     */
    public static function create(string $domain, float $retryAfter): self
    {
        return new self(
            "Rate limit exceeded for domain '{$domain}'. Retry after {$retryAfter}s",
            $domain,
            $retryAfter
        );
    }
}
