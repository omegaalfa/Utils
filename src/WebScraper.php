<?php

namespace OmegaAlfa\Utils;

use Exception;

/**
 * WebScraper - A simple and efficient web scraping utility
 * 
 * This class provides methods to fetch and parse web content with various options
 * including custom headers, user agents, and timeout settings.
 */
class WebScraper
{
    /**
     * @var array Default options for HTTP requests
     */
    private $options = [
        'timeout' => 30,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
        'follow_redirects' => true,
        'max_redirects' => 5,
        'verify_ssl' => true,
    ];

    /**
     * @var array Custom headers to include in requests
     */
    private $headers = [];

    /**
     * WebScraper constructor
     * 
     * @param array $options Optional configuration options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * Set a custom user agent
     * 
     * @param string $userAgent The user agent string
     * @return self
     */
    public function setUserAgent(string $userAgent): self
    {
        $this->options['user_agent'] = $userAgent;
        return $this;
    }

    /**
     * Set request timeout in seconds
     * 
     * @param int $timeout Timeout in seconds
     * @return self
     */
    public function setTimeout(int $timeout): self
    {
        $this->options['timeout'] = $timeout;
        return $this;
    }

    /**
     * Set custom headers for the request
     * 
     * @param array $headers Associative array of headers
     * @return self
     */
    public function setHeaders(array $headers): self
    {
        $this->headers = $headers;
        return $this;
    }

    /**
     * Add a single header to the request
     * 
     * @param string $name Header name
     * @param string $value Header value
     * @return self
     */
    public function addHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Fetch content from a URL
     * 
     * @param string $url The URL to fetch
     * @return string The response body
     * @throws Exception If the request fails
     */
    public function fetch(string $url): string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Exception("Invalid URL provided: {$url}");
        }

        // Use cURL for better control and features
        if (function_exists('curl_init')) {
            return $this->fetchWithCurl($url);
        }

        // Fallback to file_get_contents
        return $this->fetchWithFileGetContents($url);
    }

    /**
     * Fetch content using cURL
     * 
     * @param string $url The URL to fetch
     * @return string The response body
     * @throws Exception If the request fails
     */
    private function fetchWithCurl(string $url): string
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->options['timeout']);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->options['user_agent']);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->options['follow_redirects']);
        curl_setopt($ch, CURLOPT_MAXREDIRS, $this->options['max_redirects']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->options['verify_ssl']);

        // Set custom headers
        if (!empty($this->headers)) {
            $headerArray = [];
            foreach ($this->headers as $name => $value) {
                $headerArray[] = "{$name}: {$value}";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new Exception("cURL error: {$error}");
        }

        if ($httpCode >= 400) {
            throw new Exception("HTTP error {$httpCode} while fetching {$url}");
        }

        return $response;
    }

    /**
     * Fetch content using file_get_contents
     * 
     * @param string $url The URL to fetch
     * @return string The response body
     * @throws Exception If the request fails
     */
    private function fetchWithFileGetContents(string $url): string
    {
        $contextOptions = [
            'http' => [
                'method' => 'GET',
                'timeout' => $this->options['timeout'],
                'user_agent' => $this->options['user_agent'],
                'follow_location' => $this->options['follow_redirects'] ? 1 : 0,
                'max_redirects' => $this->options['max_redirects'],
            ],
        ];

        // Add custom headers
        if (!empty($this->headers)) {
            $headerString = '';
            foreach ($this->headers as $name => $value) {
                $headerString .= "{$name}: {$value}\r\n";
            }
            $contextOptions['http']['header'] = $headerString;
        }

        $context = stream_context_create($contextOptions);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new Exception("Failed to fetch URL: " . ($error['message'] ?? 'Unknown error'));
        }

        return $response;
    }

    /**
     * Parse HTML and extract elements by CSS selector (basic implementation)
     * 
     * @param string $html The HTML content to parse
     * @param string $selector CSS selector (basic support for tags and classes)
     * @return array Array of matched elements
     */
    public function parseHtml(string $html, string $selector): array
    {
        if (!class_exists('DOMDocument')) {
            throw new Exception("DOMDocument class is not available");
        }

        $dom = new \DOMDocument();
        // Suppress warnings for malformed HTML
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $xpath = new \DOMXPath($dom);
        $elements = [];

        // Convert simple CSS selectors to XPath
        $xpathQuery = $this->cssToXPath($selector);
        $nodes = $xpath->query($xpathQuery);

        foreach ($nodes as $node) {
            $elements[] = $dom->saveHTML($node);
        }

        return $elements;
    }

    /**
     * Convert basic CSS selector to XPath
     * 
     * @param string $selector CSS selector
     * @return string XPath query
     */
    private function cssToXPath(string $selector): string
    {
        // Handle basic cases
        $selector = trim($selector);

        // Tag selector (e.g., "div")
        if (preg_match('/^[a-z][a-z0-9]*$/i', $selector)) {
            return "//{$selector}";
        }

        // Class selector (e.g., ".classname")
        if (preg_match('/^\.([a-z][a-z0-9_-]*)$/i', $selector, $matches)) {
            return "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$matches[1]} ')]";
        }

        // ID selector (e.g., "#id")
        if (preg_match('/^#([a-z][a-z0-9_-]*)$/i', $selector, $matches)) {
            return "//*[@id='{$matches[1]}']";
        }

        // Tag with class (e.g., "div.classname")
        if (preg_match('/^([a-z][a-z0-9]*)\.([a-z][a-z0-9_-]*)$/i', $selector, $matches)) {
            return "//{$matches[1]}[contains(concat(' ', normalize-space(@class), ' '), ' {$matches[2]} ')]";
        }

        // Default: treat as tag
        return "//{$selector}";
    }

    /**
     * Extract text content from HTML
     * 
     * @param string $html HTML content
     * @return string Plain text content
     */
    public function extractText(string $html): string
    {
        // Remove script and style tags
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);
        $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);

        // Convert HTML to plain text
        $text = strip_tags($html);

        // Clean up whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Extract all links from HTML
     * 
     * @param string $html HTML content
     * @param string|null $baseUrl Base URL for resolving relative links
     * @return array Array of URLs
     */
    public function extractLinks(string $html, ?string $baseUrl = null): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);

        $links = [];
        $anchorTags = $dom->getElementsByTagName('a');

        foreach ($anchorTags as $anchor) {
            $href = $anchor->getAttribute('href');
            if (!empty($href)) {
                // Resolve relative URLs if base URL is provided
                if ($baseUrl && !$this->isAbsoluteUrl($href)) {
                    $href = $this->resolveUrl($baseUrl, $href);
                }
                $links[] = $href;
            }
        }

        return array_unique($links);
    }

    /**
     * Check if a URL is absolute
     * 
     * @param string $url URL to check
     * @return bool True if absolute, false otherwise
     */
    private function isAbsoluteUrl(string $url): bool
    {
        return preg_match('/^https?:\/\//i', $url) === 1;
    }

    /**
     * Resolve relative URL to absolute URL
     * 
     * @param string $base Base URL
     * @param string $relative Relative URL
     * @return string Absolute URL
     */
    private function resolveUrl(string $base, string $relative): string
    {
        // Handle protocol-relative URLs
        if (strpos($relative, '//') === 0) {
            $scheme = parse_url($base, PHP_URL_SCHEME);
            return $scheme . ':' . $relative;
        }

        // Handle absolute URLs
        if ($this->isAbsoluteUrl($relative)) {
            return $relative;
        }

        // Parse base URL
        $baseParts = parse_url($base);
        $scheme = $baseParts['scheme'] ?? 'http';
        $host = $baseParts['host'] ?? '';
        $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
        $path = $baseParts['path'] ?? '/';

        // Handle absolute path
        if (strpos($relative, '/') === 0) {
            return "{$scheme}://{$host}{$port}{$relative}";
        }

        // Handle relative path
        // If base path ends with /, treat it as a directory
        if (substr($path, -1) === '/') {
            $path = $path . $relative;
        } else {
            $path = dirname($path) . '/' . $relative;
        }
        $path = $this->normalizePath($path);

        return "{$scheme}://{$host}{$port}{$path}";
    }

    /**
     * Normalize path by resolving . and ..
     * 
     * @param string $path Path to normalize
     * @return string Normalized path
     */
    private function normalizePath(string $path): string
    {
        $parts = explode('/', $path);
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                array_pop($normalized);
            } else {
                $normalized[] = $part;
            }
        }

        return '/' . implode('/', $normalized);
    }
}
