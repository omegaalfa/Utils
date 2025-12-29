<?php

declare(strict_types=1);

namespace Omegaalfa\Utils\WebScraper;

/**
 * Generates realistic browser fingerprints with rotation.
 *
 * Includes modern User-Agents and Sec-Fetch-* headers to evade WAF detection.
 */
class HeaderFingerprint
{
    private const array USER_AGENTS = [
        // Chrome on Windows
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        // Chrome on macOS
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        // Firefox on Windows
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0',
        // Safari on macOS
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Safari/605.1.15',
        // Safari on iOS
        'Mozilla/5.0 (iPhone; CPU iPhone OS 17_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.2 Mobile/15E148 Safari/604.1',
        // Edge on Windows
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
    ];

    private const array ACCEPT_LANGUAGES = [
        'en-US,en;q=0.9',
        'pt-BR,pt;q=0.8,en-US;q=0.6,en;q=0.4',
        'es-ES,es;q=0.7,en;q=0.3',
    ];

    private int $currentIndex = 0;
    private int $languageIndex = 0;
    private bool $rotateOnRequest = false;

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
     * Rotate to next fingerprint.
     */
    public function rotate(): void
    {
        $this->currentIndex = ($this->currentIndex + 1) % count(self::USER_AGENTS);
        $this->languageIndex = ($this->languageIndex + 1) % count(self::ACCEPT_LANGUAGES);
    }

    /**
     * Get current User-Agent.
     *
     * @return string
     */
    public function getUserAgent(): string
    {
        return self::USER_AGENTS[$this->currentIndex];
    }

    /**
     * Get headers for a request.
     *
     * @param string $url Request URL
     * @param string $referer Referer URL (optional)
     * @param bool $isRedirect Whether this is a redirect
     * @return array<string, string>
     */
    public function getHeaders(string $url, string $referer = '', bool $isRedirect = false): array
    {
        if ($this->rotateOnRequest) {
            $this->rotate();
        }

        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'https';
        $isSecure = $scheme === 'https';

        $headers = [
            'User-Agent' => $this->getUserAgent(),
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language' => self::ACCEPT_LANGUAGES[$this->languageIndex],
            'Accept-Encoding' => 'gzip, deflate, br',
            'Cache-Control' => 'max-age=0',
            'Upgrade-Insecure-Requests' => '1',
            'Sec-Fetch-Dest' => 'document',
            'Sec-Fetch-Mode' => 'navigate',
            'Sec-Fetch-User' => '?1',
        ];

        // Sec-Fetch-Site depends on referer
        if ($referer === '') {
            $headers['Sec-Fetch-Site'] = 'none';
        } else {
            $refererHost = parse_url($referer, PHP_URL_HOST);
            $currentHost = $parsed['host'] ?? '';
            
            if ($refererHost === $currentHost) {
                $headers['Sec-Fetch-Site'] = 'same-origin';
            } elseif ($refererHost && $this->isSameSite($refererHost, $currentHost)) {
                $headers['Sec-Fetch-Site'] = 'same-site';
            } else {
                $headers['Sec-Fetch-Site'] = 'cross-site';
            }

            $headers['Referer'] = $referer;
        }

        // DNT (Do Not Track)
        $headers['DNT'] = '1';

        // Connection (HTTP/1.1)
        $headers['Connection'] = 'keep-alive';

        return $headers;
    }

    /**
     * Merge custom headers with fingerprint headers.
     *
     * Custom headers take precedence.
     *
     * @param array<string, string> $customHeaders
     * @param string $url
     * @param string $referer
     * @param bool $isRedirect
     * @return array<string, string>
     */
    public function mergeHeaders(array $customHeaders, string $url, string $referer = '', bool $isRedirect = false): array
    {
        $fingerprintHeaders = $this->getHeaders($url, $referer, $isRedirect);
        
        // Custom headers override fingerprint headers
        return array_merge($fingerprintHeaders, $customHeaders);
    }

    /**
     * Get headers for JSON API request.
     *
     * @param string $url
     * @param string $referer
     * @return array<string, string>
     */
    public function getJsonHeaders(string $url, string $referer = ''): array
    {
        $headers = [
            'User-Agent' => $this->getUserAgent(),
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => self::ACCEPT_LANGUAGES[$this->languageIndex],
            'Accept-Encoding' => 'gzip, deflate, br',
            'Content-Type' => 'application/json',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => $referer === '' ? 'none' : 'same-origin',
            'DNT' => '1',
            'Connection' => 'keep-alive',
        ];

        if ($referer !== '') {
            $headers['Referer'] = $referer;
        }

        return $headers;
    }

    /**
     * Check if two hosts are same-site (share eTLD+1).
     *
     * Simplified check: same second-level domain.
     *
     * @param string $host1
     * @param string $host2
     * @return bool
     */
    private function isSameSite(string $host1, string $host2): bool
    {
        $domain1 = $this->extractDomain($host1);
        $domain2 = $this->extractDomain($host2);
        
        return $domain1 === $domain2;
    }

    /**
     * Extract second-level domain (e.g., example.com from www.example.com).
     *
     * @param string $host
     * @return string
     */
    private function extractDomain(string $host): string
    {
        $parts = explode('.', $host);
        
        if (count($parts) < 2) {
            return $host;
        }

        // Return last 2 parts (second-level domain)
        return implode('.', array_slice($parts, -2));
    }

    /**
     * Get random User-Agent (for testing/variety).
     *
     * @return string
     */
    public static function getRandomUserAgent(): string
    {
        return self::USER_AGENTS[array_rand(self::USER_AGENTS)];
    }
}
