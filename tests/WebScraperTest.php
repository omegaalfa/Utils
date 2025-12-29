<?php

namespace OmegaAlfa\Utils\Tests;

use OmegaAlfa\Utils\WebScraper;
use PHPUnit\Framework\TestCase;
use Exception;

class WebScraperTest extends TestCase
{
    private $scraper;

    protected function setUp(): void
    {
        $this->scraper = new WebScraper();
    }

    public function testConstructorWithOptions()
    {
        $scraper = new WebScraper([
            'timeout' => 60,
            'user_agent' => 'Custom Agent'
        ]);
        
        $this->assertInstanceOf(WebScraper::class, $scraper);
    }

    public function testSetUserAgent()
    {
        $userAgent = 'Custom User Agent';
        $result = $this->scraper->setUserAgent($userAgent);
        
        $this->assertInstanceOf(WebScraper::class, $result);
    }

    public function testSetTimeout()
    {
        $result = $this->scraper->setTimeout(60);
        
        $this->assertInstanceOf(WebScraper::class, $result);
    }

    public function testSetHeaders()
    {
        $headers = [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer token'
        ];
        $result = $this->scraper->setHeaders($headers);
        
        $this->assertInstanceOf(WebScraper::class, $result);
    }

    public function testAddHeader()
    {
        $result = $this->scraper->addHeader('Content-Type', 'application/json');
        
        $this->assertInstanceOf(WebScraper::class, $result);
    }

    public function testFetchWithInvalidUrl()
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid URL provided');
        
        $this->scraper->fetch('not-a-valid-url');
    }

    public function testExtractText()
    {
        $html = '<html><body><h1>Title</h1><p>This is a paragraph.</p><script>alert("test");</script></body></html>';
        $text = $this->scraper->extractText($html);
        
        $this->assertStringContainsString('Title', $text);
        $this->assertStringContainsString('This is a paragraph', $text);
        $this->assertStringNotContainsString('alert', $text);
        $this->assertStringNotContainsString('<h1>', $text);
    }

    public function testExtractLinks()
    {
        $html = '<html><body><a href="https://example.com">Link 1</a><a href="/relative">Link 2</a><a href="page.html">Link 3</a></body></html>';
        $links = $this->scraper->extractLinks($html);
        
        $this->assertIsArray($links);
        $this->assertContains('https://example.com', $links);
        $this->assertContains('/relative', $links);
        $this->assertContains('page.html', $links);
    }

    public function testExtractLinksWithBaseUrl()
    {
        $html = '<html><body><a href="/page1">Link 1</a><a href="page2.html">Link 2</a></body></html>';
        $baseUrl = 'https://example.com/path/';
        $links = $this->scraper->extractLinks($html, $baseUrl);
        
        $this->assertIsArray($links);
        $this->assertContains('https://example.com/page1', $links);
        $this->assertContains('https://example.com/path/page2.html', $links);
    }

    public function testParseHtmlWithTagSelector()
    {
        $html = '<html><body><div>Content 1</div><div>Content 2</div></body></html>';
        $elements = $this->scraper->parseHtml($html, 'div');
        
        $this->assertIsArray($elements);
        $this->assertGreaterThan(0, count($elements));
    }

    public function testParseHtmlWithClassSelector()
    {
        $html = '<html><body><div class="test">Content 1</div><div class="other">Content 2</div></body></html>';
        $elements = $this->scraper->parseHtml($html, '.test');
        
        $this->assertIsArray($elements);
        $this->assertGreaterThan(0, count($elements));
    }

    public function testParseHtmlWithIdSelector()
    {
        $html = '<html><body><div id="main">Content</div></body></html>';
        $elements = $this->scraper->parseHtml($html, '#main');
        
        $this->assertIsArray($elements);
        $this->assertEquals(1, count($elements));
    }

    public function testParseHtmlWithTagAndClassSelector()
    {
        $html = '<html><body><div class="content">Div Content</div><span class="content">Span Content</span></body></html>';
        $elements = $this->scraper->parseHtml($html, 'div.content');
        
        $this->assertIsArray($elements);
        $this->assertGreaterThan(0, count($elements));
    }

    public function testChainableMethods()
    {
        $result = $this->scraper
            ->setUserAgent('Custom Agent')
            ->setTimeout(30)
            ->addHeader('Accept', 'application/json');
        
        $this->assertInstanceOf(WebScraper::class, $result);
    }
}
