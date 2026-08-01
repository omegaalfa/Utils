<div align="center">

# Omegaalfa Utils

**Utilitários PHP pequenos, seguros e sem dependências para scripts, CLIs e microsserviços.**

[![PHP](https://img.shields.io/badge/PHP-%E2%89%A58.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Tests](https://github.com/omegaalfa/Utils/actions/workflows/ci.yaml/badge.svg)](https://github.com/omegaalfa/Utils/actions/workflows/ci.yaml)
[![License](https://img.shields.io/badge/license-MIT-22C55E.svg)](LICENSE)
[![Zero dependencies](https://img.shields.io/badge/runtime_dependencies-0-0EA5E9.svg)](composer.json)

[Instalação](#instalação) · [Uso rápido](#uso-rápido) · [API](#api) · [Segurança](#segurança) · [Contribuição](#contribuição)

</div>

## Por que usar?

O **Omegaalfa Utils** reúne helpers focados e previsíveis sem adicionar frameworks ou dependências à sua aplicação. Cada módulo possui uma responsabilidade clara e pode ser usado isoladamente.

- **Leve:** nenhuma dependência em produção.
- **Moderno:** PHP 8.4+, tipos estritos e autoload PSR-4.
- **Seguro por padrão:** não sobrescreve variáveis existentes e valida entradas.
- **Confiável:** testes automatizados e análise estática no nível máximo.
- **Evolutivo:** estrutura modular pronta para receber novos pacotes utilitários.

## Instalação

```bash
composer require omegaalfa/utils
```

## Uso rápido

Crie um arquivo `.env` na raiz da aplicação:

```dotenv
APP_ENV=production
APP_DEBUG=false
HTTP_PORT=8080
APP_NAME="Minha aplicação"
```

Carregue e consulte os valores:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Omegaalfa\Utils\EnvLoader\EnvLoader;

EnvLoader::load(__DIR__, required: true);

$environment = EnvLoader::require('APP_ENV');
$debug = EnvLoader::getBool('APP_DEBUG', false);
$port = EnvLoader::getInt('HTTP_PORT', 8080);
$name = EnvLoader::get('APP_NAME', 'Aplicação');
```

O caminho pode apontar diretamente para um arquivo ou para um diretório que contenha `.env`. Sem argumento, `load()` procura `.env` no diretório de execução atual.

## EnvLoader

O loader reconhece linhas vazias, comentários, prefixo `export`, comentários inline e valores entre aspas simples ou duplas:

```dotenv
# Comentário
export APP_ENV=production
APP_NAME="Omega Utils" # comentário inline
LITERAL='conteúdo literal'
EMPTY=
```

### API

| Método | Descrição |
|---|---|
| `load(?string $path = null, bool $required = false, bool $overwrite = false, bool $strictPermissions = false): void` | Lê e aplica um arquivo de ambiente. |
| `has(string $key): bool` | Informa se a variável existe. |
| `get(string $key, ?string $default = null): ?string` | Retorna uma string ou o valor padrão. |
| `require(string $key): string` | Retorna um valor obrigatório ou lança uma exceção. |
| `getInt(string $key, ?int $default = null): ?int` | Valida e retorna um inteiro. |
| `getBool(string $key, ?bool $default = null): ?bool` | Aceita `1`, `true`, `yes`, `on`, `0`, `false`, `no` e `off`. |

Por padrão, valores já definidos em `$_ENV`, `$_SERVER` ou no ambiente do processo são preservados. Para substituí-los explicitamente:

```php
EnvLoader::load(__DIR__ . '/.env.testing', overwrite: true);
```

## Segurança

Arquivos `.env` são indicados para desenvolvimento e ambientes controlados. Em produção, prefira variáveis fornecidas pelo gerenciador de processos ou por um serviço de secrets.

O loader:

- rejeita nomes de variável inválidos, bytes NUL e sintaxe malformada;
- limita arquivos a 1 MiB;
- usa leitura com lock compartilhado;
- nunca sobrescreve valores existentes sem autorização explícita;
- pode exigir permissões Unix restritas (`0600`).

```php
EnvLoader::load('/secure/path/.env', required: true, strictPermissions: true);
```

> `strictPermissions` é ignorado no Windows, onde o modelo de permissões Unix não se aplica.

## Tratamento de erros

- `InvalidArgumentException`: chave, valor tipado ou sintaxe inválida.
- `RuntimeException`: arquivo ausente quando obrigatório, inacessível ou inseguro; variável obrigatória ausente.

```php
try {
    EnvLoader::load(__DIR__, required: true);
    $token = EnvLoader::require('API_TOKEN');
} catch (InvalidArgumentException | RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
```

## Desenvolvimento

```bash
composer install
composer test
composer analyse
composer check
composer test:coverage
```

Novos módulos devem permanecer independentes, ter uma API pequena, tipos estritos, documentação e cobertura de testes. Essa regra mantém o pacote útil sem transformá-lo em um framework genérico.

## Contribuição

Contribuições são bem-vindas. Abra uma issue para discutir mudanças maiores e envie um pull request acompanhado de testes para qualquer comportamento novo ou corrigido.

## Licença

Distribuído sob a licença [MIT](LICENSE).
