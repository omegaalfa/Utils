<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * Base test case with common utilities for WebScraper tests.
 */
abstract class WebScraperTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean up any test files
        $this->cleanupTestFiles();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up after each test
        $this->cleanupTestFiles();
    }

    /**
     * Clean up test files and directories.
     */
    private function cleanupTestFiles(): void
    {
        $testFiles = [
            '/tmp/test_cookies.json',
            '/tmp/test_cache.json',
            '/tmp/test_stats.json',
            './test_cookies.json',
            './test_cache.json',
            './test_stats.json',
        ];

        foreach ($testFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        $testDirs = [
            '/tmp/test_cache_dir',
            './test_cache_dir',
        ];

        foreach ($testDirs as $dir) {
            if (is_dir($dir)) {
                $this->removeDirectory($dir);
            }
        }
    }

    /**
     * Recursively remove a directory.
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * Create a mock HTTP response.
     */
    protected function createMockResponse(
        int $statusCode = 200,
        string $body = '',
        array $headers = []
    ): \Psr\Http\Message\ResponseInterface {
        $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);

        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getBody')->willReturn($this->createMockStream($body));
        $response->method('getHeaders')->willReturn($headers);
        $response->method('getHeader')->willReturnCallback(function ($name) use ($headers) {
            return $headers[$name] ?? [];
        });

        return $response;
    }

    /**
     * Create a mock stream.
     */
    protected function createMockStream(string $content): \Psr\Http\Message\StreamInterface
    {
        $stream = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $stream->method('getContents')->willReturn($content);
        $stream->method('__toString')->willReturn($content);
        return $stream;
    }

    /**
     * Create a mock promise that resolves immediately.
     */
    protected function createResolvedPromise($value): \Omegaalfa\HttpPromise\Promise\PromiseInterface
    {
        $promise = $this->createMock(\Omegaalfa\HttpPromise\Promise\PromiseInterface::class);
        $promise->method('wait')->willReturn($value);
        $promise->method('then')->willReturnCallback(function ($callback) use ($value) {
            $callback($value);
            return $this->createResolvedPromise($value);
        });
        return $promise;
    }

    /**
     * Create a mock promise that rejects.
     */
    protected function createRejectedPromise(\Throwable $exception): \Omegaalfa\HttpPromise\Promise\PromiseInterface
    {
        $promise = $this->createMock(\Omegaalfa\HttpPromise\Promise\PromiseInterface::class);
        $promise->method('wait')->willThrowException($exception);
        $promise->method('then')->willReturnCallback(function ($onFulfilled, $onRejected) use ($exception) {
            if ($onRejected) {
                $onRejected($exception);
            }
            return $this->createRejectedPromise($exception);
        });
        return $promise;
    }

    /**
     * Assert that an array has specific keys.
     */
    protected function assertArrayHasKeys(array $keys, array $array, string $message = ''): void
    {
        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $array, $message ?: "Array should have key '{$key}'");
        }
    }

    /**
     * Assert that a file contains valid JSON.
     */
    protected function assertFileIsValidJson(string $filename, string $message = ''): void
    {
        $this->assertFileExists($filename, $message ?: "File '{$filename}' should exist");

        $content = file_get_contents($filename);
        $this->assertIsString($content, "File '{$filename}' should contain string content");

        $data = json_decode($content, true);
        $this->assertIsArray($data, $message ?: "File '{$filename}' should contain valid JSON");
    }

    /**
     * Assert that a file has specific permissions.
     */
    protected function assertFilePermissions(string $filename, int $expectedPermissions): void
    {
        $this->assertFileExists($filename);
        $actualPermissions = fileperms($filename) & 0777;
        $this->assertEquals($expectedPermissions, $actualPermissions,
            sprintf("File '%s' should have permissions %o, got %o",
                $filename, $expectedPermissions, $actualPermissions));
    }

    /**
     * Assert that a string contains a substring.
     */
    protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertStringContainsString($needle, $haystack, $message);
    }
}