<div align="center">

# ScriptRunner

**Descoberta e execução interativa de scripts PHP com isolamento de processo e caminhos protegidos.**

Parte do [Omegaalfa Utils](../../README.md) · PHP 8.4+ · zero dependências de runtime

</div>

## Introdução

O ScriptRunner transforma uma ou mais pastas confiáveis em um menu navegável no terminal. Ele encontra scripts PHP, permite percorrer subdiretórios e executa cada arquivo em um processo separado, preservando stdout, stderr e o exit code.

O módulo é propositalmente pequeno. Ele não é um framework de comandos, agendador ou substituto do módulo CLI.

## Início rápido

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Omegaalfa\Utils\ScriptRunner\ScriptRunner;

$runner = new ScriptRunner([
    __DIR__ . '/scripts',
    __DIR__ . '/maintenance',
]);

$runner->run();
```

Diretórios são exibidos antes dos scripts e as entradas são ordenadas naturalmente. Após a execução, o usuário pode executar novamente, escolher outro script ou sair.

## API

### ScriptRunner

É a fachada recomendada para uso interativo.

```php
$runner = new ScriptRunner(
    directories: [__DIR__ . '/scripts'],
    phpBinary: PHP_BINARY,
);

$runner->run();
```

phpBinary permite selecionar explicitamente o executável. O padrão é PHP_BINARY.

### Descoberta e execução programática

Os componentes menores podem ser usados quando uma aplicação já possui sua própria interface:

```php
use Omegaalfa\Utils\ScriptRunner\ScriptExecutor;
use Omegaalfa\Utils\ScriptRunner\ScriptFinder;

$finder = new ScriptFinder([__DIR__ . '/scripts']);
$entries = $finder->entries(__DIR__ . '/scripts');

$executor = new ScriptExecutor(
    allowedDirectories: $finder->allowedDirectories(),
);

$result = $executor->execute($entries['scripts'][0]);

echo $result->stdout;
fwrite(STDERR, $result->stderr);
exit($result->exitCode);
```

ScriptExecutionResult é final e readonly. Contém o descritor Script, stdout, stderr e exitCode. Exit code diferente de zero é um resultado normal do script. Uma RuntimeException representa falha de preparação, segurança, leitura ou inicialização do processo.

## Modelo de segurança

A lista de raízes é uma fronteira de confiança, não apenas um filtro visual.

- raízes são convertidas com realpath durante a construção;
- raízes inexistentes, ilegíveis, não diretórios ou symlinks são rejeitadas;
- arquivos e diretórios simbólicos são ignorados;
- arquivos ocultos e segmentos ocultos não entram na descoberta;
- apenas arquivos regulares com extensão .php são selecionados;
- pertencimento usa raiz seguida de separador, impedindo colisões como /scripts e /scripts-backup;
- o caminho é novamente canonicalizado e validado imediatamente antes de proc_open;
- arquivos removidos, trocados por symlink ou movidos para fora da raiz são rejeitados.

Cadastre somente diretórios administrados pela aplicação. O módulo impede escape de caminho e interpretação pelo shell, mas um script PHP autorizado continua tendo as permissões do usuário do processo.

## Execução sem shell

O comando entregue ao proc_open é sempre um array:

```php
[
    $phpBinary,
    $canonicalScriptPath,
]
```

Não há concatenação, sh -c, bash -c, cmd /c ou expansão por shell. Assim, espaços e caracteres como ponto e vírgula, e comercial e cifrão no nome permanecem parte literal do caminho.

Cada script executa com dirname do caminho canônico como diretório de trabalho. O comportamento não depende da pasta onde o runner foi iniciado.

## Captura sem deadlock

stdout e stderr são enviados diretamente para dois arquivos temporários diferentes. Depois de proc_close, o executor lê os canais separadamente e preserva o exit code.

Essa estratégia evita bloqueio por pipes cheios quando um processo produz grande volume simultâneo nos dois canais. Os temporários são removidos em finally; falhas silenciosas de limpeza não substituem o resultado ou a exceção principal.

## Terminal e testes

Terminal aceita streams de entrada, saída e erro. Isso permite testar menus sem alterar os canais globais:

```php
$input = fopen('php://memory', 'r+');
$output = fopen('php://memory', 'r+');
$error = fopen('php://memory', 'r+');

fwrite($input, "1\n1\n0\n");
rewind($input);

$runner = new ScriptRunner(
    directories: [__DIR__ . '/scripts'],
    terminal: new Terminal($input, $output, $error),
);

$runner->run();
```

A limpeza de tela usa sequência ANSI somente quando a saída é um TTY. O módulo não adiciona cores, barras de progresso ou formatação avançada.

## Limitações deliberadas

Nesta versão não existem:

- argumentos ou opções para scripts;
- execução paralela ou assíncrona;
- timeout, sinal, cancelamento ou processo em background;
- descoberta via Composer;
- sandbox do sistema operacional ou redução de privilégios;
- cores, autocomplete ou histórico;
- agendamento, retry ou profiler automático.

Argumentos futuros, se adicionados, ocuparão posições independentes no array do comando. Nunca serão concatenados em uma string de shell.

## Erros

InvalidArgumentException indica configuração inválida, como raiz ausente, symlink ou PHP binary vazio. RuntimeException indica mudança no filesystem, violação da raiz, falha de temporário ou processo que não pôde ser iniciado.

O runner interativo captura essas exceções e as escreve em stderr. O uso direto de ScriptFinder e ScriptExecutor deixa a aplicação decidir a política de tratamento.

## Qualidade

```bash
vendor/bin/phpunit tests/ScriptRunner
vendor/bin/phpstan analyse --no-progress --level=max src/ScriptRunner tests/ScriptRunner
composer validate --strict
```
