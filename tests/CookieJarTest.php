<?php

declare(strict_types=1);

namespace Tests;

use Omegaalfa\Utils\WebScraper\CookieJar;
use JsonException;

/**
 * Test suite for CookieJar.
 */
class CookieJarTest extends WebScraperTestCase
{
    private CookieJar $cookieJar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cookieJar = new CookieJar();
    }

    /**
     * Test setting and getting cookies.
     */
    public function testSetAndGetCookie(): void
    {
        $this->cookieJar->setCookie('session', 'abc123', 'example.com', '/', time() + 3600);

        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/');
        $this->assertArrayHasKey('session', $cookies);
        $this->assertEquals('abc123', $cookies['session']);
    }

    /**
     * Test cookie with all attributes.
     */
    public function testCookieWithAllAttributes(): void
    {
        $this->cookieJar->setCookie(
            'session',
            'abc123',
            'example.com',
            '/admin',
            time() + 3600, // 1 hour from now
            true,        // secure
            true,        // httpOnly
            'Lax'        // sameSite
        );

        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/admin', true);
        $this->assertArrayHasKey('session', $cookies);
        $this->assertEquals('abc123', $cookies['session']);
    }

    /**
     * Test session cookie (no expiration).
     */
    public function testSessionCookie(): void
    {
        $this->cookieJar->setCookie('temp', 'value', 'example.com');

        $allCookies = $this->cookieJar->getCookies();
        $this->assertEquals(0, $allCookies['temp']['expires']);
    }

    /**
     * Test cookie domain matching.
     */
    public function testDomainMatching(): void
    {
        // Set cookie for example.com
        $this->cookieJar->setCookie('session', 'value', 'example.com');

        // Should match subdomains
        $cookies = $this->cookieJar->getCookiesForUrl('https://www.example.com/');
        $this->assertArrayHasKey('session', $cookies);

        $cookies = $this->cookieJar->getCookiesForUrl('https://api.example.com/');
        $this->assertArrayHasKey('session', $cookies);

        // Should not match different domain
        $cookies = $this->cookieJar->getCookiesForUrl('https://other.com/');
        $this->assertArrayNotHasKey('session', $cookies);
    }

    /**
     * Test cookie path matching.
     */
    public function testPathMatching(): void
    {
        // Set cookie for /admin path
        $this->cookieJar->setCookie('admin', 'value', 'example.com', '/admin');

        // Should match /admin and subpaths
        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/admin');
        $this->assertArrayHasKey('admin', $cookies);

        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/admin/users');
        $this->assertArrayHasKey('admin', $cookies);

        // Should not match / or other paths
        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/');
        $this->assertArrayNotHasKey('admin', $cookies);

        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/blog');
        $this->assertArrayNotHasKey('admin', $cookies);
    }

    /**
     * Test secure cookie.
     */
    public function testSecureCookie(): void
    {
        $this->cookieJar->setCookie('secure', 'value', 'example.com', '/', 0, true);

        // Should be sent over HTTPS
        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/', true);
        $this->assertArrayHasKey('secure', $cookies);

        // Should not be sent over HTTP
        $cookies = $this->cookieJar->getCookiesForUrl('http://example.com/', false);
        $this->assertArrayNotHasKey('secure', $cookies);
    }

    /**
     * Test HttpOnly cookie.
     */
    public function testHttpOnlyCookie(): void
    {
        $this->cookieJar->setCookie('httponly', 'value', 'example.com', '/', 0, false, true);

        $allCookies = $this->cookieJar->getCookies();
        $this->assertTrue($allCookies['httponly']['httpOnly']);
    }

    /**
     * Test SameSite attribute.
     */
    public function testSameSiteAttribute(): void
    {
        $this->cookieJar->setCookie('lax', 'value', 'example.com', '/', 0, false, false, 'Lax');
        $this->cookieJar->setCookie('strict', 'value', 'example.com', '/', 0, false, false, 'Strict');
        $this->cookieJar->setCookie('none', 'value', 'example.com', '/', 0, true, false, 'None');

        $allCookies = $this->cookieJar->getCookies();

        $this->assertEquals('Lax', $allCookies['lax']['sameSite']);
        $this->assertEquals('Strict', $allCookies['strict']['sameSite']);
        $this->assertEquals('None', $allCookies['none']['sameSite']);
    }

    /**
     * Test invalid SameSite value.
     */
    public function testInvalidSameSite(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid SameSite value: Invalid');
        
        $this->cookieJar->setCookie('invalid', 'value', 'example.com', '/', 0, false, false, 'Invalid');
    }

    /**
     * Test cookie expiration.
     */
    public function testCookieExpiration(): void
    {
        // Set cookie that expires in 1 second
        $this->cookieJar->setCookie('temp', 'value', 'example.com', '/', time() + 1);

        // Should be present initially
        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/');
        $this->assertArrayHasKey('temp', $cookies);

        // Wait for expiration
        sleep(2);

        // Should be gone
        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/');
        $this->assertArrayNotHasKey('temp', $cookies);
    }

    /**
     * Test cookie overwriting.
     */
    public function testCookieOverwriting(): void
    {
        $this->cookieJar->setCookie('test', 'value1', 'example.com');
        $this->cookieJar->setCookie('test', 'value2', 'example.com');

        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/');
        $this->assertEquals('value2', $cookies['test']);
    }

    /**
     * Test TLD-only domain rejection (security).
     */
    public function testTldOnlyDomainRejection(): void
    {
        // These should be silently rejected
        $this->cookieJar->setCookie('bad1', 'value', '.com');
        $this->cookieJar->setCookie('bad2', 'value', 'com');
        $this->cookieJar->setCookie('bad3', 'value', '.org');
        $this->cookieJar->setCookie('bad4', 'value', 'org');

        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/');
        $this->assertEmpty($cookies);
    }

    /**
     * Test saving and loading cookies from file.
     */
    public function testSaveAndLoadCookies(): void
    {
        // Set some cookies
        $this->cookieJar->setCookie('session', 'abc123', 'example.com', '/', time() + 3600, true, true, 'Lax');
        $this->cookieJar->setCookie('temp', 'value', 'example.com', '/temp', 0, false, false, null);

        $filename = './test_cookies.json';

        // Save cookies
        $this->cookieJar->saveCookiesToFile($filename);

        // Verify file was created with correct permissions
        $this->assertFileExists($filename);
        $this->assertFilePermissions($filename, 0600);

        // Create new cookie jar and load cookies
        $newCookieJar = new CookieJar();
        $newCookieJar->loadCookiesFromFile($filename);

        // Verify cookies were loaded
        $cookiesSecure = $newCookieJar->getCookiesForUrl('https://example.com/', true); // secure=true for HTTPS
        $cookiesInsecure = $newCookieJar->getCookiesForUrl('https://example.com/temp', false); // secure=false, path matches
        $this->assertArrayHasKey('session', $cookiesSecure);
        $this->assertArrayHasKey('temp', $cookiesInsecure);

        $allCookies = $newCookieJar->getAllCookies();
        $sessionCookie = $allCookies['session'];
        $this->assertEquals('abc123', $sessionCookie['value']);
        $this->assertEquals('example.com', $sessionCookie['domain']);
        $this->assertEquals('/', $sessionCookie['path']);
        $this->assertTrue($sessionCookie['secure']);
        $this->assertTrue($sessionCookie['httpOnly']);
        $this->assertEquals('Lax', $sessionCookie['sameSite']);
    }

    /**
     * Test loading invalid JSON file.
     */
    public function testLoadInvalidJsonFile(): void
    {
        $filename = './test_invalid_cookies.json';
        file_put_contents($filename, 'invalid json content');

        // Should not throw exception (fail silently for security)
        $this->cookieJar->loadCookiesFromFile($filename);
        
        // Cookies should remain empty
        $this->assertEquals(0, $this->cookieJar->countCookies());
        
        unlink($filename);
    }

    /**
     * Test loading corrupted cookie data.
     */
    public function testLoadCorruptedCookieData(): void
    {
        $filename = './test_corrupted_cookies.json';

        // Create file with invalid cookie structure
        $corruptedData = [
            'session' => [
                'value' => 'test',
                // Missing required fields
            ],
            'valid' => [
                'value' => 'good',
                'domain' => 'example.com',
                'path' => '/',
                'expires' => 0,
                'secure' => false,
                'httpOnly' => false,
                'sameSite' => null
            ]
        ];
        file_put_contents($filename, json_encode($corruptedData));

        // Should not throw exception, just skip invalid cookies
        $this->cookieJar->loadCookiesFromFile($filename);
        
        // Only valid cookie should be loaded
        $this->assertEquals(1, $this->cookieJar->countCookies());
        $allCookies = $this->cookieJar->getAllCookies();
        $this->assertArrayHasKey('valid', $allCookies);
        $this->assertArrayNotHasKey('session', $allCookies);
        
        unlink($filename);
    }

    /**
     * Test path traversal protection in saveCookiesToFile.
     */
    public function testPathTraversalProtection(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Path traversal detected');

        $this->cookieJar->saveCookiesToFile('../../../etc/passwd');
    }

    /**
     * Test system directory protection.
     */
    public function testSystemDirectoryProtection(): void
    {
        $systemPaths = [
            '/etc/passwd',
            '/var/log/auth.log',
            '/usr/bin/bash',
            '/bin/sh',
            '/proc/self/environ'
        ];

        foreach ($systemPaths as $path) {
            $this->expectException(\RuntimeException::class);
            $this->cookieJar->saveCookiesToFile($path);
        }
    }

    /**
     * Test null byte protection.
     */
    public function testNullByteProtection(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid path: null byte detected');

        $this->cookieJar->saveCookiesToFile('./test' . "\0" . '.json');
    }

    /**
     * Test Windows path traversal protection (backslash rejection).
     */
    public function testWindowsPathTraversalProtection(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid path: backslashes not allowed');

        // This was the actual attack vector that created the malicious file
        $this->cookieJar->saveCookiesToFile('..\\..\\..\\windows\\system32\\config\\sam');
    }

    /**
     * Test clearing all cookies.
     */
    public function testClearCookies(): void
    {
        $this->cookieJar->setCookie('test1', 'value1', 'example.com');
        $this->cookieJar->setCookie('test2', 'value2', 'example.com');

        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/');
        $this->assertCount(2, $cookies);

        $this->cookieJar->clearCookies();

        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/');
        $this->assertEmpty($cookies);
    }

    /**
     * Test getting all cookies.
     */
    public function testGetAllCookies(): void
    {
        $this->cookieJar->setCookie('cookie1', 'value1', 'example.com');
        $this->cookieJar->setCookie('cookie2', 'value2', 'other.com');

        $allCookies = $this->cookieJar->getAllCookies();

        $this->assertCount(2, $allCookies);
        $this->assertArrayHasKey('cookie1', $allCookies);
        $this->assertArrayHasKey('cookie2', $allCookies);
    }

    /**
     * Test cookie header generation.
     */
    public function testCookieHeaderGeneration(): void
    {
        $this->cookieJar->setCookie('session', 'abc123', 'example.com');
        $this->cookieJar->setCookie('theme', 'dark', 'example.com');

        $header = $this->cookieJar->getCookieHeader('https://example.com/');

        // Should contain both cookies
        $this->assertStringContainsString('session=abc123', $header);
        $this->assertStringContainsString('theme=dark', $header);
    }

    /**
     * Test cookie header with special characters.
     */
    public function testCookieHeaderSpecialCharacters(): void
    {
        $this->cookieJar->setCookie('special', 'value with spaces', 'example.com');
        $this->cookieJar->setCookie('encoded', 'value%20encoded', 'example.com');

        $header = $this->cookieJar->getCookieHeader('https://example.com/');

        $this->assertStringContainsString('special=value with spaces', $header);
        $this->assertStringContainsString('encoded=value%20encoded', $header);
    }

    /**
     * Test cookie removal.
     */
    public function testCookieRemoval(): void
    {
        $this->cookieJar->setCookie('test', 'value', 'example.com');

        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/');
        $this->assertArrayHasKey('test', $cookies);

        $this->cookieJar->removeCookie('test', 'example.com');

        $cookies = $this->cookieJar->getCookiesForUrl('https://example.com/');
        $this->assertArrayNotHasKey('test', $cookies);
    }

    /**
     * Test cookie count.
     */
    public function testCookieCount(): void
    {
        $this->assertEquals(0, $this->cookieJar->countCookies());

        $this->cookieJar->setCookie('test1', 'value1', 'example.com');
        $this->assertEquals(1, $this->cookieJar->countCookies());

        $this->cookieJar->setCookie('test2', 'value2', 'example.com');
        $this->assertEquals(2, $this->cookieJar->countCookies());

        $this->cookieJar->clearCookies();
        $this->assertEquals(0, $this->cookieJar->countCookies());
    }
}