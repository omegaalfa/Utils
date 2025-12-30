<?php

use Omegaalfa\Utils\WebScraper\WebScraperClient;

require "vendor/autoload.php";

echo "╔════════════════════════════════════════════════╗\n";
echo "║     WebScraper - Scraping Múltiplo            ║\n";
echo "╚════════════════════════════════════════════════╝\n\n";

// Nota: scrapeMultiple() tem bug com Promise::all()
// Usando abordagem sequencial que funciona


$scraper = WebScraperClient::create();

 $promise =  $scraper->scrapeMultiple([
    'github' => [
        'url' => 'https://github.com',
        'selectors' => [
            'title' => 'h1',
            'nav_links' => 'nav a@href'
        ]
    ],
    'stackoverflow' => [
        'url' => 'https://stackoverflow.com',
        'selectors' => [
            'title' => 'h1',
            'questions' => '.question-summary'
        ]
    ],
    'reddit' => [
        'url' => 'https://reddit.com',
        'selectors' => [
            'title' => 'h1',
            'posts' => 'article'
        ]
    ],
     'example' => [
         'url' => 'https://www.php.net/manual/pt_BR/class.dom-htmldocument.php',
         'selectors' => [
             'title' => 'h1',
             'p' => '#layout aside .parent-menu-list li a',
         ]
     ]
])->then(function($results) {
     var_dump($results['example']);
})->catch(function($error) {
    echo "Erro: " . $error->getMessage();
});

$promise->wait();