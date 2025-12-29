<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\WebScraper\Exception;

use RuntimeException;

/**
 * Exception thrown when HTML parsing fails.
 */
class ParsingException extends RuntimeException
{
    /**
     * @param string $message
     * @param string $url
     * @param int $code
     * @param \Throwable|null $previous
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message,
        private readonly string $url,
        int $code = 0,
        ?\Throwable $previous = null,
        private readonly array $context = []
    ) {
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
     * Get the context information.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Create exception for invalid HTML.
     *
     * @param string $url
     * @param string $reason
     * @return self
     */
    public static function invalidHtml(string $url, string $reason = ''): self
    {
        $message = 'Failed to parse HTML';
        if ($reason !== '') {
            $message .= ": {$reason}";
        }
        return new self($message, $url);
    }

    /**
     * Create exception for selector not found.
     *
     * @param string $url
     * @param string $selector
     * @return self
     */
    public static function selectorNotFound(string $url, string $selector): self
    {
        return new self("Selector '{$selector}' not found in HTML", $url);
    }
}
