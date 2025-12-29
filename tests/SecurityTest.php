<?php

declare(strict_types=1);

namespace Tests;

use Omegaalfa\Utils\WebScraper\CookieJar;
use RuntimeException;

/**
 * Security test suite.
 */
class SecurityTest extends WebScraperTestCase
{
    /**
     * Test path traversal protection in CookieJar.
     */
    public function testPathTraversalProtectionCookieJar(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid path: null byte detected');

        $cookieJar = new CookieJar();
        $cookieJar->saveCookiesToFile('./test' . "\0" . '.json');
    }

    /**
     * Test cookie injection prevention.
     */
    public function testCookieInjectionPrevention(): void
    {
        $cookieJar = new CookieJar();
        
        $cookieJar->setCookie('test', "value\r\nSet-Cookie: malicious=value", 'example.com');
        
        $header = $cookieJar->getCookieHeader('https://example.com/');
        
        $this->assertStringNotContainsString("\r", $header);
        $this->assertStringNotContainsString("\n", $header);
        $this->assertStringContains('test=value', $header);
    }

    /**
     * Test information disclosure prevention.
     */
    public function testInformationDisclosurePrevention(): void
    {
        $cookieJar = new CookieJar();
        
        $cookieJar->setCookie('session', 'abc123', 'example.com');
        $cookieJar->setCookie('user_id', '456', 'api.example.com');
        
        $cookies = $cookieJar->getCookiesForUrl('https://example.com/');
        $this->assertArrayHasKey('session', $cookies);
        $this->assertArrayNotHasKey('user_id', $cookies);
    }

    /**
     * Test SameSite validation in cookies.
     */
    public function testSameSiteValidation(): void
    {
        $cookieJar = new CookieJar();
        
        // Valid SameSite values
        $cookieJar->setCookie('test1', 'value1', 'example.com', '/', 0, false, false, 'Strict');
        $cookieJar->setCookie('test2', 'value2', 'example.com', '/', 0, false, false, 'Lax');
        $cookieJar->setCookie('test3', 'value3', 'example.com', '/', 0, false, false, 'None');
        
        $cookies = $cookieJar->getAllCookies();
        $this->assertArrayHasKey('test1', $cookies);
        $this->assertArrayHasKey('test2', $cookies);
        $this->assertArrayHasKey('test3', $cookies);
        
        // Invalid SameSite should throw exception
        $this->expectException(\InvalidArgumentException::class);
        $cookieJar->setCookie('test4', 'value4', 'example.com', '/', 0, false, false, 'Invalid');
    }
}