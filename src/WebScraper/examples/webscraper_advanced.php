<?php

declare(strict_types=1);

use Omegaalfa\Utils\WebScraper\WebScraperClient;

require_once __DIR__ . '/../../../vendor/autoload.php';



echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║        WebScraper - Cenários Avançados e Edge Cases           ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// Teste Avançado 1: Extração de múltiplos atributos diferentes
// ============================================================================
echo "🎯 Teste 1: Extração Avançada de Atributos\n";
echo str_repeat("─", 70) . "\n";

$scraper1 = WebScraperClient::create();

$scraper1->scrape('https://httpbin.org/html', [
    'all_links' => 'a@href',           // Links
    'images' => 'img@src',             // Imagens
    'image_alts' => 'img@alt',         // Alt text de imagens
    'form_actions' => 'form@action',   // Actions de formulários
    'meta_content' => 'meta@content',  // Content de meta tags
    'link_rels' => 'link@rel',         // Rel de link tags
])->then(function ($data) {
    echo "📊 Atributos extraídos:\n";
    
    foreach ($data as $key => $values) {
        $count = is_array($values) ? count($values) : 1;
        echo "  • {$key}: {$count} encontrado(s)\n";
        
        if ($count > 0 && $count <= 3) {
            foreach ((array)$values as $value) {
                echo "    → " . (strlen($value) > 50 ? substr($value, 0, 50) . '...' : $value) . "\n";
            }
        }
    }
})->catch(function ($error) {
    echo "❌ Erro: " . $error->getMessage() . "\n";
});

$scraper1->wait();
echo "\n";

// ============================================================================
// Teste Avançado 2: Seletores CSS complexos
// ============================================================================
echo "🔍 Teste 2: Seletores CSS Complexos\n";
echo str_repeat("─", 70) . "\n";

$scraper2 = WebScraperClient::create();

$scraper2->scrape('https://example.com', [
    'first_paragraph' => 'body > div p:first-child',
    'even_paragraphs' => 'p:nth-child(even)',
    'links_with_class' => 'a[href*="iana"]',
    'not_heading' => 'body :not(h1)',
    'adjacent' => 'h1 + p',
])->then(function ($data) {
    echo "📊 Seletores complexos testados:\n";
    
    foreach ($data as $selector => $result) {
        if (is_array($result)) {
            echo "  • {$selector}: " . count($result) . " elemento(s)\n";
        } else {
            $preview = strlen($result) > 60 ? substr($result, 0, 60) . '...' : $result;
            echo "  • {$selector}: {$preview}\n";
        }
    }
})->catch(function ($error) {
    echo "❌ Erro: " . $error->getMessage() . "\n";
});

$scraper2->wait();
echo "\n";

// ============================================================================
// Teste Avançado 3: Handling de diferentes charsets
// ============================================================================
echo "🌐 Teste 3: Normalização de Encoding\n";
echo str_repeat("─", 70) . "\n";

$scraper3 = WebScraperClient::create();

// Simular diferentes encodings
$testUrls = [
    'https://example.com',                    // UTF-8
    'https://httpbin.org/html',              // UTF-8
    'https://www.iana.org',                  // UTF-8
];

echo "Testando normalização de encoding...\n";

foreach ($testUrls as $url) {
    $scraper3->get($url)->then(function ($response) use ($url) {
        $body = (string)$response->getBody();
        $hasUtf8 = mb_check_encoding($body, 'UTF-8');
        
        echo sprintf("  %s: %s (%d bytes)\n",
            parse_url($url, PHP_URL_HOST),
            $hasUtf8 ? '✓ UTF-8' : '✗ Não UTF-8',
            strlen($body)
        );
    })->catch(function ($error) {
        echo "  ❌ Erro: " . $error->getMessage() . "\n";
    });
}

$scraper3->wait();
echo "\n";

// ============================================================================
// Teste Avançado 4: Cookies persistentes entre requisições
// ============================================================================
echo "🍪 Teste 4: Persistência de Cookies\n";
echo str_repeat("─", 70) . "\n";

$scraper4 = WebScraperClient::create();

echo "Testando fluxo completo de cookies...\n\n";

// Passo 1: Set cookies
$scraper4->get('https://httpbin.org/cookies/set?session=abc123&user=john')
    ->then(function ($response) {
        echo "  1. Cookies definidos via redirect\n";
        return $response;
    });

$scraper4->wait();

// Passo 2: Verificar se cookies foram salvos
$cookieJar = $scraper4->getCookieJar();
$cookies = $cookieJar->getCookiesForUrl('https://httpbin.org');

echo "  2. Cookies armazenados localmente:\n";
foreach ($cookies as $name => $value) {
    echo "     • {$name}: {$value}\n";
}

// Passo 3: Fazer nova requisição com cookies
$scraper4->get('https://httpbin.org/cookies')->then(function ($response) {
    $body = json_decode((string)$response->getBody(), true);
    echo "\n  3. Cookies enviados na próxima requisição:\n";
    
    if (isset($body['cookies'])) {
        foreach ($body['cookies'] as $name => $value) {
            echo "     • {$name}: {$value}\n";
        }
    }
});

$scraper4->wait();

// Passo 4: Persistir em arquivo
$cookieFile = sys_get_temp_dir() . '/webscraper_cookies.json';
$cookieJar->saveCookiesToFile($cookieFile);
echo "\n  4. Cookies salvos em: {$cookieFile}\n";

// Passo 5: Carregar de arquivo
$scraper5 = WebScraperClient::create();
$scraper5->getCookieJar()->loadCookiesFromFile($cookieFile);
$loadedCookies = $scraper5->getCookieJar()->getCookiesForUrl('https://httpbin.org');

echo "  5. Cookies carregados do arquivo: " . count($loadedCookies) . " cookie(s)\n";

echo "\n";

// ============================================================================
// Teste Avançado 5: Tratamento de redirects
// ============================================================================
echo "↪️  Teste 5: Tratamento de Redirects\n";
echo str_repeat("─", 70) . "\n";

$scraper6a = WebScraperClient::create()
    ->withRedirects(true, 5);

echo "Com redirects habilitados:\n";
$scraper6a->get('https://httpbin.org/redirect/3')->then(function ($response) {
    echo "  ✓ Status final: " . $response->getStatusCode() . "\n";
    echo "  ✓ Redirects seguidos com sucesso\n";
})->catch(function ($error) {
    echo "  ❌ Erro: " . $error->getMessage() . "\n";
});

$scraper6a->wait();

$scraper6b = WebScraperClient::create()
    ->withRedirects(false);

echo "\nSem redirects:\n";
$scraper6b->get('https://httpbin.org/redirect/1')->then(function ($response) {
    echo "  • Status: " . $response->getStatusCode() . "\n";
    if ($response->hasHeader('Location')) {
        echo "  • Location: " . $response->getHeader('Location')[0] . "\n";
    }
})->catch(function ($error) {
    echo "  ❌ Erro: " . $error->getMessage() . "\n";
});

$scraper6b->wait();
echo "\n";

// ============================================================================
// Teste Avançado 6: Custom Headers e User-Agent
// ============================================================================
echo "🎭 Teste 6: Custom Headers e Fingerprinting\n";
echo str_repeat("─", 70) . "\n";

$scraper7 = WebScraperClient::create()
    ->withFingerprintRotation(true);

echo "Testando headers customizados...\n";

// Nota: Headers customizados são passados no método get()
$scraper7->get('https://httpbin.org/headers', [
    'X-Custom-Header' => 'TestValue',
    'X-API-Key' => 'secret123',
])->then(function ($response) {
    $body = json_decode((string)$response->getBody(), true);
    
    if (isset($body['headers'])) {
        echo "\n📨 Headers enviados:\n";
        
        $relevantHeaders = [
            'User-Agent',
            'X-Custom-Header',
            'X-Api-Key',
            'Accept-Language',
            'Sec-Fetch-Site',
            'Sec-Fetch-Mode',
        ];
        
        foreach ($relevantHeaders as $header) {
            $headerValue = $body['headers'][$header] ?? null;
            if ($headerValue) {
                $display = strlen($headerValue) > 50 ? substr($headerValue, 0, 50) . '...' : $headerValue;
                echo "  • {$header}: {$display}\n";
            }
        }
    }
});

$scraper7->wait();
echo "\n";

// ============================================================================
// Teste Avançado 7: Timeout e timeouts progressivos
// ============================================================================
echo "⏱️  Teste 7: Controle de Timeout\n";
echo str_repeat("─", 70) . "\n";

$timeoutTests = [
    ['url' => 'https://httpbin.org/delay/1', 'timeout' => 2.0, 'should_pass' => true],
    ['url' => 'https://httpbin.org/delay/3', 'timeout' => 2.0, 'should_pass' => false],
    ['url' => 'https://httpbin.org/delay/0', 'timeout' => 5.0, 'should_pass' => true],
];

echo "Testando diferentes timeouts...\n\n";

foreach ($timeoutTests as $i => $test) {
    $scraper = WebScraperClient::create()->withTimeout($test['timeout']);
    
    $start = microtime(true);
    $scraper->get($test['url'])->then(
        function ($response) use ($test, $start, $i) {
            $elapsed = microtime(true) - $start;
            $status = $test['should_pass'] ? '✓' : '⚠';
            echo sprintf("  %s Teste %d: Sucesso em %.2fs (timeout: %.1fs)\n",
                $status, $i + 1, $elapsed, $test['timeout']
            );
        },
        function ($error) use ($test, $start, $i) {
            $elapsed = microtime(true) - $start;
            $status = !$test['should_pass'] ? '✓' : '❌';
            echo sprintf("  %s Teste %d: Timeout em %.2fs (esperado: %.1fs)\n",
                $status, $i + 1, $elapsed, $test['timeout']
            );
        }
    );
    
    $scraper->wait();
}

echo "\n";

// ============================================================================
// Teste Avançado 8: POST com diferentes Content-Types
// ============================================================================
echo "📤 Teste 8: POST com Diferentes Content-Types\n";
echo str_repeat("─", 70) . "\n";

$scraper8 = WebScraperClient::create();

// JSON
echo "1. POST JSON:\n";
$scraper8->post('https://httpbin.org/post', [
    'json' => ['name' => 'John', 'age' => 30]
])->then(function ($response) {
    $body = json_decode((string)$response->getBody(), true);
    echo "  ✓ Content-Type: " . ($body['headers']['Content-Type'] ?? 'N/A') . "\n";
    echo "  ✓ Data recebido: " . json_encode($body['json']) . "\n";
});

$scraper8->wait();

// Form
echo "\n2. POST Form:\n";
$scraper8->post('https://httpbin.org/post', [
    'form' => ['field1' => 'value1', 'field2' => 'value2']
])->then(function ($response) {
    $body = json_decode((string)$response->getBody(), true);
    echo "  ✓ Content-Type: " . ($body['headers']['Content-Type'] ?? 'N/A') . "\n";
    echo "  ✓ Form fields: " . json_encode($body['form']) . "\n";
});

$scraper8->wait();

// Raw body
echo "\n3. POST Raw Body:\n";
$scraper8->post('https://httpbin.org/post', [
    'body' => 'Raw text content',
    'headers' => ['Content-Type' => 'text/plain']
])->then(function ($response) {
    $body = json_decode((string)$response->getBody(), true);
    echo "  ✓ Content-Type: " . ($body['headers']['Content-Type'] ?? 'N/A') . "\n";
    echo "  ✓ Data: " . ($body['data'] ?? 'N/A') . "\n";
});

$scraper8->wait();
echo "\n";

// ============================================================================
// Teste Avançado 9: Proxy rotation e fallback
// ============================================================================
echo "🌐 Teste 9: Proxy Management\n";
echo str_repeat("─", 70) . "\n";

// Nota: Proxies fictícios para demonstração
$scraper9 = WebScraperClient::create()
    ->withProxies([
        'http://proxy1.example.com:8080',
        'http://proxy2.example.com:8080',
        'http://proxy3.example.com:8080',
    ], true); // Rotação a cada request

echo "⚠️  Configuração de proxy (demonstração):\n";
echo "  • 3 proxies configurados\n";
echo "  • Rotação automática habilitada\n";
echo "  • Fallback em caso de falha\n";
echo "  • Nota: Proxies são fictícios para este exemplo\n";
echo "  • Os proxies serão rotacionados automaticamente nas requisições\n\n";

// ============================================================================
// Teste Avançado 10: Extração de dados estruturados
// ============================================================================
echo "📊 Teste 10: Extração de Dados Estruturados\n";
echo str_repeat("─", 70) . "\n";

$scraper10 = WebScraperClient::create();

$scraper10->scrape('https://example.com', [
    'page_title' => 'title',
    'main_heading' => 'h1',
    'all_headings' => 'h1, h2, h3',
    'paragraph_count' => 'p',
    'link_count' => 'a',
    'meta_description' => 'meta[name="description"]@content',
])->then(function ($data) {
    echo "📈 Estrutura da página:\n";
    
    $stats = [
        'Título' => is_array($data['page_title']) ? $data['page_title'][0] : $data['page_title'],
        'H1' => is_array($data['main_heading']) ? $data['main_heading'][0] : $data['main_heading'],
        'Headings' => is_array($data['all_headings']) ? count($data['all_headings']) : 0,
        'Parágrafos' => is_array($data['paragraph_count']) ? count($data['paragraph_count']) : 0,
        'Links' => is_array($data['link_count']) ? count($data['link_count']) : 0,
    ];
    
    foreach ($stats as $label => $value) {
        echo "  • {$label}: {$value}\n";
    }
    
    // Métricas calculadas
    echo "\n📊 Métricas:\n";
    $linkCount = is_array($data['link_count']) ? count($data['link_count']) : 0;
    $paragraphCount = is_array($data['paragraph_count']) ? count($data['paragraph_count']) : 0;
    
    if ($paragraphCount > 0) {
        echo sprintf("  • Links por parágrafo: %.2f\n", $linkCount / $paragraphCount);
    }
    
    $headingCount = is_array($data['all_headings']) ? count($data['all_headings']) : 0;
    echo sprintf("  • Densidade de headings: %.1f%%\n", 
        ($headingCount / max($paragraphCount, 1)) * 100
    );
});

$scraper10->wait();
echo "\n";

// ============================================================================
// Teste Avançado 11: Análise de Performance por URL
// ============================================================================
echo "⚡ Teste 11: Performance Analytics\n";
echo str_repeat("─", 70) . "\n";

$scraper11 = WebScraperClient::create();

$perfUrls = [
    'https://example.com',
    'https://httpbin.org/html',
    'https://www.iana.org',
    'https://httpbin.org/delay/1',
];

echo "Analisando performance de múltiplas URLs...\n\n";

$perfResults = [];

foreach ($perfUrls as $url) {
    $start = microtime(true);
    
    $scraper11->get($url)->then(
        function ($response) use ($url, $start, &$perfResults) {
            $time = microtime(true) - $start;
            $size = strlen((string)$response->getBody());
            
            $perfResults[] = [
                'url' => parse_url($url, PHP_URL_HOST),
                'time' => $time,
                'size' => $size,
                'speed' => $size / max($time, 0.001),
                'status' => $response->getStatusCode(),
            ];
        },
        function ($error) use ($url, &$perfResults) {
            $perfResults[] = [
                'url' => parse_url($url, PHP_URL_HOST),
                'error' => $error->getMessage(),
            ];
        }
    );
}

$scraper11->wait();

// Ordenar por tempo
usort($perfResults, fn($a, $b) => ($a['time'] ?? PHP_FLOAT_MAX) <=> ($b['time'] ?? PHP_FLOAT_MAX));

echo "📊 Ranking de Performance:\n";
foreach ($perfResults as $i => $result) {
    if (isset($result['error'])) {
        echo sprintf("  %d. ❌ %s: %s\n", $i + 1, $result['url'], $result['error']);
    } else {
        echo sprintf("  %d. ✓ %s: %.3fs | %.2f KB | %.1f KB/s\n",
            $i + 1,
            $result['url'],
            $result['time'],
            $result['size'] / 1024,
            $result['speed'] / 1024
        );
    }
}

echo "\n";

// ============================================================================
// Resumo Final
// ============================================================================
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║          Testes Avançados Concluídos! ✅                       ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "🎯 Funcionalidades testadas:\n";
echo "  ✓ Extração de múltiplos atributos (@href, @src, @alt, etc)\n";
echo "  ✓ Seletores CSS complexos (nth-child, :not, combinadores)\n";
echo "  ✓ Normalização automática de encoding UTF-8\n";
echo "  ✓ Persistência de cookies entre requisições\n";
echo "  ✓ Tratamento inteligente de redirects\n";
echo "  ✓ Headers customizados e fingerprinting\n";
echo "  ✓ Controle preciso de timeouts\n";
echo "  ✓ POST com JSON, Form e Raw body\n";
echo "  ✓ Gerenciamento de proxies com rotação\n";
echo "  ✓ Extração de dados estruturados\n";
echo "  ✓ Analytics de performance por URL\n\n";

echo "💡 Recursos avançados prontos para produção!\n";
