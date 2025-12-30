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
        $headers = $this->fingerprint->getHeaders();

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
        $headers = $this->fingerprint->getHeaders();

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
        $firstHeaders = $this->fingerprint->getHeaders('https://example.com');
        $firstUA = $firstHeaders['User-Agent'];
        
        // Rotate multiple times to ensure we get a different UA
        $foundDifferent = false;
        for ($i = 0; $i < 10; $i++) {
            $this->fingerprint->rotate();
            $headers = $this->fingerprint->getHeaders('https://example.com');
            if ($headers['User-Agent'] !== $firstUA) {
                $foundDifferent = true;
                break;
            }
        }
        
        $this->assertTrue($foundDifferent, 'User-Agent should change after rotation');
    }

    /**
     * Test Accept-Language rotation.
     */
    public function testAcceptLanguageRotation(): void
    {
        $firstHeaders = $this->fingerprint->getHeaders('https://example.com');
        $firstLang = $firstHeaders['Accept-Language'];
        
        // Rotate multiple times to ensure we get a different language
        $foundDifferent = false;
        for ($i = 0; $i < 10; $i++) {
            $this->fingerprint->rotate();
            $headers = $this->fingerprint->getHeaders('https://example.com');
            if ($headers['Accept-Language'] !== $firstLang) {
                $foundDifferent = true;
                break;
            }
        }
        
        $this->assertTrue($foundDifferent, 'Accept-Language should change after rotation');
    }

    /**
     * Test rotation on request.
     */
    public function testRotationOnRequest(): void
    {
        $fingerprint = $this->fingerprint->withRotationOnRequest(true);

        $firstHeaders = $fingerprint->getHeaders();
        $secondHeaders = $fingerprint->getHeaders();

        // Headers should be different due to rotation
        $this->assertNotEquals($firstHeaders['User-Agent'], $secondHeaders['User-Agent']);
    }

    /**
     * Test no rotation on request (default behavior).
     */
    public function testNoRotationOnRequest(): void
    {
        $firstHeaders = $this->fingerprint->getHeaders();
        $secondHeaders = $this->fingerprint->getHeaders();

        // Headers should be the same (no rotation by default)
        $this->assertEquals($firstHeaders['User-Agent'], $secondHeaders['User-Agent']);
        $this->assertEquals($firstHeaders['Accept-Language'], $secondHeaders['Accept-Language']);
    }

    /**
     * Test manual rotation.
     */
    public function testManualRotation(): void
    {
        $firstHeaders = $this->fingerprint->getHeaders();
        $this->fingerprint->rotate();
        $secondHeaders = $this->fingerprint->getHeaders();

        // Headers should be different after manual rotation
        $this->assertNotEquals($firstHeaders['User-Agent'], $secondHeaders['User-Agent']);
    }

    /**
     * Test all User-Agents are valid.
     */
    public function testValidUserAgents(): void
    {
        // Rotate several times and verify each UA is valid
        for ($i = 0; $i < 5; $i++) {
            $this->fingerprint->rotate();
            $headers = $this->fingerprint->getHeaders('https://example.com');
            
            $this->assertArrayHasKey('User-Agent', $headers);
            $this->assertIsString($headers['User-Agent']);
            $this->assertNotEmpty($headers['User-Agent']);
            $this->assertMatchesRegularExpression('/Mozilla\/5\.0/', $headers['User-Agent']);
        }
    }

    /**
     * Test all Accept-Language values are valid.
     */
    public function testValidAcceptLanguages(): void
    {
        // Rotate several times and verify each language is valid
        for ($i = 0; $i < 5; $i++) {
            $this->fingerprint->rotate();
            $headers = $this->fingerprint->getHeaders('https://example.com');
            
            $this->assertArrayHasKey('Accept-Language', $headers);
            $this->assertIsString($headers['Accept-Language']);
            $this->assertNotEmpty($headers['Accept-Language']);
            $this->assertMatchesRegularExpression('/^[a-z]{2}(-[A-Z]{2})?(,[a-z]{2}(-[A-Z]{2})?)*/', $headers['Accept-Language']);
        }
    }

    /**
     * Test Sec-Fetch header values.
     */
    public function testSecFetchValues(): void
    {
        $headers = $this->fingerprint->getHeaders();

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
        $headers = $this->fingerprint->getHeaders();

        // Check that Accept header exists and contains expected values
        $this->assertArrayHasKey('Accept', $headers);
        $this->assertStringContainsString('text/html', $headers['Accept']);
        $this->assertStringContainsString('application/xml', $headers['Accept']);
        
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
            $headers = $this->fingerprint->getHeaders();
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
        $customUA = 'CustomBot/1.0';
        $fingerprint = new HeaderFingerprint($customUA);
        $headers = $fingerprint->getHeaders('https://example.com');

        // Verify custom user agent is used
        $this->assertArrayHasKey('User-Agent', $headers);
        $this->assertEquals($customUA, $headers['User-Agent']);
    }

    /**
     * Test header consistency.
     */
    public function testHeaderConsistency(): void
    {
        $headers1 = $this->fingerprint->getHeaders();
        $headers2 = $this->fingerprint->getHeaders();

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
        $headers = $this->fingerprint->getHeaders();

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
        $headers = $this->fingerprint->getHeaders();

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
        $headers1 = $fp1->getHeaders();
        $headers2 = $fp2->getHeaders();

        // Different instances should be independent
        // (fp2 should not be affected by fp1 rotation)
        $this->assertNotEquals($headers1['User-Agent'], $headers2['User-Agent']);
    }
}