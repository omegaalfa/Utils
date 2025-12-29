<?php

declare(strict_types=1);

namespace Tests;

use Omegaalfa\Utils\WebScraper\Exception\NetworkException;
use Omegaalfa\Utils\WebScraper\Exception\ParsingException;
use Omegaalfa\Utils\WebScraper\Exception\RateLimitExceededException;

/**
 * Test suite for custom exceptions.
 */
class ExceptionTest extends WebScraperTestCase
{
    /**
     * Test NetworkException.
     */
    public function testNetworkException(): void
    {
        $exception = new NetworkException('Connection failed', 'https://example.com');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(NetworkException::class, $exception);
        $this->assertEquals('Connection failed', $exception->getMessage());
        $this->assertEquals('https://example.com', $exception->getUrl());
    }

    /**
     * Test NetworkException with previous exception.
     */
    public function testNetworkExceptionWithPrevious(): void
    {
        $previous = new \RuntimeException('Original error');
        $exception = new NetworkException('Network failed', 'https://example.com', 0, $previous);

        $this->assertEquals($previous, $exception->getPrevious());
        $this->assertEquals('Original error', $exception->getPrevious()->getMessage());
    }

    /**
     * Test ParsingException.
     */
    public function testParsingException(): void
    {
        $exception = new ParsingException('Invalid HTML', 'https://example.com');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(ParsingException::class, $exception);
        $this->assertEquals('Invalid HTML', $exception->getMessage());
    }

    /**
     * Test ParsingException with context.
     */
    public function testParsingExceptionWithContext(): void
    {
        $context = ['selector' => 'h1', 'html_length' => 1000];
        $exception = new ParsingException('Selector not found', 'https://example.com', 0, null, $context);

        $this->assertInstanceOf(ParsingException::class, $exception);
        $this->assertEquals($context, $exception->getContext());
    }

    /**
     * Test RateLimitExceededException.
     */
    public function testRateLimitExceededException(): void
    {
        $exception = new RateLimitExceededException('Rate limit exceeded', 'domain.com');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(RateLimitExceededException::class, $exception);
        $this->assertStringContainsString('Rate limit exceeded', $exception->getMessage());
    }

    /**
     * Test RateLimitExceededException with retry time.
     */
    public function testRateLimitExceededExceptionWithRetryTime(): void
    {
        $retryAfter = 5.0;
        $exception = new RateLimitExceededException('Rate limit exceeded', 'domain.com', $retryAfter);

        $this->assertInstanceOf(RateLimitExceededException::class, $exception);
        $this->assertEquals($retryAfter, $exception->getRetryAfter());
    }

    /**
     * Test exception hierarchy.
     */
    public function testExceptionHierarchy(): void
    {
        $networkException = new NetworkException('Network error', 'https://example.com');
        $parsingException = new ParsingException('Parse error', 'https://example.com');
        $rateLimitException = new RateLimitExceededException('Rate limit error', 'example.com');

        // All should be RuntimeExceptions
        $this->assertInstanceOf(\RuntimeException::class, $networkException);
        $this->assertInstanceOf(\RuntimeException::class, $parsingException);
        $this->assertInstanceOf(\RuntimeException::class, $rateLimitException);

        // But they should be different classes
        $this->assertNotSame(get_class($networkException), get_class($parsingException));
        $this->assertNotSame(get_class($parsingException), get_class($rateLimitException));
        $this->assertNotSame(get_class($networkException), get_class($rateLimitException));
    }

    /**
     * Test exception messages.
     */
    public function testExceptionMessages(): void
    {
        $messages = [
            'Connection timeout',
            'DNS resolution failed',
            'SSL certificate error',
            'Invalid HTML structure',
            'Selector not found: .nonexistent',
            'HTML content too large',
            'Rate limit exceeded for api.example.com',
            'Too many requests, retry after 60 seconds'
        ];

        foreach ($messages as $message) {
            $exception = new NetworkException($message, 'https://example.com');
            $this->assertEquals($message, $exception->getMessage());
        }
    }

    /**
     * Test exception codes.
     */
    public function testExceptionCodes(): void
    {
        $exception = new NetworkException('Error', 'https://example.com', 500);
        $this->assertEquals(500, $exception->getCode());
    }

    /**
     * Test exception serialization.
     */
    public function testExceptionSerialization(): void
    {
        $exception = new ParsingException('Parse error', 'https://example.com', 400, null, ['line' => 10, 'column' => 5]);

        $serialized = serialize($exception);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(ParsingException::class, $unserialized);
        $this->assertEquals($exception->getMessage(), $unserialized->getMessage());
        $this->assertEquals($exception->getCode(), $unserialized->getCode());
        $this->assertEquals($exception->getContext(), $unserialized->getContext());
    }

    /**
     * Test exception in try-catch blocks.
     */
    public function testExceptionHandling(): void
    {
        // Test that exceptions can be caught properly
        $caught = false;
        try {
            throw new NetworkException('Test error', 'https://example.com');
        } catch (NetworkException $e) {
            $caught = true;
            $this->assertEquals('Test error', $e->getMessage());
        } catch (\Exception $e) {
            $this->fail('Should have caught NetworkException specifically');
        }

        $this->assertTrue($caught);
    }

    /**
     * Test exception context in ParsingException.
     */
    public function testParsingExceptionContext(): void
    {
        $context = [
            'selector' => 'div.content',
            'html_length' => 15000,
            'parsing_time' => 2.5,
            'error_position' => 1234
        ];

        $exception = new ParsingException('Failed to parse selector', 'https://example.com', 0, null, $context);

        $this->assertEquals($context, $exception->getContext());
        $this->assertEquals('div.content', $exception->getContext()['selector']);
        $this->assertEquals(15000, $exception->getContext()['html_length']);
    }

    /**
     * Test RateLimitExceededException retry time.
     */
    public function testRateLimitRetryTime(): void
    {
        $retryTimes = [1.0, 5.0, 30.0, 60.0, 300.0];

        foreach ($retryTimes as $retryTime) {
            $exception = new RateLimitExceededException('Rate limited', 'example.com', $retryTime);
            $this->assertEquals($retryTime, $exception->getRetryAfter());
        }
    }

    /**
     * Test exception chaining.
     */
    public function testExceptionChaining(): void
    {
        $rootCause = new \InvalidArgumentException('Invalid argument');
        $networkException = new NetworkException('Network failed', 'https://example.com', 0, $rootCause);
        $parsingException = new ParsingException('Parse failed', 'https://example.com', 0, $networkException);

        // Test the chain
        $this->assertSame($rootCause, $networkException->getPrevious());
        $this->assertSame($networkException, $parsingException->getPrevious());
        $this->assertSame($rootCause, $parsingException->getPrevious()->getPrevious());
    }

    /**
     * Test exception string representation.
     */
    public function testExceptionStringRepresentation(): void
    {
        $exception = new NetworkException('Connection failed', 'https://example.com', 500);

        $string = (string) $exception;
        $this->assertStringContains('NetworkException', $string);
        $this->assertStringContains('Connection failed', $string);
    }

    /**
     * Test exception with empty context.
     */
    public function testExceptionWithEmptyContext(): void
    {
        $exception = new ParsingException('Error', 'https://example.com');

        $this->assertIsArray($exception->getContext());
        $this->assertEmpty($exception->getContext());
    }

    /**
     * Test exception with zero retry time.
     */
    public function testExceptionWithZeroRetryTime(): void
    {
        $exception = new RateLimitExceededException('Limited', 'example.com', 0.0);

        $this->assertEquals(0.0, $exception->getRetryAfter());
    }

    /**
     * Test exception immutability.
     */
    public function testExceptionImmutability(): void
    {
        $exception = new ParsingException('Error', 'https://example.com', 0, null, ['key' => 'value']);

        $context = $exception->getContext();
        $context['new_key'] = 'new_value';

        // Original context should not be modified
        $this->assertArrayNotHasKey('new_key', $exception->getContext());
        $this->assertEquals(['key' => 'value'], $exception->getContext());
    }
}