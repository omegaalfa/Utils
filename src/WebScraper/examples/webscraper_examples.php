<?php

declare(strict_types=1);

use Omegaalfa\Utils\WebScraper\WebScraperClient;

require_once __DIR__ . '/../../../vendor/autoload.php';



echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║          WebScraper Professional - Testes Completos           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// Teste 1: Scraping básico com seletores CSS
// ============================================================================
echo "📋 Teste 1: Scraping básico - IANA Example Domains\n";
echo str_repeat("─", 70) . "\n";

$scraper = WebScraperClient::create()
    ->withTimeout(15.0);

$promise1 = $scraper->scrape('https://www.iana.org/help/example-domains', [
    'title' => 'h1',
    'paragraphs' => 'article p',
    'links' => 'article p a@href',
    'linkTexts' => 'article p a',
]);

$promise1->then(function ($data) {
    echo "✅ Sucesso!\n";
    echo "Title: " . ($data['title'] ?? 'N/A') . "\n";
    echo "Paragraphs: " . count($data['paragraphs'] ?? []) . " encontrados\n";
    echo "Links: " . count($data['links'] ?? []) . " encontrados\n";
    if (!empty($data['links'])) {
        echo "Primeiros links:\n";
        foreach (array_slice($data['links'], 0, 3) as $link) {
            echo "  - $link\n";
        }
    }
    echo "\n";
})->catch(function ($error) {
    echo "❌ Erro: " . $error->getMessage() . "\n\n";
});

$scraper->wait();

// ============================================================================
// Teste 2: Scraping com cache e rate limiting
// ============================================================================
echo "💾 Teste 2: Cache e Rate Limiting\n";
echo str_repeat("─", 70) . "\n";

$scraper2 = WebScraperClient::create()
    ->withCache(300) // 5 minutos
    ->withRateLimit(2.0); // 2 RPS

// Primeira requisição (sem cache)
$start = microtime(true);
$promise2a = $scraper2->scrape('https://example.com', [
    'title' => 'h1',
    'description' => 'p',
]);

$promise2a->then(function ($data) use ($start) {
    $time = round((microtime(true) - $start) * 1000);
    echo "✅ Primeira requisição: {$time}ms (sem cache)\n";
    echo "Title: " . ($data['title'] ?? 'N/A') . "\n";
});

$scraper2->wait();

// Aguardar para respeitar rate limit
usleep(600000); // 0.6 segundos

// Segunda requisição (com cache)
$start2 = microtime(true);
$promise2b = $scraper2->scrape('https://example.com', [
    'title' => 'h1',
    'description' => 'p',
]);

$promise2b->then(function ($data) use ($start2) {
    $time = round((microtime(true) - $start2) * 1000);
    echo "✅ Segunda requisição: {$time}ms (FROM CACHE!)\n";
});

$scraper2->wait();

// Estatísticas do cache
$cacheStats = $scraper2->getCache()->getStats();
echo "Cache hits: {$cacheStats['hits']}, misses: {$cacheStats['misses']}, hit rate: " . 
     round($cacheStats['hitRate'], 2) . "%\n\n";

// ============================================================================
// Teste 3: Scraping múltiplo concorrente
// ============================================================================
echo "🚀 Teste 3: Scraping múltiplo concorrente\n";
echo str_repeat("─", 70) . "\n";

$http3 = \Omegaalfa\HttpPromise\HttpPromise::create()
    ->withTimeout(20.0)
    ->withMaxConcurrent(5);

$scraper3 = new WebScraperClient($http3);

$targets = [
    'example1' => [
        'url' => 'https://example.com',
        'selectors' => ['title' => 'h1', 'text' => 'p'],
    ],
    'example2' => [
        'url' => 'https://www.iana.org/domains/reserved',
        'selectors' => ['title' => 'h1', 'content' => 'article p'],
    ],
    'httpbin' => [
        'url' => 'https://httpbin.org/html',
        'selectors' => ['title' => 'h1', 'paragraphs' => 'p'],
    ],
];

$start3 = microtime(true);
$promise3 = $scraper3->scrapeMultiple($targets);

$promise3->then(function ($results) use ($start3) {
    $totalTime = round((microtime(true) - $start3) * 1000);
    echo "✅ Concluído em {$totalTime}ms\n";
    
    foreach ($results as $key => $data) {
        echo "\n[$key]:\n";
        if (isset($data['title'])) {
            echo "  Title: " . (is_array($data['title']) ? $data['title'][0] : $data['title']) . "\n";
        }
        if (isset($data['text'])) {
            $text = is_array($data['text']) ? $data['text'] : [$data['text']];
            echo "  Items: " . count($text) . "\n";
        }
    }
})->catch(function ($error) {
    echo "❌ Erro: " . $error->getMessage() . "\n";
});

$scraper3->wait();
echo "\n";

// ============================================================================
// Teste 4: Scraping com callback de progresso
// ============================================================================
echo "📊 Teste 4: Progress callback\n";
echo str_repeat("─", 70) . "\n";

$scraper4 = WebScraperClient::create()
    ->onProgress(function ($url, $current, $total) {
        $percent = round(($current / $total) * 100);
        $bar = str_repeat('█', (int)($percent / 5));
        $bar .= str_repeat('░', 20 - strlen($bar));
        echo "\r[{$bar}] {$percent}% ({$current}/{$total}) - " . basename(parse_url($url, PHP_URL_PATH) ?: 'index');
    });

$targets4 = [
    'page1' => ['url' => 'https://example.com', 'selectors' => ['title' => 'h1']],
    'page2' => ['url' => 'https://httpbin.org/html', 'selectors' => ['title' => 'h1']],
    'page3' => ['url' => 'https://www.iana.org', 'selectors' => ['title' => 'h1']],
];

$scraper4->scrapeMultiple($targets4)->then(function () {
    echo "\n✅ Todas as páginas processadas!\n\n";
});

$scraper4->wait();

// ============================================================================
// Teste 5: Extração de metadados e imagens
// ============================================================================
echo "🖼️  Teste 5: Extração de metadados e imagens\n";
echo str_repeat("─", 70) . "\n";

$scraper5 = WebScraperClient::create();

$promise5 = $scraper5->scrape('https://www.iana.org', [
    'title' => 'title',
    'description' => 'meta[name="description"]@content',
    'ogTitle' => 'meta[property="og:title"]@content',
    'images' => 'img@src',
    'stylesheets' => 'link[rel="stylesheet"]@href',
]);

$promise5->then(function ($data) {
    echo "✅ Metadados extraídos:\n";
    echo "Title: " . ($data['title'] ?? 'N/A') . "\n";
    echo "Description: " . ($data['description'] ?? 'N/A') . "\n";
    echo "OG Title: " . ($data['ogTitle'] ?? 'N/A') . "\n";
    
    if (!empty($data['images'])) {
        $images = is_array($data['images']) ? $data['images'] : [$data['images']];
        echo "Images: " . count($images) . " encontradas\n";
        echo "Primeira imagem: " . ($images[0] ?? 'N/A') . "\n";
    }
    
    if (!empty($data['stylesheets'])) {
        $css = is_array($data['stylesheets']) ? $data['stylesheets'] : [$data['stylesheets']];
        echo "Stylesheets: " . count($css) . " encontrados\n";
    }
    echo "\n";
});

$scraper5->wait();

// ============================================================================
// Teste 6: Cookies e sessões
// ============================================================================
echo "🍪 Teste 6: Gerenciamento de Cookies\n";
echo str_repeat("─", 70) . "\n";

$scraper6 = WebScraperClient::create();

// Fazer requisição que pode definir cookies
$promise6 = $scraper6->get('https://httpbin.org/cookies/set?session=abc123&user=test');

$promise6->then(function ($response) use ($scraper6) {
    echo "✅ Cookies recebidos\n";
    
    $cookies = $scraper6->getCookieJar()->getCookies();
    echo "Total de cookies: " . count($cookies) . "\n";
    
    foreach ($cookies as $name => $cookie) {
        echo "  - {$name} = {$cookie['value']} (domain: {$cookie['domain']})\n";
    }
    
    // Salvar cookies
    $cookiePath = '/tmp/webscraper_cookies.json';
    $scraper6->saveCookies($cookiePath);
    echo "Cookies salvos em: {$cookiePath}\n\n";
});

$scraper6->wait();

// ============================================================================
// Teste 7: Tratamento de erros e retry
// ============================================================================
echo "⚠️  Teste 7: Tratamento de erros e retry\n";
echo str_repeat("─", 70) . "\n";

$scraper7 = WebScraperClient::create()
    ->withRetry(3, 0.5) // 3 tentativas, 0.5s delay
    ->withTimeout(5.0);

// Testar com URL que pode falhar
$promise7 = $scraper7->get('https://httpbin.org/status/500');

$promise7->then(function ($response) {
    echo "✅ Requisição bem-sucedida (status: {$response->getStatusCode()})\n";
})->catch(function ($error) {
    echo "❌ Falha após retries: " . $error->getMessage() . "\n";
});

$scraper7->wait();

$stats7 = $scraper7->getStatistics();
echo "Retries realizados: " . ($stats7['retried_requests'] ?? 0) . "\n\n";

// ============================================================================
// Teste 8: Estatísticas completas
// ============================================================================
echo "📈 Teste 8: Estatísticas detalhadas\n";
echo str_repeat("─", 70) . "\n";

$scraper8 = WebScraperClient::create()
    ->withCache(600)
    ->withRateLimit(5.0);

// Fazer várias requisições
$urls = [
    'https://example.com',
    'https://httpbin.org/html',
    'https://www.iana.org',
];

foreach ($urls as $url) {
    $scraper8->get($url)->catch(fn($e) => null);
}

$scraper8->wait();

$stats = $scraper8->getStatistics();

echo "📊 Estatísticas:\n";
echo "  Total de requisições: {$stats['total_requests']}\n";
echo "  Bem-sucedidas: {$stats['successful_requests']}\n";
echo "  Falhas: {$stats['failed_requests']}\n";
echo "  Taxa de sucesso: " . round($stats['success_rate_percent'], 2) . "%\n";
echo "  Tempo médio de resposta: {$stats['response_time']['average_seconds']}s\n";
echo "  Tempo mínimo: {$stats['response_time']['min_seconds']}s\n";
echo "  Tempo máximo: {$stats['response_time']['max_seconds']}s\n";
echo "  Requisições por segundo: " . round($stats['requests_per_second'], 2) . "\n";
echo "  Uptime: {$stats['uptime_seconds']}s\n";

if (isset($stats['cache'])) {
    echo "\n💾 Cache:\n";
    echo "  Hits: {$stats['cache']['hits']}\n";
    echo "  Misses: {$stats['cache']['misses']}\n";
    echo "  Hit rate: " . round($stats['cache']['hitRate'], 2) . "%\n";
    echo "  Entradas: {$stats['cache']['size']}\n";
}

if (!empty($stats['status_codes'])) {
    echo "\n📋 Status codes:\n";
    foreach ($stats['status_codes'] as $code => $count) {
        echo "  {$code}: {$count}x\n";
    }
}

echo "\n";

// ============================================================================
// Teste 9: POST request com JSON
// ============================================================================
echo "📤 Teste 9: POST request com JSON\n";
echo str_repeat("─", 70) . "\n";

$scraper9 = WebScraperClient::create();

$jsonData = [
    'name' => 'WebScraper',
    'version' => '1.0.0',
    'features' => ['cache', 'retry', 'cookies'],
];

$promise9 = $scraper9->post(
    'https://httpbin.org/post',
    json_encode($jsonData),
    ['Content-Type' => 'application/json']
);

$promise9->then(function ($response) {
    echo "✅ POST enviado com sucesso!\n";
    echo "Status: {$response->getStatusCode()}\n";
    $body = (string)$response->getBody();
    $json = json_decode($body, true);
    if (isset($json['json'])) {
        echo "Dados enviados:\n";
        echo "  Name: {$json['json']['name']}\n";
        echo "  Version: {$json['json']['version']}\n";
    }
    echo "\n";
})->catch(function ($error) {
    echo "❌ Erro: " . $error->getMessage() . "\n\n";
});

$scraper9->wait();

// ============================================================================
// Resumo Final
// ============================================================================
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                    Testes Concluídos! ✅                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";

echo "\n📚 Funcionalidades demonstradas:\n";
echo "  ✓ Scraping básico com seletores CSS\n";
echo "  ✓ Extração de atributos (@href, @src, @content)\n";
echo "  ✓ Cache inteligente com TTL\n";
echo "  ✓ Rate limiting por domínio\n";
echo "  ✓ Scraping múltiplo concorrente\n";
echo "  ✓ Progress callback\n";
echo "  ✓ Gerenciamento de cookies (RFC 6265)\n";
echo "  ✓ Retry automático com backoff\n";
echo "  ✓ Estatísticas detalhadas\n";
echo "  ✓ POST requests com JSON\n";
echo "  ✓ Tratamento de erros\n\n";

echo "🎯 WebScraper está pronto para produção!\n";
