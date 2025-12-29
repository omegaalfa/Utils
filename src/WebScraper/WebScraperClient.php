<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\WebScraper;

use Dom\HTMLDocument;
use Omegaalfa\HttpPromise\HttpPromise;
use Omegaalfa\HttpPromise\Promise\PromiseInterface;
use Omegaalfa\HttpPromise\Utils\WebScraper\Exception\NetworkException;
use Omegaalfa\HttpPromise\Utils\WebScraper\Exception\ParsingException;
use Omegaalfa\HttpPromise\Utils\WebScraper\Exception\RateLimitExceededException;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Professional Web Scraper with WAF evasion, resilience, and performance optimizations.
 *
 * Features:
 * - Asynchronous HTTP requests via HttpPromise
 * - RFC 6265 compliant cookie management
 * - Realistic browser fingerprinting with rotation
 * - Intelligent caching with TTL
 * - Rate limiting per domain
 * - Proxy rotation
 * - Automatic retry with exponential backoff
 * - HTML parsing with CSS selector support
 * - UTF-8 normalization
 * - Comprehensive statistics
 *
 * Example:
 * ```php
 * $scraper = WebScraperClient::create()
 *     ->withCookiesFromFile('/path/cookies.json')
 *     ->withCache(3600)
 *     ->withRateLimit(10.0)
 *     ->withRetry(3, 1.0);
 *
 * $data = $scraper->scrape('https://example.com', [
 *     'title' => 'h1',
 *     'price' => '.product-price',
 *     'description' => 'meta[name="description"]@content',
 * ])->wait();
 * ```
 */
class WebScraperClient
{
    private HttpPromise $http;
    private CookieJar $cookieJar;
    private HeaderFingerprint $fingerprint;
    private ResponseCache $cache;
    private RateLimiter $rateLimiter;
    private ProxyManager $proxyManager;
    private Statistics $statistics;

    private bool $followRedirects = true;
    private int $maxRedirects = 5;
    private float $timeout = 30.0;
    private bool $autoRotateFingerprint = false;
    private bool $cacheEnabled = false;
    private bool $rateLimitEnabled = false;

    /** @var array<int> Retry on these status codes */
    private array $retryStatusCodes = [429, 502, 503, 504];
    private int $retryAttempts = 3;
    private float $retryDelay = 1.0;

    /** @var callable|null Progress callback */
    private $onProgress = null;

    private string $lastUrl = '';

    /**
     * @param HttpPromise $http
     */
    public function __construct(HttpPromise $http)
    {
        $this->http = $http;
        $this->cookieJar = new CookieJar();
        $this->fingerprint = new HeaderFingerprint();
        $this->cache = new ResponseCache();
        $this->rateLimiter = new RateLimiter();
        $this->proxyManager = new ProxyManager();
        $this->statistics = new Statistics();
    }

    /**
     * Factory method to create scraper with default HttpPromise.
     *
     * @return self
     */
    public static function create(): self
    {
        $http = HttpPromise::create()
            ->withTimeout(30.0)
            ->withMaxConcurrent(10);

        return new self($http);
    }

    // =========================================================================
    // Configuration Methods
    // =========================================================================

    /**
     * Load cookies from file.
     *
     * @param string $path
     * @return self
     */
    public function withCookiesFromFile(string $path): self
    {
        $this->cookieJar->loadCookiesFromFile($path);
        return $this;
    }

    /**
     * Save cookies to file.
     *
     * @param string $path
     * @return self
     * @throws \JsonException
     */
    public function saveCookies(string $path): self
    {
        $this->cookieJar->saveCookiesToFile($path);
        return $this;
    }

    /**
     * Enable response caching with TTL.
     *
     * @param float $ttl TTL in seconds
     * @param int $maxSize Maximum cache entries
     * @return self
     */
    public function withCache(float $ttl = 3600.0, int $maxSize = 1000): self
    {
        $this->cache = new ResponseCache($maxSize, $ttl);
        $this->cacheEnabled = true;
        return $this;
    }

    /**
     * Disable caching.
     *
     * @return self
     */
    public function withoutCache(): self
    {
        $this->cacheEnabled = false;
        return $this;
    }

    /**
     * Enable rate limiting per domain.
     *
     * @param float $rps Requests per second
     * @param float $burstSize Burst size
     * @return self
     */
    public function withRateLimit(float $rps = 10.0, float $burstSize = 0.0): self
    {
        $this->rateLimiter = new RateLimiter($rps, $burstSize);
        $this->rateLimitEnabled = true;
        return $this;
    }

    /**
     * Disable rate limiting.
     *
     * @return self
     */
    public function withoutRateLimit(): self
    {
        $this->rateLimitEnabled = false;
        return $this;
    }

    /**
     * Configure proxies.
     *
     * @param list<string> $proxies
     * @param bool $rotateOnRequest
     * @return self
     */
    public function withProxies(array $proxies, bool $rotateOnRequest = true): self
    {
        $this->proxyManager->setProxies($proxies);
        $this->proxyManager = $this->proxyManager->withRotationOnRequest($rotateOnRequest);
        return $this;
    }

    /**
     * Configure retry behavior.
     *
     * @param int $attempts
     * @param float $delay Initial delay in seconds
     * @param array<int> $statusCodes Status codes to retry
     * @return self
     */
    public function withRetry(int $attempts = 3, float $delay = 1.0, array $statusCodes = [429, 502, 503, 504]): self
    {
        $this->retryAttempts = max(1, $attempts);
        $this->retryDelay = max(0.1, $delay);
        $this->retryStatusCodes = $statusCodes;
        return $this;
    }

    /**
     * Enable automatic fingerprint rotation on each request.
     *
     * @param bool $enabled
     * @return self
     */
    public function withFingerprintRotation(bool $enabled = true): self
    {
        $this->autoRotateFingerprint = $enabled;
        $this->fingerprint = $this->fingerprint->withRotationOnRequest($enabled);
        return $this;
    }

    /**
     * Configure redirect behavior.
     *
     * @param bool $follow
     * @param int $maxRedirects
     * @return self
     */
    public function withRedirects(bool $follow = true, int $maxRedirects = 5): self
    {
        $this->followRedirects = $follow;
        $this->maxRedirects = max(0, $maxRedirects);
        return $this;
    }

    /**
     * Set request timeout.
     *
     * @param float $timeout
     * @return self
     */
    public function withTimeout(float $timeout): self
    {
        $this->timeout = max(1.0, $timeout);
        $this->http = $this->http->withTimeout($this->timeout);
        return $this;
    }

    /**
     * Set progress callback for scrapeMultiple().
     *
     * @param callable $callback function(string $url, int $current, int $total): void
     * @return self
     */
    public function onProgress(callable $callback): self
    {
        $this->onProgress = $callback;
        return $this;
    }

    // =========================================================================
    // HTTP Request Methods
    // =========================================================================

    /**
     * Perform GET request.
     *
     * @param string $url
     * @param array<string, string> $customHeaders
     * @return PromiseInterface<ResponseInterface>
     */
    public function get(string $url, array $customHeaders = []): PromiseInterface
    {
        return $this->request('GET', $url, $customHeaders);
    }

    /**
     * Perform POST request.
     *
     * @param string $url
     * @param mixed $body
     * @param array<string, string> $customHeaders
     * @return PromiseInterface<ResponseInterface>
     */
    public function post(string $url, mixed $body = null, array $customHeaders = []): PromiseInterface
    {
        return $this->request('POST', $url, $customHeaders, $body);
    }

    /**
     * Generic HTTP request with all features enabled.
     *
     * @param string $method
     * @param string $url
     * @param array<string, string> $customHeaders
     * @param mixed $body
     * @return PromiseInterface<ResponseInterface>
     */
    private function request(string $method, string $url, array $customHeaders = [], mixed $body = null): PromiseInterface
    {
        // Security: Validate URL to prevent SSRF
        $this->validateUrlSafety($url);
        
        $startTime = microtime(true);

        // Check cache first (only for GET)
        if ($this->cacheEnabled && $method === 'GET') {
            $cached = $this->cache->get($url);
            if ($cached !== null) {
                $responseTime = microtime(true) - $startTime;
                $this->statistics->recordSuccess($cached['statusCode'], $responseTime, true);
                
                // Return cached response wrapped in resolved promise
                return $this->http->get('/')->then(fn() => $this->createResponseFromCache($cached));
            }
        }

        // Rate limiting
        if ($this->rateLimitEnabled) {
            try {
                $domain = parse_url($url, PHP_URL_HOST) ?? '';
                $this->rateLimiter->checkLimit($domain);
            } catch (RateLimitExceededException $e) {
                $this->statistics->recordFailure('rate_limit');
                throw $e;
            }
        }

        // Build headers
        $headers = $this->buildHeaders($url, $customHeaders);

        // Add cookies
        $cookieHeader = $this->cookieJar->getCookieHeader($url, str_starts_with($url, 'https://'));
        if ($cookieHeader !== '') {
            $headers['Cookie'] = $cookieHeader;
        }

        // Configure HTTP client
        $http = $this->http->withTimeout($this->timeout);

        // Set proxy if available
        if ($this->proxyManager->hasProxies()) {
            $proxy = $this->proxyManager->getNextProxy();
            if ($proxy !== null) {
                $http = $http->withProxy($proxy);
            }
        }

        // Make request with retry logic
        return $this->executeWithRetry($http, $method, $url, $headers, $body, $startTime, 1);
    }

    /**
     * Execute request with retry logic.
     *
     * @param HttpPromise $http
     * @param string $method
     * @param string $url
     * @param array<string, string> $headers
     * @param mixed $body
     * @param float $startTime
     * @param int $attempt
     * @return PromiseInterface<ResponseInterface>
     */
    private function executeWithRetry(
        HttpPromise $http,
        string $method,
        string $url,
        array $headers,
        mixed $body,
        float $startTime,
        int $attempt
    ): PromiseInterface {
        return $http->request($method, $url, $headers, $body)->then(
            function (ResponseInterface $response) use ($url, $startTime, $attempt, $http, $method, $headers, $body) {
                $responseTime = microtime(true) - $startTime;
                $statusCode = $response->getStatusCode();

                // Parse Set-Cookie headers
                if ($response->hasHeader('Set-Cookie')) {
                    foreach ($response->getHeader('Set-Cookie') as $setCookie) {
                        $this->cookieJar->parseCookie($setCookie, $url);
                    }
                }

                // Handle Retry-After
                if ($response->hasHeader('Retry-After') && $this->rateLimitEnabled) {
                    $domain = parse_url($url, PHP_URL_HOST) ?? '';
                    $retryAfter = $response->getHeaderLine('Retry-After');
                    $this->rateLimiter->setRetryAfter($domain, $retryAfter);
                }

                // Check if should retry based on status code
                if (in_array($statusCode, $this->retryStatusCodes, true) && $attempt < $this->retryAttempts) {
                    $this->statistics->recordRetry();
                    $delay = $this->calculateBackoff($attempt);
                    usleep((int)($delay * 1000000));
                    return $this->executeWithRetry($http, $method, $url, $headers, $body, microtime(true), $attempt + 1);
                }

                // Cache successful responses (200-299)
                if ($this->cacheEnabled && $method === 'GET' && $statusCode >= 200 && $statusCode < 300) {
                    $content = (string)$response->getBody();
                    $this->cache->set($url, $content, $this->extractHeaders($response), $statusCode);
                }

                // Record success
                $this->statistics->recordSuccess($statusCode, $responseTime, false);
                $this->proxyManager->markSuccess();
                $this->lastUrl = $url;

                return $response;
            },
            function (Throwable $error) use ($url, $attempt, $http, $method, $headers, $body, $startTime) {
                // Handle network errors with retry
                if ($attempt < $this->retryAttempts) {
                    $this->statistics->recordRetry();
                    $this->proxyManager->markFailure();
                    
                    $delay = $this->calculateBackoff($attempt);
                    usleep((int)($delay * 1000000));
                    
                    return $this->executeWithRetry($http, $method, $url, $headers, $body, microtime(true), $attempt + 1);
                }

                // Record failure
                $errorType = $this->classifyError($error);
                $this->statistics->recordFailure($errorType);
                $this->proxyManager->markFailure();

                throw NetworkException::fromCurlError($error->getMessage(), $url);
            }
        );
    }

    /**
     * Calculate exponential backoff delay.
     *
     * @param int $attempt
     * @return float
     */
    private function calculateBackoff(int $attempt): float
    {
        return $this->retryDelay * (2 ** ($attempt - 1));
    }

    /**
     * Build headers with fingerprint.
     *
     * @param string $url
     * @param array<string, string> $customHeaders
     * @return array<string, string>
     */
    private function buildHeaders(string $url, array $customHeaders): array
    {
        // Security: Sanitize custom headers to prevent CRLF injection
        $customHeaders = $this->sanitizeHeaders($customHeaders);
        
        $referer = $this->lastUrl;
        return $this->fingerprint->mergeHeaders($customHeaders, $url, $referer);
    }

    /**
     * Extract headers from response as array.
     *
     * @param ResponseInterface $response
     * @return array<string, string>
     */
    private function extractHeaders(ResponseInterface $response): array
    {
        return array_map(static function ($values) {
            return implode(', ', $values);
        }, $response->getHeaders());
    }

    /**
     * Create mock response from cache data.
     *
     * @param array{content: string, headers: array<string, string>, statusCode: int} $cached
     * @return ResponseInterface
     * @throws Throwable
     */
    private function createResponseFromCache(array $cached): ResponseInterface
    {
        // This is a simplified mock - in production, build proper PSR-7 response
        return $this->http->get('/')->wait(); // Placeholder
    }

    /**
     * Classify error type.
     *
     * @param Throwable $error
     * @return string
     */
    private function classifyError(Throwable $error): string
    {
        $message = strtolower($error->getMessage());
        
        if (str_contains($message, 'timeout')) {
            return 'timeout';
        }
        if (str_contains($message, 'connection')) {
            return 'connection_failed';
        }
        if (str_contains($message, 'ssl') || str_contains($message, 'certificate')) {
            return 'ssl_error';
        }
        
        return 'unknown';
    }

    // =========================================================================
    // HTML Parsing Methods
    // =========================================================================

    /**
     * Scrape data using CSS selectors.
     *
     * @param string $url
     * @param array<string, string> $selectors Map of key => CSS selector
     * @param array<string, string> $customHeaders
     * @return PromiseInterface<array<string, string|list<string>>>
     */
    public function scrape(string $url, array $selectors, array $customHeaders = []): PromiseInterface
    {
        return $this->get($url, $customHeaders)->then(function (ResponseInterface $response) use ($url, $selectors) {
            $html = (string)$response->getBody();
            $html = $this->normalizeEncoding($html, $response);

            $results = [];
            foreach ($selectors as $key => $selector) {
                try {
                    $results[$key] = $this->extractBySelector($html, $selector);
                } catch (ParsingException $e) {
                    $results[$key] = null;
                }
            }

            return $results;
        });
    }

    /**
     * Scrape multiple URLs concurrently.
     *
     * @param array<string, array{url: string, selectors: array<string, string>}> $targets
     * @return PromiseInterface<array<string, array<string, string|list<string>>>>
     */
    public function scrapeMultiple(array $targets): PromiseInterface
    {
        $promises = [];
        $total = count($targets);
        $current = 0;

        foreach ($targets as $key => $target) {
            $promise = $this->scrape($target['url'], $target['selectors']);
            
            if ($this->onProgress !== null) {
                $promise = $promise->then(function ($result) use ($key, &$current, $total, $target) {
                    $current++;
                    ($this->onProgress)($target['url'], $current, $total);
                    return $result;
                });
            }

            $promises[$key] = $promise;
        }

        return \Omegaalfa\HttpPromise\Promise\Promise::all($promises);
    }

    /**
     * Extract data by CSS selector.
     *
     * @param string $html
     * @param string $selector
     * @return string|list<string>|null
     */
    private function extractBySelector(string $html, string $selector): string|array|null
    {
        // Security: Limit HTML size to prevent ReDoS and memory exhaustion (10MB)
        if (strlen($html) > 10 * 1024 * 1024) {
            throw ParsingException::invalidHtml('', 'HTML too large (>10MB)');
        }

        $results = [];

        $selector = trim($selector);
        if ($selector === '') {
            return $results;
        }

        // Parse attribute extraction (e.g., "a@href")
        $attribute = null;
        if (str_contains($selector, '@')) {
            [$selector, $attribute] = explode('@', $selector, 2);
            $selector = trim($selector);
            $attribute = trim($attribute);
        }

        // PHP 8.4 (ext-dom): HTML5 parser + seletores CSS nativos (querySelectorAll)
        if (class_exists(HTMLDocument::class)) {
            try {
                /** @var object $doc */
                $doc = HTMLDocument::createFromString($html, LIBXML_NOERROR | LIBXML_COMPACT);

                // querySelectorAll faz o parsing de CSS selectors de verdade (>, +, ~, attrs, pseudo-classes, ...)
                $nodeList = $doc->querySelectorAll($selector);

                foreach ($nodeList as $node) {
                    $value = '';
                    try {
                        // Extract attribute if specified
                        if ($attribute !== null && method_exists($node, 'getAttribute')) {
                            $value = trim((string)$node->getAttribute($attribute));
                        } elseif (isset($node->innerHTML)) {
                            $value = trim((string)$node->innerHTML);
                        } else {
                            $value = trim((string)($node->textContent ?? ''));
                        }
                    } catch (Throwable) {
                        $value = '';
                    }

                    if ($value !== '') {
                        $results[] = $value;
                    }
                }

                return array_values($results);
            } catch (Throwable) {
                // Fallback para implementação legada abaixo
            }
        }

        // Fallback (legado): seletor simples via regex
        // Security: Configure PCRE limits to prevent ReDoS
        ini_set('pcre.backtrack_limit', '1000000');
        ini_set('pcre.recursion_limit', '100000');
        
        // Remove scripts e styles (evita ruído)
        $html = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html) ?? '';

        // Suporte: #id, .class, tag, tag.class, tag#id, tag#id.class
        if (preg_match('/^(?:(?<tag>[a-z][a-z0-9-]*)|)(?<id>#[a-z0-9_-]+|)(?<classes>(?:\.[a-z0-9_-]+)*)$/i', $selector, $m) !== 1) {
            return $results;
        }

        $tag = $m['tag'] !== '' ? $m['tag'] : null;
        $id = $m['id'] !== '' ? substr($m['id'], 1) : null;

        $classes = [];
        $classesRaw = $m['classes'] ?? '';
        if ($classesRaw !== '') {
            foreach (explode('.', ltrim($classesRaw, '.')) as $cls) {
                if ($cls !== '') {
                    $classes[] = $cls;
                }
            }
        }

        $tagPattern = $tag ? preg_quote($tag, '/') : '[a-z0-9-]+';

        $attrLookaheads = '';
        if ($id !== null) {
            $attrLookaheads .= '(?=[^>]*\bid\s*=\s*["\']' . preg_quote($id, '/') . '["\'])';
        }
        foreach ($classes as $cls) {
            $attrLookaheads .= '(?=[^>]*\bclass\s*=\s*["\'][^"\']*\b' . preg_quote($cls, '/') . '\b[^"\']*["\'])';
        }

        // If extracting attribute, match opening tag only
        if ($attribute !== null) {
            $regex = '/<(' . $tagPattern . ')\b' . $attrLookaheads . '[^>]*>/is';
            
            if (preg_match_all($regex, $html, $matches)) {
                foreach ($matches[0] as $tag) {
                    // Extract attribute value from tag
                    if (preg_match('/' . preg_quote($attribute, '/') . '\s*=\s*["\']([^"\']*)["\']/', $tag, $attrMatch)) {
                        $value = trim($attrMatch[1]);
                        if ($value !== '') {
                            $results[] = $value;
                        }
                    }
                }
            }
        } else {
            // Extract inner content
            $regex = '/<(' . $tagPattern . ')\b' . $attrLookaheads . '[^>]*>(.*?)<\/\1>/is';
            
            if (preg_match_all($regex, $html, $matches)) {
                foreach ($matches[2] as $content) {
                    $v = trim($content);
                    if ($v !== '') {
                        $results[] = $v;
                    }
                }
            }
        }

        return array_values($results);
    }


    /**
     * Normalize HTML encoding to UTF-8.
     *
     * @param string $html
     * @param ResponseInterface $response
     * @return string
     */
    private function normalizeEncoding(string $html, ResponseInterface $response): string
    {
        // 1. Check Content-Type header
        $contentType = $response->getHeaderLine('Content-Type');
        if (preg_match('/charset=([a-z0-9_-]+)/i', $contentType, $matches)) {
            $charset = strtoupper($matches[1]);
            if ($charset !== 'UTF-8') {
                return mb_convert_encoding($html, 'UTF-8', $charset);
            }
            return $html;
        }

        // 2. Check <meta charset>
        if (preg_match('/<meta[^>]+charset=["\']?([a-z0-9_-]+)/i', $html, $matches)) {
            $charset = strtoupper($matches[1]);
            if ($charset !== 'UTF-8') {
                return mb_convert_encoding($html, 'UTF-8', $charset);
            }
            return $html;
        }

        // 3. Auto-detect
        $detected = mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);
        if ($detected !== false && $detected !== 'UTF-8') {
            return mb_convert_encoding($html, 'UTF-8', $detected);
        }

        return $html;
    }

    // =========================================================================
    // Utility Methods
    // =========================================================================

    /**
     * Get statistics report.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $report = $this->statistics->getReport();
        
        // Add cache stats
        if ($this->cacheEnabled) {
            $report['cache'] = $this->cache->getStats();
        }

        return $report;
    }

    /**
     * Wait for all pending requests.
     */
    public function wait(): void
    {
        $this->http->wait();
    }

    /**
     * Get cookie jar.
     *
     * @return CookieJar
     */
    public function getCookieJar(): CookieJar
    {
        return $this->cookieJar;
    }

    /**
     * Get cache instance.
     *
     * @return ResponseCache
     */
    public function getCache(): ResponseCache
    {
        return $this->cache;
    }

    /**
     * Get statistics instance.
     *
     * @return Statistics
     */
    public function getStats(): Statistics
    {
        return $this->statistics;
    }

    /**
     * Clear all cache.
     *
     * @return self
     */
    public function clearCache(): self
    {
        $this->cache->clear();
        return $this;
    }

    /**
     * Reset statistics.
     *
     * @return self
     */
    public function resetStatistics(): self
    {
        $this->statistics->reset();
        return $this;
    }

    // =========================================================================
    // Security Methods
    // =========================================================================

    /**
     * Validate URL safety to prevent SSRF attacks.
     *
     * @param string $url
     * @throws \RuntimeException
     */
    private function validateUrlSafety(string $url): void
    {
        // Parse URL
        $parsed = parse_url($url);
        if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
            throw new \RuntimeException('Invalid URL format');
        }

        $scheme = strtolower($parsed['scheme']);
        $host = strtolower($parsed['host']);

        // Only allow HTTP and HTTPS
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \RuntimeException('Invalid URL scheme. Only http and https are allowed');
        }

        // Block localhost and local IPs
        $blockedHosts = [
            'localhost',
            '127.0.0.1',
            '0.0.0.0',
            '::1',
            'localhost.localdomain',
        ];

        if (in_array($host, $blockedHosts, true)) {
            throw new \RuntimeException('Access to localhost is blocked for security reasons');
        }

        // Check if it's an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // Block private and reserved IP ranges
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \RuntimeException('Access to private IP ranges is blocked for security reasons');
            }
        } else {
            // For domain names, resolve and check the IP
            $ip = @gethostbyname($host);
            if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) {
                if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    throw new \RuntimeException('Domain resolves to private IP range');
                }
            }
        }

        // Block cloud metadata endpoints
        $blockedPatterns = [
            '169.254.169.254', // AWS, Azure, GCP metadata
            'metadata.google.internal',
            'instance-data',
        ];

        foreach ($blockedPatterns as $pattern) {
            if (str_contains($host, $pattern)) {
                throw new \RuntimeException('Access to metadata endpoints is blocked');
            }
        }
    }

    /**
     * Sanitize HTTP headers to prevent CRLF injection.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function sanitizeHeaders(array $headers): array
    {
        $sanitized = [];

        foreach ($headers as $name => $value) {
            // Remove CRLF characters
            $name = preg_replace('/[\r\n]/', '', (string)$name);
            $value = preg_replace('/[\r\n]/', '', (string)$value);

            // Validate header name (alphanumeric and hyphens only)
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $name)) {
                continue; // Skip invalid header names
            }

            // Limit header value size (8KB max)
            if (strlen($value) > 8192) {
                $value = substr($value, 0, 8192);
            }

            $sanitized[$name] = $value;
        }

        return $sanitized;
    }
}