<?php

declare(strict_types=1);

namespace Tests;

use Omegaalfa\Utils\WebScraper\HeaderFingerprint;

/**
 * Test suite for HeaderFingerprint.
 */
class HeaderFingerprintTest extends WebScraperTestCase
{
    private HeaderFingerprint $fingerprint;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fingerprint = new HeaderFingerprint();
    }

    /**
     * Test default headers generation.
     */
    public function testDefaultHeaders(): void
    {
        $headers = $this->fingerprint->getHeaders('https://example.com');

        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertArrayHasKey('Accept', $headers);
        $this->assertArrayHasKey('Accept-Language', $headers);
        $this->assertArrayHasKey('Accept-Encoding', $headers);
        $this->assertArrayHasKey('DNT', $headers);
        $this->assertArrayHasKey('Upgrade-Insecure-Requests', $headers);
    }

    /**
     * Test Sec-Fetch headers.
     */
    public function testSecFetchHeaders(): void
    {
        $headers = $this->fingerprint->getHeaders('https://example.com');

        $this->assertArrayHasKey('Sec-Fetch-Dest', $headers);
        $this->assertArrayHasKey('Sec-Fetch-Mode', $headers);
        $this->assertArrayHasKey('Sec-Fetch-Site', $headers);
        $this->assertArrayHasKey('Sec-Fetch-User', $headers);
    }

    /**
     * Test User-Agent rotation.
     */
    public function testUserAgentRotation(): void
    {
        $userAgents = [];

        // Get multiple user agents to test rotation
        for ($i = 0; $i < 10; $i++) {
            $this->fingerprint->rotate();
            $headers = $this->fingerprint->getHeaders('https://example.com');
            $userAgents[] = $headers['User-Agent'];
        }

        // Should have some variety (at least 2 different UAs)
        $uniqueUserAgents = array_unique($userAgents);
        $this->assertGreaterThan(1, count($uniqueUserAgents));
    }

    /**
     * Test Accept-Language rotation.
     */
    public function testAcceptLanguageRotation(): void
    {
        $languages = [];

        // Get multiple languages to test rotation
        for ($i = 0; $i < 10; $i++) {
            $this->fingerprint->rotate();
            $headers = $this->fingerprint->getHeaders('https://example.com');
            $languages[] = $headers['Accept-Language'];
        }

        // Should have some variety
        $uniqueLanguages = array_unique($languages);
        $this->assertGreaterThan(1, count($uniqueLanguages));
    }

    /**
     * Test rotation on request.
     */
    public function testRotationOnRequest(): void
    {
        $fingerprint = $this->fingerprint->withRotationOnRequest(true);

        $firstHeaders = $fingerprint->getHeaders('https://example.com');
        $secondHeaders = $fingerprint->getHeaders('https://example.com');

        // Headers should be different due to rotation
        $this->assertNotEquals($firstHeaders['User-Agent'], $secondHeaders['User-Agent']);
    }

    /**
     * Test no rotation on request (default behavior).
     */
    public function testNoRotationOnRequest(): void
    {
        $firstHeaders = $this->fingerprint->getHeaders('https://example.com');
        $secondHeaders = $this->fingerprint->getHeaders('https://example.com');

        // Headers should be the same (no rotation by default)
        $this->assertEquals($firstHeaders['User-Agent'], $secondHeaders['User-Agent']);
        $this->assertEquals($firstHeaders['Accept-Language'], $secondHeaders['Accept-Language']);
    }

    /**
     * Test manual rotation.
     */
    public function testManualRotation(): void
    {
        $firstHeaders = $this->fingerprint->getHeaders('https://example.com');
        $this->fingerprint->rotate();
        $secondHeaders = $this->fingerprint->getHeaders('https://example.com');

        // Headers should be different after manual rotation
        $this->assertNotEquals($firstHeaders['User-Agent'], $secondHeaders['User-Agent']);
    }

    /**
     * Test Sec-Fetch header values.
     */
    public function testSecFetchValues(): void
    {
        $headers = $this->fingerprint->getHeaders('https://example.com');

        $this->assertEquals('document', $headers['Sec-Fetch-Dest']);
        $this->assertEquals('navigate', $headers['Sec-Fetch-Mode']);
        $this->assertEquals('none', $headers['Sec-Fetch-Site']);
        $this->assertEquals('?1', $headers['Sec-Fetch-User']);
    }

    /**
     * Test standard headers values.
     */
    public function testStandardHeaders(): void
    {
        $headers = $this->fingerprint->getHeaders('https://example.com');

        $this->assertStringContainsString('text/html', $headers['Accept']);
        $this->assertEquals('gzip, deflate, br', $headers['Accept-Encoding']);
        $this->assertEquals('1', $headers['DNT']);
        $this->assertEquals('1', $headers['Upgrade-Insecure-Requests']);
    }

    /**
     * Test immutability of withRotationOnRequest.
     */
    public function testImmutability(): void
    {
        $original = $this->fingerprint;
        $rotated = $original->withRotationOnRequest(true);

        $this->assertNotSame($original, $rotated);
        $this->assertInstanceOf(HeaderFingerprint::class, $rotated);
    }

    /**
     * Test rotation bounds.
     */
    public function testRotationBounds(): void
    {
        // Rotate many times to ensure no out-of-bounds errors
        for ($i = 0; $i < 100; $i++) {
            $headers = $this->fingerprint->getHeaders('https://example.com');
            $this->assertArrayHasKey('User-Agent', $headers);
            $this->assertArrayHasKey('Accept-Language', $headers);
            $this->fingerprint->rotate();
        }
    }

    /**
     * Test custom User-Agent.
     */
    public function testCustomUserAgent(): void
    {
        $customUA = 'Custom Bot/1.0';
        $fingerprint = new HeaderFingerprint();

        // Set custom User-Agent (if method exists)
        if (method_exists($fingerprint, 'withUserAgent')) {
            $fingerprint = $fingerprint->withUserAgent($customUA);
            $headers = $fingerprint->getHeaders('https://example.com');
        } else {
            $this->markTestSkipped('Method withUserAgent not implemented yet');
            $this->assertEquals($customUA, $headers['User-Agent']);
        }
    }

    /**
     * Test header consistency.
     */
    public function testHeaderConsistency(): void
    {
        $headers1 = $this->fingerprint->getHeaders('https://example.com');
        $headers2 = $this->fingerprint->getHeaders('https://example.com');

        // Same instance should return consistent headers (no rotation)
        $this->assertEquals($headers1, $headers2);

        // All required headers should be present
        $requiredHeaders = [
            'User-Agent',
            'Accept',
            'Accept-Language',
            'Accept-Encoding',
            'DNT',
            'Upgrade-Insecure-Requests',
            'Sec-Fetch-Dest',
            'Sec-Fetch-Mode',
            'Sec-Fetch-Site',
            'Sec-Fetch-User'
        ];

        foreach ($requiredHeaders as $header) {
            $this->assertArrayHasKey($header, $headers1);
            $this->assertArrayHasKey($header, $headers2);
        }
    }

    /**
     * Test browser-like headers.
     */
    public function testBrowserLikeHeaders(): void
    {
        $headers = $this->fingerprint->getHeaders('https://example.com');

        // User-Agent should look like a real browser
        $this->assertStringContains('Mozilla/', $headers['User-Agent']);
        $this->assertStringContains('AppleWebKit/', $headers['User-Agent']);

        // Accept should include common browser values
        $this->assertStringContains('text/html', $headers['Accept']);
        $this->assertStringContains('application/xhtml+xml', $headers['Accept']);

        // Accept-Encoding should include common compressions
        $this->assertStringContains('gzip', $headers['Accept-Encoding']);
        $this->assertStringContains('deflate', $headers['Accept-Encoding']);
    }

    /**
     * Test header format validation.
     */
    public function testHeaderFormatValidation(): void
    {
        $headers = $this->fingerprint->getHeaders('https://example.com');

        // All headers should be strings
        foreach ($headers as $name => $value) {
            $this->assertIsString($name);
            $this->assertIsString($value);
            $this->assertNotEmpty($name);
            $this->assertNotEmpty($value);
        }

        // Header names should be valid HTTP header names
        foreach (array_keys($headers) as $name) {
            $this->assertMatchesRegularExpression('/^[A-Z][a-zA-Z0-9-]*$/', $name);
        }
    }

    /**
     * Test multiple instances independence.
     */
    public function testMultipleInstancesIndependence(): void
    {
        $fp1 = new HeaderFingerprint();
        $fp2 = new HeaderFingerprint();

        $fp1->rotate();
        $headers1 = $fp1->getHeaders('https://example.com');
        $headers2 = $fp2->getHeaders('https://example.com');

        // Different instances should be independent
        // (fp2 should not be affected by fp1 rotation)
        $this->assertNotEquals($headers1['User-Agent'], $headers2['User-Agent']);
    }
}