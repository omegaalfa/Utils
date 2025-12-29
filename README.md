# Utils

A PHP utility package providing web scraping capabilities and other useful tools.

## Installation

Install via Composer:

```bash
composer require omegaalfa/utils
```

## Features

- **WebScraper**: A powerful and flexible web scraping utility
  - HTTP/HTTPS request handling
  - Custom headers and user agents
  - HTML parsing with CSS selector support
  - Text extraction
  - Link extraction with relative URL resolution
  - Timeout and redirect configuration
  - SSL verification options

## Usage

### WebScraper

#### Basic Usage

```php
use OmegaAlfa\Utils\WebScraper;

$scraper = new WebScraper();

// Fetch content from a URL
$html = $scraper->fetch('https://example.com');
echo $html;
```

#### Custom Configuration

```php
$scraper = new WebScraper([
    'timeout' => 60,
    'user_agent' => 'My Custom Bot 1.0',
    'verify_ssl' => true
]);

// Or use chainable methods
$scraper->setUserAgent('My Custom Bot 1.0')
        ->setTimeout(30)
        ->addHeader('Accept', 'application/json')
        ->addHeader('Authorization', 'Bearer token');

$html = $scraper->fetch('https://api.example.com');
```

#### Extract Text from HTML

```php
$html = $scraper->fetch('https://example.com');
$text = $scraper->extractText($html);
echo $text; // Plain text without HTML tags
```

#### Extract Links

```php
$html = $scraper->fetch('https://example.com');

// Extract all links
$links = $scraper->extractLinks($html);

// Extract links with relative URL resolution
$links = $scraper->extractLinks($html, 'https://example.com');
print_r($links);
```

#### Parse HTML with CSS Selectors

```php
$html = $scraper->fetch('https://example.com');

// Select by tag
$divs = $scraper->parseHtml($html, 'div');

// Select by class
$elements = $scraper->parseHtml($html, '.content');

// Select by ID
$element = $scraper->parseHtml($html, '#main');

// Select by tag and class
$articles = $scraper->parseHtml($html, 'article.post');

foreach ($articles as $article) {
    echo $article . PHP_EOL;
}
```

#### Complete Example

```php
use OmegaAlfa\Utils\WebScraper;

try {
    $scraper = new WebScraper();
    $scraper->setUserAgent('Mozilla/5.0')
            ->setTimeout(30);
    
    // Fetch the page
    $html = $scraper->fetch('https://example.com/blog');
    
    // Extract article titles
    $titles = $scraper->parseHtml($html, 'h2.title');
    
    // Extract all links
    $links = $scraper->extractLinks($html, 'https://example.com/blog');
    
    // Get plain text content
    $text = $scraper->extractText($html);
    
    echo "Found " . count($titles) . " articles\n";
    echo "Found " . count($links) . " links\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

## Configuration Options

When creating a WebScraper instance, you can pass the following options:

- `timeout` (int): Request timeout in seconds (default: 30)
- `user_agent` (string): User agent string (default: Chrome user agent)
- `follow_redirects` (bool): Whether to follow redirects (default: true)
- `max_redirects` (int): Maximum number of redirects to follow (default: 5)
- `verify_ssl` (bool): Whether to verify SSL certificates (default: true)

## Requirements

- PHP >= 7.4
- cURL extension (recommended) or allow_url_fopen enabled
- DOM extension for HTML parsing

## Testing

Run the test suite:

```bash
composer install
vendor/bin/phpunit
```

## License

MIT License

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Future Additions

This package is designed to be extensible. Future utilities may include:

- File manipulation utilities
- String processing helpers
- Data validation tools
- Cache management
- And more...
