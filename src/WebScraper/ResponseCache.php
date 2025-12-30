<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\WebScraper;

/**
 * Intelligent response cache with TTL and hit rate tracking.
 */
class ResponseCache
{
    /** @var array<string, array{content: string, headers: array<string, string>, statusCode: int, expires: float}> */
    private array $cache = [];

    private int $hits = 0;
    private int $misses = 0;
    private int $maxSize;
    private float $defaultTtl;

    /**
     * @param int $maxSize Maximum cache entries
     * @param float $defaultTtl Default TTL in seconds
     */
    public function __construct(int $maxSize = 1000, float $defaultTtl = 3600.0)
    {
        $this->maxSize = max(1, $maxSize);
        $this->defaultTtl = max(0.0, $defaultTtl);
    }

    /**
     * Get cached response.
     *
     * @param string $url
     * @return array{content: string, headers: array<string, string>, statusCode: int}|null
     */
    public function get(string $url): ?array
    {
        $key = $this->generateKey($url);
        
        if (!isset($this->cache[$key])) {
            $this->misses++;
            return null;
        }

        $entry = $this->cache[$key];
        
        // Check expiration
        if ($entry['expires'] < microtime(true)) {
            unset($this->cache[$key]);
            $this->misses++;
            return null;
        }

        // Security: Validate integrity if hash exists
        if (isset($entry['integrity'], $entry['content'])) {
            $currentHash = hash('sha256', $entry['content']);
            if (!hash_equals($entry['integrity'], $currentHash)) {
                // Cache poisoning detected - remove entry
                unset($this->cache[$key]);
                error_log('Cache integrity check failed for: ' . $url);
                $this->misses++;
                return null;
            }
        }

        $this->hits++;
        
        return [
            'content' => $entry['content'],
            'headers' => $entry['headers'],
            'statusCode' => $entry['statusCode'],
        ];
    }

    /**
     * Store response in cache.
     *
     * @param string $url
     * @param string $content
     * @param array<string, string> $headers
     * @param int $statusCode
     * @param float|null $ttl TTL in seconds (null = use default)
     */
    public function set(string $url, string $content, array $headers, int $statusCode, ?float $ttl = null): void
    {
        // Security: Don't cache errors or large responses
        if ($statusCode < 200 || $statusCode >= 400) {
            return;
        }
        
        // Security: Limit cached content size to 10MB
        if (strlen($content) > 10 * 1024 * 1024) {
            return;
        }
        
        $key = $this->generateKey($url);
        $ttl = $ttl ?? $this->defaultTtl;

        // Evict oldest entries if cache is full (LRU-like)
        if (count($this->cache) >= $this->maxSize && !isset($this->cache[$key])) {
            $this->evictOldest();
        }

        // Security: Add integrity hash
        $integrity = hash('sha256', $content);

        $this->cache[$key] = [
            'content' => $content,
            'headers' => $headers,
            'statusCode' => $statusCode,
            'expires' => microtime(true) + $ttl,
            'integrity' => $integrity,
            'cachedAt' => time(),
        ];
    }

    /**
     * Check if URL is cached and valid.
     *
     * @param string $url
     * @return bool
     */
    public function has(string $url): bool
    {
        return $this->get($url) !== null;
    }

    /**
     * Remove cached entry.
     *
     * @param string $url
     */
    public function delete(string $url): void
    {
        $key = $this->generateKey($url);
        unset($this->cache[$key]);
    }

    /**
     * Clear all cache.
     */
    public function clear(): void
    {
        $this->cache = [];
        $this->hits = 0;
        $this->misses = 0;
    }

    /**
     * Remove expired entries.
     */
    public function prune(): void
    {
        $now = microtime(true);
        
        foreach ($this->cache as $key => $entry) {
            if ($entry['expires'] < $now) {
                unset($this->cache[$key]);
            }
        }
    }

    /**
     * Get cache statistics.
     *
     * @return array{hits: int, misses: int, size: int, hitRate: float}
     */
    public function getStats(): array
    {
        $total = $this->hits + $this->misses;
        $hitRate = $total > 0 ? ($this->hits / $total) * 100 : 0.0;

        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'size' => count($this->cache),
            'hitRate' => $hitRate,
        ];
    }

    /**
     * Reset statistics.
     */
    public function resetStats(): void
    {
        $this->hits = 0;
        $this->misses = 0;
    }

    /**
     * Save cache to file.
     *
     * @param string $path
     */
    public function saveToFile(string $path): void
    {
        // Security: Validate and sanitize path
        $path = $this->sanitizePath($path);
        $directory = dirname($path);
        
        // Security: Use restrictive permissions
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }

        $data = [
            'cache' => $this->cache,
            'hits' => $this->hits,
            'misses' => $this->misses,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        // Security: Atomic write with temporary file
        $tempFile = $path . '.tmp.' . uniqid('', true);
        
        try {
            if (file_put_contents($tempFile, $json, LOCK_EX) === false) {
                throw new \RuntimeException('Failed to write cache file');
            }
            
            chmod($tempFile, 0600);
            
            if (!rename($tempFile, $path)) {
                @unlink($tempFile);
                throw new \RuntimeException('Failed to save cache file');
            }
            
            chmod($path, 0600);
        } catch (\Throwable $e) {
            @unlink($tempFile);
            throw $e;
        }
    }

    /**
     * Load cache from file (silent fail if not exists).
     *
     * @param string $path
     */
    public function loadFromFile(string $path): void
    {
        // Security: Validate path
        try {
            $path = $this->sanitizePath($path);
        } catch (\Throwable $e) {
            error_log('Invalid cache path: ' . $e->getMessage());
            return;
        }
        
        if (!file_exists($path)) {
            return; // Silent fail
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return;
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            error_log('Invalid cache JSON: ' . $e->getMessage());
            return;
        }
        
        if (!is_array($data)) {
            return;
        }

        // Security: Validate cache structure
        if (isset($data['cache']) && is_array($data['cache'])) {
            // Validate each cache entry
            foreach ($data['cache'] as $key => $entry) {
                if (!is_array($entry) || !isset($entry['content'], $entry['expires'])) {
                    continue;
                }
                $this->cache[$key] = $entry;
            }
        }
        
        $this->hits = isset($data['hits']) ? (int)$data['hits'] : 0;
        $this->misses = isset($data['misses']) ? (int)$data['misses'] : 0;

        // Remove expired entries on load
        $this->prune();
    }

    /**
     * Sanitize file path to prevent path traversal attacks.
     *
     * @param string $path
     * @return string
     * @throws \RuntimeException
     */
    private function sanitizePath(string $path): string
    {
        // Reject paths with null bytes
        if (str_contains($path, "\0")) {
            throw new \RuntimeException('Invalid path: null byte detected');
        }

        // Security: Reject paths with backslashes (Windows path separators)
        // Backslashes are valid filename characters on Linux but indicate
        // a path traversal attempt from Windows paths like "..\..\windows\system32\config\sam"
        if (str_contains($path, '\\')) {
            throw new \RuntimeException('Invalid path: backslashes not allowed');
        }

        // Reject absolute paths pointing to system directories
        $dangerousPaths = ['/etc/', '/var/', '/usr/', '/bin/', '/sbin/', '/root/', '/boot/', '/sys/', '/proc/'];
        foreach ($dangerousPaths as $dangerous) {
            if (str_starts_with($path, $dangerous)) {
                throw new \RuntimeException('Access to system directories is not allowed');
            }
        }

        // Resolve path to detect directory traversal
        $directory = dirname($path);
        $filename = basename($path);

        // Check if directory exists and resolve it
        if (file_exists($directory)) {
            $realDir = realpath($directory);
            if ($realDir === false) {
                throw new \RuntimeException('Invalid directory path');
            }
            
            // Security: Check if realpath resolved to a different location (traversal detected)
            $expectedDir = realpath(dirname($directory) ?: '.') . DIRECTORY_SEPARATOR . basename($directory);
            $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $directory);
            
            // If path contains .., the realpath will be different from expected
            if (str_contains($normalized, '..')) {
                throw new \RuntimeException('Path traversal detected');
            }
            
            // Validate filename
            if ($filename === '' || $filename === '.' || $filename === '..') {
                throw new \RuntimeException('Invalid filename');
            }
            
            return $realDir . DIRECTORY_SEPARATOR . $filename;
        }

        // Directory doesn't exist yet - validate it doesn't contain traversal
        $normalized = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path);
        $parts = explode(DIRECTORY_SEPARATOR, $normalized);
        
        // Check for .. in any part
        foreach ($parts as $part) {
            if ($part === '..') {
                throw new \RuntimeException('Path traversal detected');
            }
        }

        return $path;
    }

    /**
     * Generate cache key from URL.
     *
     * @param string $url
     * @return string
     */
    private function generateKey(string $url): string
    {
        // Use sha256 for collision resistance
        return hash('sha256', $url);
    }

    /**
     * Evict oldest entry (by expiration time).
     */
    private function evictOldest(): void
    {
        if (empty($this->cache)) {
            return;
        }

        $oldest = null;
        $oldestKey = null;
        
        foreach ($this->cache as $key => $entry) {
            if ($oldest === null || $entry['expires'] < $oldest) {
                $oldest = $entry['expires'];
                $oldestKey = $key;
            }
        }

        if ($oldestKey !== null) {
            unset($this->cache[$oldestKey]);
        }
    }

    /**
     * Get cache size in bytes (approximate).
     *
     * @return int
     */
    public function getSizeInBytes(): int
    {
        $size = 0;
        
        foreach ($this->cache as $entry) {
            $size += strlen($entry['content']);
        }

        return $size;
    }
}
