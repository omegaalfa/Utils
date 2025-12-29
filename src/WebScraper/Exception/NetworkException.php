<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\WebScraper\Exception;

use RuntimeException;

/**
 * Exception thrown when network errors occur during scraping.
 */
class NetworkException extends RuntimeException
{
    /**
     * @param string $message
     * @param string $url
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, private readonly string $url, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the URL that caused the exception.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Create exception from cURL error.
     *
     * @param string $curlError
     * @param string $url
     * @return self
     */
    public static function fromCurlError(string $curlError, string $url): self
    {
        return new self("Network error: {$curlError}", $url);
    }

    /**
     * Create exception for timeout.
     *
     * @param string $url
     * @param float $timeout
     * @return self
     */
    public static function timeout(string $url, float $timeout): self
    {
        return new self("Request timeout after {$timeout}s", $url);
    }

    /**
     * Create exception for connection failure.
     *
     * @param string $url
     * @return self
     */
    public static function connectionFailed(string $url): self
    {
        return new self('Connection failed', $url);
    }
}
