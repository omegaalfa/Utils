<?php

declare(strict_types=1);

use Omegaalfa\Utils\WebScraper\WebScraperClient;

require_once __DIR__ . '/../../../vendor/autoload.php';



echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║     WebScraper - Casos de Uso Reais e Testes Avançados        ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// ============================================================================
// Caso de Uso 1: Monitoramento de preços
// ============================================================================
echo "💰 Caso de Uso 1: Monitoramento de Preços\n";
echo str_repeat("─", 70) . "\n";

class PriceMonitor
{
    private WebScraperClient $scraper;
    private array $history = [];

    public function __construct()
    {
        $this->scraper = WebScraperClient::create()
            ->withCache(60) // 1 minuto de cache
            ->withRateLimit(2.0)
            ->withRetry(3, 1.0)
            ->withFingerprintRotation(true);
    }

    public function monitorPrice(string $url, string $priceSelector): void
    {
        $promise = $this->scraper->scrape($url, [
            'title' => 'title',
            'price' => $priceSelector,
            'availability' => '.availability, .stock',
        ]);

        $promise->then(function ($data) use ($url) {
            $price = $this->extractNumericPrice($data['price'] ?? 'N/A');
            
            echo "📊 Produto monitorado:\n";
            echo "  URL: " . parse_url($url, PHP_URL_HOST) . "\n";
            echo "  Preço: R$ " . number_format($price, 2, ',', '.') . "\n";
            
            $this->history[] = [
                'timestamp' => time(),
                'price' => $price,
                'url' => $url,
            ];
            
            $this->checkPriceAlert($price);
        });

        $this->scraper->wait();
    }

    private function extractNumericPrice(string $price): float
    {
        // Remove tudo exceto números e pontos/vírgulas
        $cleaned = preg_replace('/[^\d,.]/', '', $price);
        $cleaned = str_replace(',', '.', $cleaned);
        return (float)$cleaned;
    }

    private function checkPriceAlert(float $price): void
    {
        if (count($this->history) > 1) {
            $previous = $this->history[count($this->history) - 2]['price'];
            $change = (($price - $previous) / $previous) * 100;
            
            if (abs($change) > 5) {
                $emoji = $change > 0 ? '📈' : '📉';
                echo "  {$emoji} Alerta: Mudança de " . round($change, 2) . "%\n";
            }
        }
        echo "\n";
    }

    public function getStatistics(): array
    {
        return $this->scraper->getStatistics();
    }
}

$monitor = new PriceMonitor();
echo "Monitorando exemplo de produto...\n";
$monitor->monitorPrice('https://example.com', '.price, .valor');

// ============================================================================
// Caso de Uso 2: Agregador de notícias
// ============================================================================
echo "📰 Caso de Uso 2: Agregador de Notícias\n";
echo str_repeat("─", 70) . "\n";

class NewsAggregator
{
    private WebScraperClient $scraper;
    private array $articles = [];

    public function __construct()
    {
        $http = \Omegaalfa\HttpPromise\HttpPromise::create()
            ->withTimeout(15.0)
            ->withMaxConcurrent(10);
        
        $this->scraper = new WebScraperClient($http);
        $this->scraper->withCache(300) // 5 minutos
            ->withRateLimit(5.0)
            ->onProgress(function ($url, $current, $total) {
                echo "\r🔄 Processando: {$current}/{$total} fontes...";
            });
    }

    public function fetchFromSources(array $sources): array
    {
        $targets = [];
        
        foreach ($sources as $name => $config) {
            $targets[$name] = [
                'url' => $config['url'],
                'selectors' => [
                    'headlines' => $config['headline_selector'],
                    'links' => $config['link_selector'],
                    'descriptions' => $config['description_selector'] ?? 'p',
                ],
            ];
        }

        $promise = $this->scraper->scrapeMultiple($targets);
        
        $promise->then(function ($results) {
            echo "\n✅ Agregação concluída!\n\n";
            
            foreach ($results as $source => $data) {
                $headlines = is_array($data['headlines']) ? $data['headlines'] : [$data['headlines']];
                $links = is_array($data['links']) ? $data['links'] : [$data['links']];
                
                echo "📌 {$source}:\n";
                
                for ($i = 0; $i < min(3, count($headlines)); $i++) {
                    if (isset($headlines[$i])) {
                        $link = $links[$i] ?? '#';
                        echo "  • " . substr($headlines[$i], 0, 60) . "...\n";
                        echo "    → {$link}\n";
                    }
                }
                echo "\n";
            }
        });

        $this->scraper->wait();
        
        return $this->scraper->getStatistics();
    }
}

$aggregator = new NewsAggregator();

$newsSources = [
    'Example' => [
        'url' => 'https://example.com',
        'headline_selector' => 'h1, h2',
        'link_selector' => 'a@href',
    ],
    'IANA' => [
        'url' => 'https://www.iana.org',
        'headline_selector' => 'h1',
        'link_selector' => 'nav a@href',
    ],
];

$stats = $aggregator->fetchFromSources($newsSources);
echo "Tempo total: {$stats['uptime_seconds']}s\n";
echo "Taxa de sucesso: " . round($stats['success_rate_percent'], 2) . "%\n\n";

// ============================================================================
// Caso de Uso 3: Validador de SEO
// ============================================================================
echo "🔍 Caso de Uso 3: Validador de SEO\n";
echo str_repeat("─", 70) . "\n";

class SEOValidator
{
    private WebScraperClient $scraper;

    public function __construct()
    {
        $this->scraper = WebScraperClient::create()
            ->withTimeout(20.0);
    }

    public function validate(string $url): array
    {
        $selectors = [
            'title' => 'title',
            'metaDescription' => 'meta[name="description"]@content',
            'metaKeywords' => 'meta[name="keywords"]@content',
            'h1' => 'h1',
            'h2' => 'h2',
            'h3' => 'h3',
            'images' => 'img@src',
            'imagesAlt' => 'img@alt',
            'links' => 'a@href',
            'canonicalUrl' => 'link[rel="canonical"]@href',
            'ogTitle' => 'meta[property="og:title"]@content',
            'ogDescription' => 'meta[property="og:description"]@content',
            'ogImage' => 'meta[property="og:image"]@content',
        ];

        $report = [
            'url' => $url,
            'score' => 0,
            'issues' => [],
            'recommendations' => [],
        ];

        $promise = $this->scraper->scrape($url, $selectors);

        $promise->then(function ($data) use (&$report) {
            echo "🔍 Analisando SEO...\n\n";

            // Title
            $title = is_array($data['title']) ? ($data['title'][0] ?? '') : ($data['title'] ?? '');
            if (empty($title)) {
                $report['issues'][] = "❌ Tag <title> ausente";
            } elseif (strlen($title) < 30) {
                $report['issues'][] = "⚠️  Title muito curto (< 30 caracteres)";
            } elseif (strlen($title) > 60) {
                $report['issues'][] = "⚠️  Title muito longo (> 60 caracteres)";
            } else {
                $report['score'] += 15;
                echo "✅ Title: OK ({$title})\n";
            }

            // Meta Description
            if (empty($data['metaDescription'])) {
                $report['issues'][] = "❌ Meta description ausente";
            } else {
                $desc = is_array($data['metaDescription']) ? $data['metaDescription'][0] : $data['metaDescription'];
                if (strlen($desc) < 120 || strlen($desc) > 160) {
                    $report['issues'][] = "⚠️  Meta description fora do ideal (120-160 chars)";
                } else {
                    $report['score'] += 15;
                    echo "✅ Meta Description: OK\n";
                }
            }

            // H1
            $h1Count = is_array($data['h1']) ? count($data['h1']) : (empty($data['h1']) ? 0 : 1);
            if ($h1Count === 0) {
                $report['issues'][] = "❌ Nenhuma tag <h1> encontrada";
            } elseif ($h1Count > 1) {
                $report['issues'][] = "⚠️  Múltiplas tags <h1> ({$h1Count})";
            } else {
                $report['score'] += 10;
                echo "✅ H1: OK\n";
            }

            // Hierarchy (H2, H3)
            $h2Count = is_array($data['h2']) ? count($data['h2']) : (empty($data['h2']) ? 0 : 1);
            $h3Count = is_array($data['h3']) ? count($data['h3']) : (empty($data['h3']) ? 0 : 1);
            
            if ($h2Count > 0) {
                $report['score'] += 10;
                echo "✅ Hierarquia de headings: OK (H2: {$h2Count}, H3: {$h3Count})\n";
            }

            // Images
            $images = is_array($data['images']) ? $data['images'] : (empty($data['images']) ? [] : [$data['images']]);
            $alts = is_array($data['imagesAlt']) ? $data['imagesAlt'] : (empty($data['imagesAlt']) ? [] : [$data['imagesAlt']]);
            
            if (count($images) > 0) {
                $missingAlts = 0;
                foreach ($alts as $alt) {
                    if (empty($alt)) {
                        $missingAlts++;
                    }
                }
                
                if ($missingAlts > 0) {
                    $report['issues'][] = "⚠️  {$missingAlts} imagens sem atributo alt";
                } else {
                    $report['score'] += 10;
                    echo "✅ Imagens com alt: OK\n";
                }
            }

            // Open Graph
            if (!empty($data['ogTitle']) && !empty($data['ogDescription'])) {
                $report['score'] += 10;
                echo "✅ Open Graph: OK\n";
            } else {
                $report['issues'][] = "⚠️  Open Graph incompleto";
            }

            // Canonical URL
            if (!empty($data['canonicalUrl'])) {
                $report['score'] += 5;
                echo "✅ Canonical URL: OK\n";
            }

            // Links
            $links = is_array($data['links']) ? $data['links'] : (empty($data['links']) ? [] : [$data['links']]);
            $internalLinks = 0;
            $externalLinks = 0;
            
            foreach ($links as $link) {
                if (str_starts_with($link, 'http')) {
                    $externalLinks++;
                } else {
                    $internalLinks++;
                }
            }

            echo "\n📊 Resumo:\n";
            echo "  Links internos: {$internalLinks}\n";
            echo "  Links externos: {$externalLinks}\n";
            echo "  Imagens: " . count($images) . "\n";

            // Score final
            $report['score'] = min(100, $report['score']);
            
            echo "\n🎯 Score SEO: {$report['score']}/100\n";
            
            if ($report['score'] >= 80) {
                echo "  ⭐ Excelente!\n";
            } elseif ($report['score'] >= 60) {
                echo "  ✅ Bom\n";
            } elseif ($report['score'] >= 40) {
                echo "  ⚠️  Precisa melhorias\n";
            } else {
                echo "  ❌ Crítico\n";
            }

            if (!empty($report['issues'])) {
                echo "\n⚠️  Problemas encontrados:\n";
                foreach ($report['issues'] as $issue) {
                    echo "  {$issue}\n";
                }
            }

            echo "\n";
        });

        $this->scraper->wait();

        return $report;
    }
}

$seoValidator = new SEOValidator();
$seoValidator->validate('https://www.iana.org');

// ============================================================================
// Caso de Uso 4: Extração de dados estruturados
// ============================================================================
echo "📊 Caso de Uso 4: Extração de Dados Estruturados\n";
echo str_repeat("─", 70) . "\n";

$scraper4 = WebScraperClient::create()
    ->withCache(600);

// Extrair dados estruturados de uma página
$promise4 = $scraper4->scrape('https://httpbin.org/html', [
    'allLinks' => 'a@href',
    'allText' => 'p',
    'listItems' => 'li',
    'externalScripts' => 'script@src',
]);

$promise4->then(function ($data) {
    echo "✅ Dados estruturados extraídos:\n\n";
    
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            echo "{$key}: " . count($value) . " itens\n";
            if (count($value) > 0) {
                echo "  Exemplo: " . substr($value[0], 0, 50) . "...\n";
            }
        } else {
            echo "{$key}: {$value}\n";
        }
    }
    echo "\n";
});

$scraper4->wait();

// ============================================================================
// Caso de Uso 5: Health Check de múltiplos sites
// ============================================================================
echo "🏥 Caso de Uso 5: Health Check de Sites\n";
echo str_repeat("─", 70) . "\n";

$scraper5 = WebScraperClient::create()
    ->withTimeout(10.0)
    ->withRetry(2, 0.5);

$sitesToCheck = [
    'https://example.com',
    'https://www.iana.org',
    'https://httpbin.org/html',
];

echo "Verificando status de " . count($sitesToCheck) . " sites...\n\n";

foreach ($sitesToCheck as $url) {
    $start = microtime(true);
    
    $scraper5->get($url)->then(
        function ($response) use ($url, $start) {
            $time = round((microtime(true) - $start) * 1000);
            $status = $response->getStatusCode();
            $emoji = $status >= 200 && $status < 300 ? '✅' : '⚠️';
            
            echo "{$emoji} {$url}\n";
            echo "   Status: {$status} | Tempo: {$time}ms\n";
        },
        function ($error) use ($url) {
            echo "❌ {$url}\n";
            echo "   Erro: " . $error->getMessage() . "\n";
        }
    );
}

$scraper5->wait();

$stats5 = $scraper5->getStatistics();
echo "\n📈 Resultado:\n";
echo "  Sucessos: {$stats5['successful_requests']}/{$stats5['total_requests']}\n";
echo "  Falhas: {$stats5['failed_requests']}\n";
echo "  Taxa de disponibilidade: " . round($stats5['success_rate_percent'], 2) . "%\n\n";

// ============================================================================
// Resumo Final
// ============================================================================
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║              Casos de Uso Demonstrados! ✅                     ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

echo "💡 Casos de uso implementados:\n";
echo "  1. 💰 Monitoramento de Preços com alertas\n";
echo "  2. 📰 Agregador de Notícias multi-fonte\n";
echo "  3. 🔍 Validador de SEO completo\n";
echo "  4. 📊 Extração de Dados Estruturados\n";
echo "  5. 🏥 Health Check de múltiplos sites\n\n";

echo "🚀 WebScraper pronto para casos de uso reais em produção!\n";
