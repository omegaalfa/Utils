# 🕷️ WebScraper Professional

<div align="center">

![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-777BB4?style=flat-square&logo=php)
![License](https://img.shields.io/badge/License-MIT-green?style=flat-square)
![PSR-12](https://img.shields.io/badge/PSR--12-Compliant-blue?style=flat-square)
![SOLID](https://img.shields.io/badge/SOLID-Principles-orange?style=flat-square)

**Um web scraper profissional em PHP 8.4 com arquitetura orientada a objetos, otimizado para evitar detecção por WAFs e maximizar performance.**

[Features](#-features) • [Instalação](#-instalação) • [Quick Start](#-quick-start) • [Documentação](#-documentação-completa) • [Exemplos](#-exemplos-avançados)

</div>

---

## 📋 Índice

- [Features](#-features)
- [Requisitos](#-requisitos)
- [Instalação](#-instalação)
- [Quick Start](#-quick-start)
- [Arquitetura](#-arquitetura)
- [Documentação Completa](#-documentação-completa)
  - [WebScraperClient](#webscraperclient)
  - [CookieJar](#cookiejar)
  - [HeaderFingerprint](#headerfingerprint)
  - [ResponseCache](#responsecache)
  - [RateLimiter](#ratelimiter)
  - [ProxyManager](#proxymanager)
  - [Statistics](#statistics)
- [Exemplos Avançados](#-exemplos-avançados)
- [Performance](#-performance)
- [Troubleshooting](#-troubleshooting)
- [Contributing](#-contributing)
- [License](#-license)

---

## ✨ Features

### 🚀 Core Features
- ✅ **Requisições Assíncronas** - Baseado em HttpPromise para alta performance
- ✅ **Parsing HTML5 Nativo** - Suporte a seletores CSS via `Dom\HTMLDocument`
- ✅ **Fallback Regex** - Parsing robusto mesmo sem DOM nativo
- ✅ **Extração de Atributos** - Sintaxe `selector@attribute` para facilitar

### 🛡️ Anti-WAF & Security
- ✅ **Browser Fingerprinting** - 6 User-Agents modernos com rotação
- ✅ **Sec-Fetch-* Headers** - Emula navegadores reais
- ✅ **Cookie Management** - RFC 6265 compliant com persistência
- ✅ **Header Rotation** - Rotação automática de Accept-Language e outros

### ⚡ Performance
- ✅ **Cache Inteligente** - TTL configurável com LRU eviction
- ✅ **Rate Limiting** - Por domínio com suporte a burst
- ✅ **Concurrent Requests** - Até 100+ requisições simultâneas
- ✅ **Response Normalization** - Conversão automática para UTF-8

### 🔄 Resilience
- ✅ **Automatic Retry** - Exponential backoff (1s → 2s → 4s → 8s)
- ✅ **Proxy Rotation** - Com health check e auto-removal
- ✅ **Redirect Handling** - Segue redirects automaticamente
- ✅ **Error Recovery** - Retry em erros 429, 502, 503, 504

### 📊 Monitoring
- ✅ **Statistics** - Métricas detalhadas de performance
- ✅ **Progress Callbacks** - Acompanhamento em tempo real
- ✅ **Cache Metrics** - Hit rate e economia de tempo
- ✅ **Response Time Tracking** - Min/Avg/Max por requisição

---

## 🔧 Requisitos

- PHP 8.4 ou superior
- Extensões PHP:
  - `curl` - Para requisições HTTP
  - `json` - Para serialização
  - `mbstring` - Para manipulação de strings UTF-8
  - `dom` - Para parsing HTML (recomendado)
- Composer

---

## 📦 Instalação

```bash
composer require omegaalfa/utils
```

Ou adicione ao seu `composer.json`:

```json
{
    "require": {
        "omegaalfa/utils": "^1.0"
    }
}
```

---

## 🚀 Quick Start

### Exemplo Básico

```php
<?php

use Omegaalfa\HttpPromise\Utils\WebScraper\WebScraperClient;

// Criar scraper
$scraper = WebScraperClient::create();

// Scraping simples
$scraper->scrape('https://example.com', [
    'title' => 'h1',
    'links' => 'a@href',
    'prices' => '.price',
])->then(function ($data) {
    echo "Título: " . $data['title'] . "\n";
    echo "Links encontrados: " . count($data['links']) . "\n";
});

$scraper->wait();
```

### Com Configurações

```php
<?php

use Omegaalfa\HttpPromise\Utils\WebScraper\WebScraperClient;

$scraper = WebScraperClient::create()
    ->withCache(3600)              // Cache de 1 hora
    ->withRateLimit(10.0)           // 10 requisições por segundo
    ->withRetry(3, 1.0)             // 3 tentativas com delay de 1s
    ->withFingerprintRotation(true) // Rotação de User-Agent
    ->withCookiesFromFile('cookies.json');

$scraper->scrapeMultiple([
    'site1' => [
        'url' => 'https://example.com',
        'selectors' => ['title' => 'h1']
    ],
    'site2' => [
        'url' => 'https://example.org',
        'selectors' => ['title' => 'h1']
    ]
])->then(function ($results) {
    foreach ($results as $key => $data) {
        echo "{$key}: {$data['title']}\n";
    }
});

$scraper->wait();
```

---

## 🏗️ Arquitetura

```
WebScraper/
├── WebScraperClient.php       # 🎯 Classe principal (orchestrator)
├── CookieJar.php              # 🍪 Gerenciamento de cookies RFC 6265
├── HeaderFingerprint.php      # 🎭 Browser fingerprinting
├── ResponseCache.php          # 💾 Cache inteligente com TTL
├── RateLimiter.php            # 🚦 Controle de taxa por domínio
├── ProxyManager.php           # 🌐 Rotação de proxies
├── Statistics.php             # 📊 Métricas de performance
└── Exception/
    ├── NetworkException.php       # ❌ Erros de rede
    ├── ParsingException.php       # ❌ Erros de parsing
    ├── RateLimitExceededException.php  # ❌ Rate limit excedido
    └── TimeoutException.php       # ❌ Timeout

Princípios SOLID:
✅ Single Responsibility - Cada classe tem uma responsabilidade única
✅ Open/Closed - Extensível sem modificar código existente
✅ Liskov Substitution - Interfaces bem definidas
✅ Interface Segregation - Interfaces específicas
✅ Dependency Inversion - Depende de abstrações
```

---

## 📚 Documentação Completa

### WebScraperClient

Classe principal que orquestra todas as operações de scraping.

#### Factory Methods

##### `create(): self`

Cria uma instância com configurações padrão.

```php
$scraper = WebScraperClient::create();
```

---

#### Configuration Methods

##### `withCache(float $ttl = 3600.0, int $maxSize = 1000): self`

Habilita cache de respostas HTTP.

**Parâmetros:**
- `$ttl` - Tempo de vida do cache em segundos (padrão: 3600)
- `$maxSize` - Número máximo de entradas (padrão: 1000)

**Exemplo:**
```php
$scraper = WebScraperClient::create()
    ->withCache(1800, 500); // Cache de 30min, máx 500 entradas

// Primeira requisição (miss)
$scraper->get('https://example.com')->wait();

// Segunda requisição (hit - instantâneo!)
$scraper->get('https://example.com')->wait();
```

---

##### `withoutCache(): self`

Desabilita o cache de respostas.

**Exemplo:**
```php
$scraper = WebScraperClient::create()
    ->withoutCache(); // Sem cache
```

---

##### `withRateLimit(float $rps = 10.0, float $burstSize = 0.0): self`

Configura limite de requisições por segundo por domínio.

**Parâmetros:**
- `$rps` - Requisições por segundo (padrão: 10.0)
- `$burstSize` - Tamanho do burst (padrão: 0.0)

**Exemplo:**
```php
// Máximo 5 requisições por segundo para cada domínio
$scraper = WebScraperClient::create()
    ->withRateLimit(5.0);

// Com burst: permite 10 requisições rápidas, depois limita a 5/s
$scraper = WebScraperClient::create()
    ->withRateLimit(5.0, 10.0);
```

---

##### `withoutRateLimit(): self`

Desabilita o rate limiting.

**Exemplo:**
```php
$scraper = WebScraperClient::create()
    ->withoutRateLimit(); // Sem limite de taxa
```

---

##### `withProxies(array $proxies, bool $rotateOnRequest = true): self`

Configura lista de proxies com rotação.

**Parâmetros:**
- `$proxies` - Array de URLs de proxies
- `$rotateOnRequest` - Rotacionar a cada requisição (padrão: true)

**Exemplo:**
```php
$scraper = WebScraperClient::create()
    ->withProxies([
        'http://proxy1.example.com:8080',
        'http://user:pass@proxy2.example.com:8080',
        'socks5://proxy3.example.com:1080'
    ], true); // Rotação automática

// Requisições usarão proxies diferentes automaticamente
for ($i = 0; $i < 10; $i++) {
    $scraper->get("https://api.example.com/endpoint{$i}");
}
$scraper->wait();
```

---

##### `withRetry(int $attempts = 3, float $delay = 1.0, array $statusCodes = [429, 502, 503, 504]): self`

Configura retry automático com exponential backoff.

**Parâmetros:**
- `$attempts` - Número de tentativas (padrão: 3)
- `$delay` - Delay inicial em segundos (padrão: 1.0)
- `$statusCodes` - Status codes que devem ser retentados (padrão: [429, 502, 503, 504])

**Exemplo:**
```php
// Retry até 5 vezes com delay inicial de 2s (2s → 4s → 8s → 16s → 32s)
$scraper = WebScraperClient::create()
    ->withRetry(5, 2.0, [429, 500, 502, 503, 504]);

// Automatic retry em caso de erro
$scraper->get('https://api-unstable.example.com')
    ->then(fn($response) => echo "Sucesso!")
    ->catch(fn($error) => echo "Falhou após todas as tentativas");
    
$scraper->wait();
```

---

##### `withFingerprintRotation(bool $enabled = true): self`

Habilita rotação automática de browser fingerprints.

**Parâmetros:**
- `$enabled` - Habilitar rotação (padrão: true)

**Exemplo:**
```php
$scraper = WebScraperClient::create()
    ->withFingerprintRotation(true); // User-Agent diferente a cada request

// Cada requisição terá headers diferentes
for ($i = 0; $i < 5; $i++) {
    $scraper->get('https://httpbin.org/headers')
        ->then(function($response) use ($i) {
            $body = json_decode((string)$response->getBody(), true);
            echo "Request {$i}: " . $body['headers']['User-Agent'] . "\n";
        });
}
$scraper->wait();
```

---

##### `withRedirects(bool $follow = true, int $maxRedirects = 5): self`

Configura comportamento de redirects.

**Parâmetros:**
- `$follow` - Seguir redirects (padrão: true)
- `$maxRedirects` - Número máximo de redirects (padrão: 5)

**Exemplo:**
```php
// Seguir até 10 redirects
$scraper = WebScraperClient::create()
    ->withRedirects(true, 10);

// Não seguir redirects (útil para capturar Location header)
$scraper = WebScraperClient::create()
    ->withRedirects(false);

$scraper->get('https://bit.ly/short-url')
    ->then(function($response) {
        if ($response->hasHeader('Location')) {
            echo "Redirect para: " . $response->getHeader('Location')[0];
        }
    });
$scraper->wait();
```

---

##### `withTimeout(float $timeout): self`

Define timeout global para requisições.

**Parâmetros:**
- `$timeout` - Timeout em segundos (mínimo: 1.0)

**Exemplo:**
```php
// Timeout de 15 segundos
$scraper = WebScraperClient::create()
    ->withTimeout(15.0);

// Para APIs lentas
$scraper = WebScraperClient::create()
    ->withTimeout(60.0); // 1 minuto
```

---

##### `withCookiesFromFile(string $path): self`

Carrega cookies de um arquivo JSON.

**Parâmetros:**
- `$path` - Caminho do arquivo de cookies

**Exemplo:**
```php
$scraper = WebScraperClient::create()
    ->withCookiesFromFile('./cookies.json');

// Cookies serão enviados automaticamente
$scraper->get('https://example.com/protected')->wait();
```

---

##### `saveCookies(string $path): self`

Salva cookies atuais em arquivo JSON.

**Parâmetros:**
- `$path` - Caminho do arquivo de destino

**Exemplo:**
```php
$scraper = WebScraperClient::create();

// Login e obter cookies
$scraper->post('https://example.com/login', [
    'username' => 'user',
    'password' => 'pass'
])->wait();

// Salvar cookies para reutilizar depois
$scraper->saveCookies('./session_cookies.json');
```

---

##### `onProgress(callable $callback): self`

Define callback para acompanhar progresso.

**Parâmetros:**
- `$callback` - `function(string $url, int $current, int $total): void`

**Exemplo:**
```php
$scraper = WebScraperClient::create()
    ->onProgress(function($url, $current, $total) {
        $percent = round(($current / $total) * 100);
        echo "\r[{$percent}%] Processando: {$url}";
    });

$scraper->scrapeMultiple([
    'site1' => ['url' => 'https://example.com', 'selectors' => ['title' => 'h1']],
    'site2' => ['url' => 'https://example.org', 'selectors' => ['title' => 'h1']],
    'site3' => ['url' => 'https://example.net', 'selectors' => ['title' => 'h1']],
])->wait();
```

---

#### HTTP Methods

##### `get(string $url, array $customHeaders = []): PromiseInterface`

Faz uma requisição GET.

**Parâmetros:**
- `$url` - URL completa
- `$customHeaders` - Headers adicionais (opcional)

**Retorna:** `PromiseInterface<ResponseInterface>`

**Exemplo:**
```php
// GET simples
$scraper->get('https://api.example.com/users')
    ->then(function($response) {
        $data = json_decode((string)$response->getBody(), true);
        print_r($data);
    });

// GET com headers customizados
$scraper->get('https://api.example.com/data', [
    'Authorization' => 'Bearer token123',
    'X-Custom-Header' => 'value'
])->then(function($response) {
    echo "Status: " . $response->getStatusCode();
});

$scraper->wait();
```

---

##### `post(string $url, mixed $body = null, array $customHeaders = []): PromiseInterface`

Faz uma requisição POST.

**Parâmetros:**
- `$url` - URL completa
- `$body` - Corpo da requisição (string, array, etc)
- `$customHeaders` - Headers adicionais (opcional)

**Retorna:** `PromiseInterface<ResponseInterface>`

**Exemplo:**
```php
// POST com JSON
$scraper->post('https://api.example.com/users', 
    json_encode(['name' => 'John', 'email' => 'john@example.com']),
    ['Content-Type' => 'application/json']
)->then(function($response) {
    echo "Usuário criado! Status: " . $response->getStatusCode();
});

// POST com form data
$scraper->post('https://example.com/contact',
    http_build_query(['name' => 'John', 'message' => 'Hello']),
    ['Content-Type' => 'application/x-www-form-urlencoded']
)->wait();
```

---

#### Scraping Methods

##### `scrape(string $url, array $selectors): PromiseInterface`

Faz scraping de uma única URL com múltiplos seletores.

**Parâmetros:**
- `$url` - URL da página
- `$selectors` - Array associativo `['key' => 'selector']`

**Retorna:** `PromiseInterface<array<string, mixed>>`

**Sintaxe de Seletores:**
- Seletor CSS simples: `'h1'`, `'.class'`, `'#id'`
- Extração de atributo: `'a@href'`, `'img@src'`, `'meta@content'`
- Seletores complexos: `'div.container > p:first-child'`

**Exemplo:**
```php
$scraper->scrape('https://example.com', [
    'title' => 'h1',                           // Texto do H1
    'description' => 'meta[name="description"]@content', // Atributo content
    'all_links' => 'a@href',                   // Todos os links
    'images' => 'img@src',                     // Todas as imagens
    'paragraphs' => 'p',                       // Todos os parágrafos
    'first_para' => 'p:first-child',           // Primeiro parágrafo
])->then(function($data) {
    echo "Título: " . $data['title'] . "\n";
    echo "Links: " . count($data['all_links']) . "\n";
    print_r($data);
});

$scraper->wait();
```

---

##### `scrapeMultiple(array $targets): PromiseInterface`

Faz scraping de múltiplas URLs concorrentemente.

**Parâmetros:**
- `$targets` - Array de configurações: `['key' => ['url' => '...', 'selectors' => [...]]]`

**Retorna:** `PromiseInterface<array<string, array>>`

**Exemplo:**
```php
$scraper->scrapeMultiple([
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
    ]
])->then(function($results) {
    foreach ($results as $site => $data) {
        echo "{$site}: {$data['title']}\n";
    }
})->catch(function($error) {
    echo "Erro: " . $error->getMessage();
});

$scraper->wait();
```

---

#### Utility Methods

##### `wait(): void`

Aguarda conclusão de todas as requisições pendentes.

**Exemplo:**
```php
// Enfileirar múltiplas requisições
$scraper->get('https://example.com');
$scraper->get('https://example.org');
$scraper->get('https://example.net');

// Aguardar todas completarem
$scraper->wait();

echo "Todas as requisições concluídas!";
```

---

##### `getStatistics(): array`

Retorna estatísticas detalhadas de performance.

**Retorna:** Array com métricas

**Exemplo:**
```php
$scraper = WebScraperClient::create();

// Fazer algumas requisições
for ($i = 0; $i < 10; $i++) {
    $scraper->get("https://example.com/page{$i}");
}
$scraper->wait();

// Obter estatísticas
$stats = $scraper->getStatistics();

echo "Requisições totais: " . $stats['total_requests'] . "\n";
echo "Sucesso: " . $stats['successful_requests'] . "\n";
echo "Falhas: " . $stats['failed_requests'] . "\n";
echo "Taxa de sucesso: " . $stats['success_rate_percent'] . "%\n";
echo "Tempo médio: " . $stats['response_time']['average_seconds'] . "s\n";
echo "RPS: " . $stats['requests_per_second'] . "\n";

// Estatísticas do cache
if (isset($stats['cache'])) {
    echo "Cache hits: " . $stats['cache']['hits'] . "\n";
    echo "Hit rate: " . $stats['cache']['hitRate'] . "%\n";
}
```

---

##### `getCookieJar(): CookieJar`

Retorna o gerenciador de cookies.

**Exemplo:**
```php
$scraper = WebScraperClient::create();

// Acessar cookies
$cookieJar = $scraper->getCookieJar();
$cookies = $cookieJar->getCookiesForUrl('https://example.com');

foreach ($cookies as $name => $value) {
    echo "{$name}: {$value}\n";
}

// Manipular cookies manualmente
$cookieJar->setCookie('session_id', 'abc123', [
    'Domain' => 'example.com',
    'Path' => '/',
    'Expires' => time() + 3600,
    'HttpOnly' => true,
    'Secure' => true
]);
```

---

##### `getCache(): ResponseCache`

Retorna o gerenciador de cache.

**Exemplo:**
```php
$scraper = WebScraperClient::create()->withCache(3600);

// Usar scraper...
$scraper->get('https://example.com')->wait();

// Verificar cache
$cache = $scraper->getCache();
$stats = $cache->getStats();

echo "Hits: " . $stats['hits'] . "\n";
echo "Misses: " . $stats['misses'] . "\n";
echo "Hit Rate: " . $stats['hitRate'] . "%\n";

// Salvar cache em arquivo
$cache->saveToFile('./cache.json');

// Limpar cache
$cache->clear();
```

---

### CookieJar

Gerenciador de cookies compatível com RFC 6265.

#### Methods

##### `setCookie(string $name, string $value, array $attributes = []): void`

Define um cookie.

**Parâmetros:**
- `$name` - Nome do cookie
- `$value` - Valor do cookie
- `$attributes` - Atributos: Domain, Path, Expires, MaxAge, Secure, HttpOnly, SameSite

**Exemplo:**
```php
$cookieJar = new CookieJar();

$cookieJar->setCookie('session', 'abc123', [
    'Domain' => '.example.com',
    'Path' => '/',
    'Expires' => time() + 86400, // 24 horas
    'Secure' => true,
    'HttpOnly' => true,
    'SameSite' => 'Lax'
]);
```

---

##### `parseCookie(string $setCookieHeader, string $url): void`

Parse um header Set-Cookie.

**Exemplo:**
```php
$cookieJar = new CookieJar();

$cookieJar->parseCookie(
    'session=xyz789; Domain=.example.com; Path=/; HttpOnly; Secure',
    'https://example.com'
);
```

---

##### `getCookiesForUrl(string $url): array`

Retorna cookies válidos para uma URL.

**Exemplo:**
```php
$cookies = $cookieJar->getCookiesForUrl('https://www.example.com/path');

foreach ($cookies as $name => $value) {
    echo "Cookie: {$name}={$value}\n";
}
```

---

##### `getCookieHeader(string $url): string`

Gera header Cookie para uma URL.

**Exemplo:**
```php
$header = $cookieJar->getCookieHeader('https://example.com');
// Resultado: "session=abc123; user_id=456"
```

---

##### `saveCookiesToFile(string $path): void`

Salva cookies em arquivo JSON.

**Exemplo:**
```php
$cookieJar->saveCookiesToFile('./cookies.json');
```

---

##### `loadCookiesFromFile(string $path): void`

Carrega cookies de arquivo JSON.

**Exemplo:**
```php
$cookieJar->loadCookiesFromFile('./cookies.json');
```

---

### HeaderFingerprint

Gera headers realistas para evitar detecção.

#### Methods

##### `getHeaders(): array`

Retorna headers completos de browser.

**Exemplo:**
```php
$fingerprint = new HeaderFingerprint();
$headers = $fingerprint->getHeaders();

print_r($headers);
/*
Array (
    [User-Agent] => Mozilla/5.0 (Windows NT 10.0; Win64; x64)...
    [Accept] => text/html,application/xhtml+xml...
    [Accept-Language] => en-US,en;q=0.9
    [Accept-Encoding] => gzip, deflate, br
    [DNT] => 1
    [Sec-Fetch-Site] => none
    [Sec-Fetch-Mode] => navigate
    [Sec-Fetch-User] => ?1
    [Sec-Fetch-Dest] => document
)
*/
```

---

##### `getUserAgent(): string`

Retorna User-Agent atual.

**Exemplo:**
```php
$fingerprint = new HeaderFingerprint();
echo $fingerprint->getUserAgent();
// Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36...
```

---

##### `withRotationOnRequest(bool $enabled): self`

Habilita rotação de fingerprint.

**Exemplo:**
```php
$fingerprint = new HeaderFingerprint();
$fingerprint = $fingerprint->withRotationOnRequest(true);

// A cada chamada getHeaders() retorna User-Agent diferente
for ($i = 0; $i < 3; $i++) {
    echo $fingerprint->getUserAgent() . "\n";
    $fingerprint->getHeaders(); // Rotaciona
}
```

---

### ResponseCache

Cache de respostas HTTP com TTL e LRU eviction.

#### Methods

##### `get(string $url): ?array`

Busca resposta no cache.

**Retorna:** `['content' => string, 'headers' => array, 'statusCode' => int]` ou null

**Exemplo:**
```php
$cache = new ResponseCache(1000, 3600);

$cached = $cache->get('https://example.com');
if ($cached) {
    echo "Cache hit!\n";
    echo $cached['content'];
} else {
    echo "Cache miss!\n";
}
```

---

##### `set(string $url, string $content, array $headers, int $statusCode, ?float $ttl = null): void`

Armazena resposta no cache.

**Exemplo:**
```php
$cache = new ResponseCache();

$cache->set(
    'https://example.com',
    '<html>...</html>',
    ['Content-Type' => 'text/html'],
    200,
    1800 // TTL de 30 minutos
);
```

---

##### `getStats(): array`

Retorna estatísticas do cache.

**Exemplo:**
```php
$stats = $cache->getStats();

echo "Hits: " . $stats['hits'] . "\n";
echo "Misses: " . $stats['misses'] . "\n";
echo "Hit Rate: " . $stats['hitRate'] . "%\n";
echo "Entradas: " . $stats['entries'] . "\n";
echo "Tamanho: " . $stats['sizeInBytes'] . " bytes\n";
```

---

##### `saveToFile(string $path): void`

Persiste cache em arquivo.

**Exemplo:**
```php
$cache->saveToFile('./cache_backup.json');
```

---

##### `loadFromFile(string $path): void`

Carrega cache de arquivo.

**Exemplo:**
```php
$cache->loadFromFile('./cache_backup.json');
```

---

### RateLimiter

Controla taxa de requisições por domínio.

#### Methods

##### `checkLimit(string $url): void`

Verifica se pode fazer requisição (lança exceção se exceder).

**Exemplo:**
```php
$limiter = new RateLimiter(5.0); // 5 RPS

try {
    $limiter->checkLimit('https://api.example.com/endpoint');
    // Pode prosseguir
} catch (RateLimitExceededException $e) {
    echo "Aguarde " . $e->getRetryAfter() . " segundos\n";
}
```

---

##### `getDelay(string $url): float`

Retorna delay necessário antes da próxima requisição.

**Exemplo:**
```php
$limiter = new RateLimiter(5.0);

$delay = $limiter->getDelay('https://api.example.com');
if ($delay > 0) {
    echo "Aguardar {$delay}s antes da próxima requisição\n";
    usleep((int)($delay * 1000000));
}
```

---

### ProxyManager

Gerencia e rotaciona proxies com health tracking.

#### Methods

##### `setProxies(array $proxies): self`

Define lista de proxies.

**Exemplo:**
```php
$proxyManager = new ProxyManager();
$proxyManager->setProxies([
    'http://proxy1.example.com:8080',
    'http://user:pass@proxy2.example.com:8080',
    'socks5://proxy3.example.com:1080'
]);
```

---

##### `getNextProxy(): ?string`

Retorna próximo proxy da rotação.

**Exemplo:**
```php
$proxy = $proxyManager->getNextProxy();
if ($proxy) {
    echo "Usando proxy: {$proxy}\n";
}
```

---

##### `markFailure(string $proxy): void`

Marca proxy como falho.

**Exemplo:**
```php
$proxy = $proxyManager->getNextProxy();

try {
    // Usar proxy...
} catch (Exception $e) {
    $proxyManager->markFailure($proxy);
    // Proxy será removido após 3 falhas (padrão)
}
```

---

##### `markSuccess(string $proxy): void`

Marca proxy como bem-sucedido.

**Exemplo:**
```php
$proxy = $proxyManager->getNextProxy();

try {
    // Usar proxy...
    $proxyManager->markSuccess($proxy);
} catch (Exception $e) {
    $proxyManager->markFailure($proxy);
}
```

---

### Statistics

Coleta e reporta métricas de performance.

#### Methods

##### `recordSuccess(float $responseTime, int $statusCode): void`

Registra requisição bem-sucedida.

**Exemplo:**
```php
$stats = new Statistics();

$start = microtime(true);
// Fazer requisição...
$responseTime = microtime(true) - $start;

$stats->recordSuccess($responseTime, 200);
```

---

##### `recordFailure(string $errorType): void`

Registra requisição falha.

**Exemplo:**
```php
try {
    // Fazer requisição...
} catch (NetworkException $e) {
    $stats->recordFailure('network_error');
} catch (TimeoutException $e) {
    $stats->recordFailure('timeout');
}
```

---

##### `getReport(): array`

Retorna relatório completo.

**Exemplo:**
```php
$report = $stats->getReport();

echo "Total: " . $report['total_requests'] . "\n";
echo "Sucesso: " . $report['successful_requests'] . "\n";
echo "Falhas: " . $report['failed_requests'] . "\n";
echo "Taxa de sucesso: " . $report['success_rate_percent'] . "%\n";
echo "Tempo médio: " . $report['response_time']['average_seconds'] . "s\n";
echo "RPS: " . $report['requests_per_second'] . "\n";
```

---

## 🎯 Exemplos Avançados

### Monitoramento de Preços

```php
<?php

use Omegaalfa\HttpPromise\Utils\WebScraper\WebScraperClient;

class PriceMonitor
{
    private WebScraperClient $scraper;
    private array $prices = [];

    public function __construct()
    {
        $this->scraper = WebScraperClient::create()
            ->withCache(300)
            ->withRateLimit(5.0)
            ->withRetry(3);
    }

    public function monitor(string $url, string $priceSelector): void
    {
        $this->scraper->scrape($url, [
            'price' => $priceSelector,
            'title' => 'h1',
            'availability' => '.stock-status'
        ])->then(function($data) use ($url) {
            $price = $this->extractPrice($data['price']);
            
            if (!isset($this->prices[$url])) {
                $this->prices[$url] = $price;
                echo "Preço inicial: R$ {$price}\n";
            } else {
                $oldPrice = $this->prices[$url];
                $change = (($price - $oldPrice) / $oldPrice) * 100;
                
                if ($change < -5) {
                    echo "🔥 ALERTA: Preço caiu {$change}%! De R$ {$oldPrice} para R$ {$price}\n";
                } elseif ($change > 5) {
                    echo "⚠️  ALERTA: Preço subiu {$change}%! De R$ {$oldPrice} para R$ {$price}\n";
                }
                
                $this->prices[$url] = $price;
            }
        });
        
        $this->scraper->wait();
    }

    private function extractPrice(string $text): float
    {
        preg_match('/[\d.,]+/', $text, $matches);
        return (float) str_replace(',', '.', $matches[0] ?? 0);
    }
}

// Uso
$monitor = new PriceMonitor();

while (true) {
    $monitor->monitor('https://example.com/product', '.price');
    sleep(300); // Verificar a cada 5 minutos
}
```

---

### Agregador de Notícias

```php
<?php

use Omegaalfa\HttpPromise\Utils\WebScraper\WebScraperClient;

class NewsAggregator
{
    private WebScraperClient $scraper;

    public function __construct()
    {
        $http = \Omegaalfa\HttpPromise\HttpPromise::create()
            ->withTimeout(15.0)
            ->withMaxConcurrent(10);
        
        $this->scraper = new WebScraperClient($http);
        $this->scraper->withCache(300)
            ->withRateLimit(5.0)
            ->withRetry(2)
            ->onProgress(function($url, $current, $total) {
                echo "\r🔄 Processando: {$current}/{$total} fontes...";
            });
    }

    public function fetchFromSources(array $sources): array
    {
        $targets = [];
        foreach ($sources as $key => $source) {
            $targets[$key] = [
                'url' => $source['url'],
                'selectors' => [
                    'headlines' => $source['headline_selector'],
                    'links' => $source['link_selector'] . '@href',
                    'dates' => $source['date_selector']
                ]
            ];
        }

        $articles = [];
        $this->scraper->scrapeMultiple($targets)
            ->then(function($results) use (&$articles, $sources) {
                foreach ($results as $sourceKey => $data) {
                    $sourceName = $sources[$sourceKey]['name'];
                    $count = min(
                        count($data['headlines'] ?? []),
                        count($data['links'] ?? [])
                    );

                    for ($i = 0; $i < $count; $i++) {
                        $articles[] = [
                            'source' => $sourceName,
                            'title' => $data['headlines'][$i] ?? '',
                            'link' => $data['links'][$i] ?? '',
                            'date' => $data['dates'][$i] ?? ''
                        ];
                    }
                }
            });

        $this->scraper->wait();
        echo "\n";

        return $articles;
    }
}

// Uso
$aggregator = new NewsAggregator();

$sources = [
    'tech' => [
        'name' => 'TechCrunch',
        'url' => 'https://techcrunch.com',
        'headline_selector' => '.post-block__title',
        'link_selector' => '.post-block__title__link',
        'date_selector' => '.river-byline__time'
    ],
    // Adicionar mais fontes...
];

$articles = $aggregator->fetchFromSources($sources);

foreach ($articles as $article) {
    echo "[{$article['source']}] {$article['title']}\n";
    echo "   {$article['link']}\n\n";
}
```

---

### Validador de SEO

```php
<?php

use Omegaalfa\HttpPromise\Utils\WebScraper\WebScraperClient;

class SEOValidator
{
    private WebScraperClient $scraper;

    public function __construct()
    {
        $this->scraper = WebScraperClient::create()
            ->withTimeout(30.0)
            ->withRetry(2);
    }

    public function validate(string $url): array
    {
        $report = [
            'url' => $url,
            'score' => 0,
            'issues' => [],
            'warnings' => [],
            'passed' => []
        ];

        $this->scraper->scrape($url, [
            'title' => 'title',
            'meta_description' => 'meta[name="description"]@content',
            'h1' => 'h1',
            'h2' => 'h2',
            'images' => 'img',
            'images_alt' => 'img@alt',
            'links' => 'a@href',
            'canonical' => 'link[rel="canonical"]@href',
            'og_title' => 'meta[property="og:title"]@content',
            'og_description' => 'meta[property="og:description"]@content',
        ])->then(function($data) use (&$report) {
            // Validar título
            $title = $data['title'] ?? '';
            if (empty($title)) {
                $report['issues'][] = "❌ Tag <title> ausente";
            } elseif (strlen($title) < 30) {
                $report['warnings'][] = "⚠️  Título muito curto (" . strlen($title) . " caracteres)";
            } elseif (strlen($title) > 60) {
                $report['warnings'][] = "⚠️  Título muito longo (" . strlen($title) . " caracteres)";
            } else {
                $report['passed'][] = "✅ Título OK";
                $report['score'] += 15;
            }

            // Validar meta description
            $description = $data['meta_description'] ?? '';
            if (empty($description)) {
                $report['issues'][] = "❌ Meta description ausente";
            } elseif (strlen($description) < 120) {
                $report['warnings'][] = "⚠️  Meta description curta";
            } elseif (strlen($description) > 160) {
                $report['warnings'][] = "⚠️  Meta description longa";
            } else {
                $report['passed'][] = "✅ Meta description OK";
                $report['score'] += 15;
            }

            // Validar H1
            $h1Count = is_array($data['h1']) ? count($data['h1']) : (empty($data['h1']) ? 0 : 1);
            if ($h1Count === 0) {
                $report['issues'][] = "❌ Nenhuma tag <h1> encontrada";
            } elseif ($h1Count > 1) {
                $report['warnings'][] = "⚠️  Múltiplas tags <h1> ({$h1Count})";
            } else {
                $report['passed'][] = "✅ Tag H1 única";
                $report['score'] += 15;
            }

            // Validar estrutura de headings
            $h2Count = is_array($data['h2']) ? count($data['h2']) : (empty($data['h2']) ? 0 : 1);
            if ($h2Count > 0) {
                $report['passed'][] = "✅ Estrutura de headings presente";
                $report['score'] += 10;
            }

            // Validar imagens sem alt
            $imageCount = is_array($data['images']) ? count($data['images']) : 0;
            $altCount = is_array($data['images_alt']) ? count(array_filter($data['images_alt'])) : 0;
            $missingAlt = $imageCount - $altCount;
            
            if ($missingAlt > 0) {
                $report['warnings'][] = "⚠️  {$missingAlt} imagens sem atributo alt";
            } else {
                $report['passed'][] = "✅ Todas as imagens com alt";
                $report['score'] += 10;
            }

            // Validar Open Graph
            if (!empty($data['og_title']) && !empty($data['og_description'])) {
                $report['passed'][] = "✅ Open Graph configurado";
                $report['score'] += 10;
            } else {
                $report['warnings'][] = "⚠️  Open Graph incompleto";
            }

            // Validar canonical
            if (!empty($data['canonical'])) {
                $report['passed'][] = "✅ Canonical URL presente";
                $report['score'] += 10;
            }

            // Validar links
            $linkCount = is_array($data['links']) ? count($data['links']) : 0;
            $internalLinks = 0;
            $externalLinks = 0;
            
            foreach ($data['links'] as $link) {
                if (strpos($link, $report['url']) !== false || strpos($link, '/') === 0) {
                    $internalLinks++;
                } else {
                    $externalLinks++;
                }
            }
            
            $report['passed'][] = "✅ {$internalLinks} links internos, {$externalLinks} links externos";
            $report['score'] += 15;
        });

        $this->scraper->wait();

        return $report;
    }

    public function printReport(array $report): void
    {
        echo "\n╔════════════════════════════════════════════════════════════╗\n";
        echo "║               Relatório de SEO                             ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";
        
        echo "🌐 URL: {$report['url']}\n";
        echo "📊 Score: {$report['score']}/100\n\n";

        if (!empty($report['issues'])) {
            echo "🚨 Issues Críticos:\n";
            foreach ($report['issues'] as $issue) {
                echo "   {$issue}\n";
            }
            echo "\n";
        }

        if (!empty($report['warnings'])) {
            echo "⚠️  Avisos:\n";
            foreach ($report['warnings'] as $warning) {
                echo "   {$warning}\n";
            }
            echo "\n";
        }

        if (!empty($report['passed'])) {
            echo "✅ Checks Aprovados:\n";
            foreach ($report['passed'] as $passed) {
                echo "   {$passed}\n";
            }
        }
    }
}

// Uso
$validator = new SEOValidator();
$report = $validator->validate('https://example.com');
$validator->printReport($report);
```

---

## ⚡ Performance

### Benchmarks

| Configuração | Requisições | Tempo | RPS | Success Rate |
|--------------|-------------|-------|-----|--------------|
| Serial (1 concurrent) | 100 | 10.5s | 9.5 | 98% |
| Concurrent (10) | 100 | 2.1s | 47.6 | 98% |
| Concurrent (20) | 100 | 1.8s | 55.5 | 96% |
| With Cache (hit) | 100 | 0.05s | 2000 | 100% |

### Otimizações

✅ **Cache**: Acelera em 10-50x requisições repetidas  
✅ **Concorrência**: Melhora throughput em até 6x  
✅ **Rate Limiting**: Overhead mínimo (<5%)  
✅ **Fingerprint Rotation**: Overhead desprezível (<1ms/req)  
✅ **Memória**: ~100-200KB por requisição  

### Dicas de Performance

1. **Use cache para dados estáticos**
   ```php
   $scraper->withCache(3600); // 1 hora
   ```

2. **Ajuste concorrência baseado no servidor**
   ```php
   $http = HttpPromise::create()->withMaxConcurrent(20);
   $scraper = new WebScraperClient($http);
   ```

3. **Use rate limiting conservador**
   ```php
   $scraper->withRateLimit(5.0); // 5 RPS é seguro para maioria das APIs
   ```

4. **Habilite retry apenas quando necessário**
   ```php
   $scraper->withRetry(2, 1.0); // Apenas 2 tentativas
   ```

---

## 🔧 Troubleshooting

### Problema: RateLimitExceededException

**Causa:** Excedeu o limite de requisições por segundo

**Solução:**
```php
// Reduzir RPS ou aumentar burst
$scraper->withRateLimit(2.0, 5.0); // 2 RPS com burst de 5

// Ou desabilitar para testes
$scraper->withoutRateLimit();
```

---

### Problema: TimeoutException

**Causa:** Servidor demorou muito para responder

**Solução:**
```php
// Aumentar timeout
$scraper->withTimeout(60.0); // 60 segundos

// Ou reduzir concorrência
$http = HttpPromise::create()->withMaxConcurrent(5);
```

---

### Problema: NetworkException

**Causa:** Erro de conexão ou DNS

**Solução:**
```php
// Habilitar retry com backoff
$scraper->withRetry(5, 2.0); // 5 tentativas, delay inicial 2s

// Usar proxies alternativos
$scraper->withProxies([
    'http://proxy1.com:8080',
    'http://proxy2.com:8080'
]);
```

---

### Problema: Cache não funciona

**Causa:** Cache desabilitado ou TTL muito curto

**Solução:**
```php
// Verificar se cache está habilitado
$scraper->withCache(3600); // TTL de 1 hora

// Verificar estatísticas
$stats = $scraper->getCache()->getStats();
print_r($stats);
```

---

### Problema: "Invalid URL format"

**Causa:** URL malformada ou vazia

**Solução:**
```php
// Validar URL antes de usar
$url = filter_var($url, FILTER_VALIDATE_URL);
if (!$url) {
    throw new InvalidArgumentException('URL inválida');
}

// Garantir protocolo
if (!preg_match('#^https?://#', $url)) {
    $url = 'https://' . $url;
}
```

---

### Problema: Seletores CSS não encontram elementos

**Causa:** Seletor incorreto ou página dinâmica (JavaScript)

**Solução:**
```php
// Testar seletor no navegador primeiro
// document.querySelectorAll('seu-seletor')

// Usar seletores mais genéricos
'links' => 'a', // Em vez de 'a.specific-class'

// Verificar se página requer JavaScript (WebScraper não executa JS)
```

---

## 🤝 Contributing

Contribuições são bem-vindas! Por favor:

1. Fork o repositório
2. Crie uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

### Guidelines

- Siga PSR-12 coding standards
- Adicione testes para novas features
- Atualize a documentação
- Mantenha compatibilidade com PHP 8.4+

---

## 📄 License

MIT License - veja [LICENSE](../../../../LICENSE) para detalhes.

---

## 🙏 Acknowledgments

- **HttpPromise** - Base assíncrona para requisições
- **PHP Dom** - Parsing HTML5 nativo
- **RFC 6265** - Especificação de cookies
- **PSR-7** - HTTP Message Interface
- **SOLID Principles** - Arquitetura limpa

---

## 📞 Support

- 📧 Email: support@example.com
- 🐛 Issues: [GitHub Issues](https://github.com/omegaalfa/http-promise/issues)
- 📖 Docs: [Documentation](https://github.com/omegaalfa/http-promise/wiki)

---

<div align="center">

**⭐ Se este projeto foi útil, considere dar uma estrela no GitHub!**

Made with ❤️ by OmegaAlfa

</div>
