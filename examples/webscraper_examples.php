<?php

require_once __DIR__ . '/../vendor/autoload.php';

use OmegaAlfa\Utils\WebScraper;

/**
 * Example: Basic web scraping
 */
function basicExample()
{
    echo "=== Basic Example ===\n\n";
    
    $scraper = new WebScraper();
    
    try {
        // Fetch a simple HTML page
        $html = '<html><body><h1>Hello World</h1><p>This is a test page.</p></body></html>';
        
        // Extract text
        $text = $scraper->extractText($html);
        echo "Extracted text: {$text}\n\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}

/**
 * Example: Parsing HTML with CSS selectors
 */
function parsingExample()
{
    echo "=== Parsing Example ===\n\n";
    
    $scraper = new WebScraper();
    
    $html = '
        <html>
            <body>
                <div class="content">
                    <h1>Title 1</h1>
                    <p>Paragraph 1</p>
                </div>
                <div class="content">
                    <h1>Title 2</h1>
                    <p>Paragraph 2</p>
                </div>
                <div id="footer">
                    <p>Footer content</p>
                </div>
            </body>
        </html>
    ';
    
    try {
        // Select by class
        $contentDivs = $scraper->parseHtml($html, '.content');
        echo "Found " . count($contentDivs) . " content divs\n";
        
        // Select by ID
        $footer = $scraper->parseHtml($html, '#footer');
        echo "Found " . count($footer) . " footer element\n";
        
        // Select by tag
        $headings = $scraper->parseHtml($html, 'h1');
        echo "Found " . count($headings) . " h1 elements\n\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}

/**
 * Example: Extracting links
 */
function linksExample()
{
    echo "=== Links Example ===\n\n";
    
    $scraper = new WebScraper();
    
    $html = '
        <html>
            <body>
                <a href="https://example.com">Absolute Link</a>
                <a href="/about">Relative Link 1</a>
                <a href="contact.html">Relative Link 2</a>
                <a href="//cdn.example.com/file.js">Protocol-relative Link</a>
            </body>
        </html>
    ';
    
    try {
        // Extract links without base URL
        $links = $scraper->extractLinks($html);
        echo "Links without base URL:\n";
        foreach ($links as $link) {
            echo "  - {$link}\n";
        }
        
        echo "\nLinks with base URL (https://example.com/page/):\n";
        // Extract links with base URL resolution
        $links = $scraper->extractLinks($html, 'https://example.com/page/');
        foreach ($links as $link) {
            echo "  - {$link}\n";
        }
        echo "\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}

/**
 * Example: Custom configuration
 */
function configurationExample()
{
    echo "=== Configuration Example ===\n\n";
    
    // Create scraper with custom options
    $scraper = new WebScraper([
        'timeout' => 60,
        'user_agent' => 'My Custom Bot 1.0',
        'verify_ssl' => false
    ]);
    
    // Or use chainable methods
    $scraper->setUserAgent('Another Bot 2.0')
            ->setTimeout(30)
            ->addHeader('Accept', 'text/html')
            ->addHeader('Accept-Language', 'en-US');
    
    echo "Scraper configured with custom settings\n";
    echo "This example demonstrates configuration options\n\n";
}

/**
 * Example: Text extraction with cleanup
 */
function textExtractionExample()
{
    echo "=== Text Extraction Example ===\n\n";
    
    $scraper = new WebScraper();
    
    $html = '
        <html>
            <head>
                <title>Page Title</title>
                <script>console.log("This should not appear");</script>
                <style>body { color: red; }</style>
            </head>
            <body>
                <h1>Main Title</h1>
                <p>First paragraph with <strong>bold text</strong>.</p>
                <p>Second paragraph with <em>italic text</em>.</p>
                <script>alert("Another script");</script>
            </body>
        </html>
    ';
    
    try {
        $text = $scraper->extractText($html);
        echo "Extracted clean text:\n";
        echo "{$text}\n\n";
        
        // Verify scripts and styles are removed
        if (strpos($text, 'console.log') === false && strpos($text, 'alert') === false) {
            echo "✓ Scripts successfully removed\n";
        }
        if (strpos($text, 'color: red') === false) {
            echo "✓ Styles successfully removed\n";
        }
        echo "\n";
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n\n";
    }
}

// Run all examples
echo "OmegaAlfa\\Utils\\WebScraper Examples\n";
echo "====================================\n\n";

basicExample();
parsingExample();
linksExample();
configurationExample();
textExtractionExample();

echo "All examples completed successfully!\n";
