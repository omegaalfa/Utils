<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\WebScraper;

use JsonException;

/**
 * RFC 6265 compliant Cookie Jar for managing HTTP cookies.
 *
 * Supports Domain, Path, Expires, Secure, HttpOnly, SameSite attributes.
 */
class CookieJar
{
    /** @var array<string, array{value: string, domain: string, path: string, expires: int, secure: bool, httpOnly: bool, sameSite: string|null}> */
    private array $cookies = [];

    /** @var array<string, array<string, int>> URL cache para parse_url */
    private array $urlCache = [];

    /**
     * Set a cookie.
     *
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param string $domain Cookie domain
     * @param string $path Cookie path
     * @param int $expires Expiration timestamp (0 = session cookie)
     * @param bool $secure Secure flag
     * @param bool $httpOnly HttpOnly flag
     * @param string|null $sameSite SameSite attribute (Strict, Lax, None, or null)
     */
    public function setCookie(
        string $name,
        string $value,
        string $domain = '',
        string $path = '/',
        int $expires = 0,
        bool $secure = false,
        bool $httpOnly = false,
        ?string $sameSite = null
    ): void {
        $domain = $this->normalizeDomain($domain);

        // Security: Reject TLD-only domains (e.g., ".com", "com")
        if ($domain !== '' && substr_count($domain, '.') < 1) {
            return; // Silently reject invalid domain
        }

        // Security: Remove control characters from cookie value to prevent injection
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        // Validate SameSite
        $validSameSite = ['Strict', 'Lax', 'None'];
        if ($sameSite !== null && !in_array($sameSite, $validSameSite, true)) {
            throw new \InvalidArgumentException("Invalid SameSite value: {$sameSite}. Must be one of: Strict, Lax, None");
        }

        $this->cookies[$name] = [
            'value' => $value,
            'domain' => $domain,
            'path' => $path,
            'expires' => $expires,
            'secure' => $secure,
            'httpOnly' => $httpOnly,
            'sameSite' => $sameSite,
        ];
    }

    /**
     * Parse Set-Cookie header and store cookie.
     *
     * @param string $setCookieHeader
     * @param string $requestUrl URL that returned the Set-Cookie header
     */
    public function parseCookie(string $setCookieHeader, string $requestUrl): void
    {
        $parts = array_map('trim', explode(';', $setCookieHeader));
        
        if (empty($parts)) {
            return;
        }

        // Parse name=value
        $nameValue = explode('=', $parts[0], 2);
        if (count($nameValue) !== 2) {
            return;
        }

        [$name, $value] = $nameValue;
        $name = trim($name);
        $value = trim($value);

        if ($name === '') {
            return;
        }

        // Parse URL for default domain/path
        $parsed = $this->parseUrlCached($requestUrl);
        $defaultDomain = $parsed['host'] ?? '';
        $defaultPath = $parsed['path'] ?? '/';
        $defaultPath = dirname($defaultPath);
        if ($defaultPath === '.') {
            $defaultPath = '/';
        }

        // Parse attributes
        $domain = $defaultDomain;
        $path = $defaultPath;
        $expires = 0;
        $secure = false;
        $httpOnly = false;
        $sameSite = null;

        for ($i = 1, $iMax = count($parts); $i < $iMax; $i++) {
            $attribute = $parts[$i];
            $attrParts = explode('=', $attribute, 2);
            $attrName = strtolower(trim($attrParts[0]));
            $attrValue = isset($attrParts[1]) ? trim($attrParts[1]) : '';

            match ($attrName) {
                'domain' => $domain = $attrValue,
                'path' => $path = $attrValue,
                'expires' => $expires = strtotime($attrValue) ?: 0,
                'max-age' => $expires = time() + (int)$attrValue,
                'secure' => $secure = true,
                'httponly' => $httpOnly = true,
                'samesite' => $sameSite = ucfirst(strtolower($attrValue)),
                default => null,
            };
        }

        $this->setCookie($name, $value, $domain, $path, $expires, $secure, $httpOnly, $sameSite);
    }

    /**
     * Get cookies matching the given URL.
     *
     * @param string $url Request URL
     * @param bool $secureRequest Whether the request is HTTPS
     * @return array<string, string> Cookie name => value pairs
     */
    public function getCookiesForUrl(string $url, bool $secureRequest = false): array
    {
        $parsed = $this->parseUrlCached($url);
        $domain = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '/';
        $now = time();

        $matching = [];

        foreach ($this->cookies as $name => $cookie) {
            // Check expiration
            if ($cookie['expires'] > 0 && $cookie['expires'] < $now) {
                continue;
            }

            // Check secure flag
            if ($cookie['secure'] && !$secureRequest) {
                continue;
            }

            // Check domain match
            if (!$this->domainMatches($domain, $cookie['domain'])) {
                continue;
            }

            // Check path match
            if (!$this->pathMatches($path, $cookie['path'])) {
                continue;
            }

            $matching[$name] = $cookie['value'];
        }

        return $matching;
    }

    /**
     * Get Cookie header string for URL.
     *
     * @param string $url
     * @param bool $secureRequest
     * @return string
     */
    public function getCookieHeader(string $url, bool $secureRequest = false): string
    {
        $cookies = $this->getCookiesForUrl($url, $secureRequest);
        
        if (empty($cookies)) {
            return '';
        }

        $pairs = [];
        foreach ($cookies as $name => $value) {
            $pairs[] = "{$name}={$value}";
        }

        return implode('; ', $pairs);
    }

    /**
     * Load cookies from array.
     *
     * @param array<string, array{value: string, domain: string, path: string, expires: int, secure: bool, httpOnly: bool, sameSite: string|null}> $cookies
     */
    public function loadCookiesFromArray(array $cookies): void
    {
        foreach ($cookies as $name => $cookie) {
            $this->setCookie(
                $name,
                $cookie['value'] ?? '',
                $cookie['domain'] ?? '',
                $cookie['path'] ?? '/',
                $cookie['expires'] ?? 0,
                $cookie['secure'] ?? false,
                $cookie['httpOnly'] ?? false,
                $cookie['sameSite'] ?? null
            );
        }
    }

    /**
     * Save cookies to JSON file.
     *
     * @param string $path File path
     * @throws JsonException
     */
    public function saveCookiesToFile(string $path): void
    {
        // Security: Validate and sanitize path to prevent path traversal
        $path = $this->sanitizePath($path);
        $directory = dirname($path);
        
        // Security: Use more restrictive permissions (0700 instead of 0755)
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $directory));
        }

        $json = json_encode($this->cookies, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        // Security: Atomic write with temporary file to prevent race conditions
        $tempFile = $path . '.tmp.' . uniqid('', true);
        
        try {
            if (file_put_contents($tempFile, $json, LOCK_EX) === false) {
                throw new \RuntimeException('Failed to write cookie file');
            }
            
            // Set secure permissions before rename
            chmod($tempFile, 0600);
            
            if (!rename($tempFile, $path)) {
                @unlink($tempFile);
                throw new \RuntimeException('Failed to save cookie file');
            }
            
            // Ensure final file has correct permissions
            chmod($path, 0600);
        } catch (\Throwable $e) {
            @unlink($tempFile);
            throw $e;
        }
    }

    /**
     * Load cookies from JSON file (silent fail if not exists).
     *
     * @param string $path File path
     */
    public function loadCookiesFromFile(string $path): void
    {
        // Security: Validate and sanitize path
        $path = $this->sanitizePath($path);
        
        if (!file_exists($path)) {
            return; // Silent fail
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return;
        }

        try {
            $cookies = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            error_log('Invalid cookie JSON: ' . $e->getMessage());
            return;
        }

        if (!is_array($cookies)) {
            return;
        }

        // Security: Validate each cookie before loading
        $validatedCookies = [];
        foreach ($cookies as $name => $cookie) {
            if (!is_string($name) || strlen($name) > 256 || $name === '') {
                continue;
            }

            if (!is_array($cookie) || !isset($cookie['value'], $cookie['domain'], $cookie['path'])) {
                continue;
            }

            // Validate and sanitize cookie data
            $validatedCookies[$name] = [
                'value' => is_string($cookie['value']) ? $cookie['value'] : '',
                'domain' => is_string($cookie['domain']) ? $this->normalizeDomain($cookie['domain']) : '',
                'path' => is_string($cookie['path']) ? $cookie['path'] : '/',
                'expires' => isset($cookie['expires']) ? (int)$cookie['expires'] : 0,
                'secure' => isset($cookie['secure']) ? (bool)$cookie['secure'] : false,
                'httpOnly' => isset($cookie['httpOnly']) ? (bool)$cookie['httpOnly'] : false,
                'sameSite' => isset($cookie['sameSite']) && in_array($cookie['sameSite'], ['Strict', 'Lax', 'None'], true) 
                    ? $cookie['sameSite'] 
                    : null,
            ];
        }

        $this->loadCookiesFromArray($validatedCookies);
    }

    /**
     * Get all cookies.
     *
     * @return array<string, array{value: string, domain: string, path: string, expires: int, secure: bool, httpOnly: bool, sameSite: string|null}>
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Get all cookies (alias for getCookies).
     *
     * @return array
     */
    public function getAllCookies(): array
    {
        return $this->getCookies();
    }

    /**
     * Clear all cookies.
     */
    public function clearCookies(): void
    {
        $this->cookies = [];
    }

    /**
     * Remove a specific cookie.
     *
     * @param string $name Cookie name
     * @param string $domain Cookie domain
     * @param string $path Cookie path
     */
    public function removeCookie(string $name, string $domain = '', string $path = '/'): void
    {
        if (isset($this->cookies[$name])) {
            $cookie = $this->cookies[$name];
            if ($domain === '' || $cookie['domain'] === $domain) {
                if ($path === '/' || $cookie['path'] === $path) {
                    unset($this->cookies[$name]);
                }
            }
        }
    }

    /**
     * Count total cookies.
     *
     * @return int
     */
    public function countCookies(): int
    {
        return count($this->cookies);
    }

    /**
     * Remove expired cookies.
     */
    public function removeExpired(): void
    {
        $now = time();
        
        foreach ($this->cookies as $name => $cookie) {
            if ($cookie['expires'] > 0 && $cookie['expires'] < $now) {
                unset($this->cookies[$name]);
            }
        }
    }

    /**
     * Normalize domain (remove leading dot, lowercase).
     *
     * @param string $domain
     * @return string
     */
    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        return ltrim($domain, '.');
    }

    /**
     * Check if request domain matches cookie domain.
     *
     * @param string $requestDomain
     * @param string $cookieDomain
     * @return bool
     */
    private function domainMatches(string $requestDomain, string $cookieDomain): bool
    {
        $requestDomain = $this->normalizeDomain($requestDomain);
        $cookieDomain = $this->normalizeDomain($cookieDomain);

        // Security: Reject TLD-only domains (e.g., ".com", "com")
        if (substr_count($cookieDomain, '.') < 1) {
            return false;
        }

        if ($requestDomain === $cookieDomain) {
            return true;
        }

        // Check subdomain match (cookie.com matches www.cookie.com)
        // Cookie domain should be a suffix of request domain
        return str_ends_with($requestDomain, ".{$cookieDomain}");
    }

    /**
     * Check if request path matches cookie path.
     *
     * @param string $requestPath
     * @param string $cookiePath
     * @return bool
     */
    private function pathMatches(string $requestPath, string $cookiePath): bool
    {
        if ($requestPath === $cookiePath) {
            return true;
        }

        // Cookie path must be prefix of request path
        return str_starts_with($requestPath, rtrim($cookiePath, '/') . '/');
    }

    /**
     * Parse URL with caching.
     *
     * @param string $url
     * @return array<string, mixed>
     */
    private function parseUrlCached(string $url): array
    {
        if (isset($this->urlCache[$url])) {
            return $this->urlCache[$url];
        }

        $parsed = parse_url($url);
        if ($parsed === false) {
            $parsed = [];
        }

        // Limit cache size to prevent memory bloat
        if (count($this->urlCache) > 1000) {
            $this->urlCache = array_slice($this->urlCache, -500, null, true);
        }

        $this->urlCache[$url] = $parsed;
        return $parsed;
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
}
