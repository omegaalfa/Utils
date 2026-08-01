# Fibers

> Scheduler cooperativo mínimo para Fibers nativas do PHP.

[← Portal do ecossistema](../../README.md)

## Introdução

O FiberScheduler executa tarefas em uma única thread e alterna entre elas quando cada tarefa chama `Fiber::suspend()`. Ele existe para coordenação cooperativa simples sem event loop ou dependências.

Use-o em pipelines internos onde tarefas conhecem seus pontos de yield. Não o use para paralelismo de CPU, timers, promises, rede assíncrona ou como substituto de ReactPHP, Amp ou Revolt.

## Principais recursos

- ✅ Fibers nativas;
- ✅ fila `SplQueue`;
- ✅ rotação cooperativa round-robin;
- ✅ limite de tarefas pendentes;
- ✅ resultados associados a IDs estáveis;
- ✅ tarefas podem agendar novas tarefas;
- ✅ propagação fail-fast de exceptions;
- ✅ scheduler reutilizável depois de falha;
- ✅ 100% de cobertura da classe;
- ❌ sem paralelismo;
- ❌ sem timers ou I/O assíncrono;
- ❌ sem cancelamento ou valores de resume.

## Instalação

```bash
composer require omegaalfa/utils
```

Requer PHP 8.4+. `Fiber` e `SplQueue` fazem parte do PHP; não há dependência externa.

## Início rápido

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Fiber;
use Omegaalfa\Utils\Fibers\FiberScheduler;

$scheduler = new FiberScheduler();

$first = $scheduler->schedule(static function (): string {
    echo "A1\n";
    Fiber::suspend();
    echo "A2\n";

    return 'A done';
});

$second = $scheduler->schedule(static function (): string {
    echo "B1\n";
    Fiber::suspend();
    echo "B2\n";

    return 'B done';
});

$results = $scheduler->run();

echo $results[$first] . PHP_EOL;
echo $results[$second] . PHP_EOL;
```

Saída:

```text
A1
B1
A2
B2
A done
B done
```

## Conceitos

### Cooperação, não preempção

O scheduler não interrompe uma tarefa. Ela mantém a thread até retornar ou chamar `Fiber::suspend()`. Um loop infinito sem suspend bloqueia todas as demais.

### Round-robin

Cada Fiber iniciada ou retomada executa até o próximo suspend ou término. Fibers suspensas voltam ao fim da fila, cuja inserção e remoção usam `SplQueue`.

### Resultados

`schedule()` devolve um ID inteiro. `run()` devolve um array associando cada ID ao retorno da respectiva callable. A ordem física do array acompanha a conclusão; use os IDs, não posições sequenciais.

### Falha rápida

A primeira exception encerra `run()`, descarta a fila restante e é propagada sem wrapping. A instância pode receber novas tarefas depois disso.

## Casos de uso

### Pipeline cooperativo

```php
$scheduler = new FiberScheduler(maxTasks: 50);

$parseId = $scheduler->schedule(static function (): array {
    $firstBatch = ['a', 'b'];
    Fiber::suspend();

    return [...$firstBatch, 'c'];
});

$metricsId = $scheduler->schedule(static function (): int {
    Fiber::suspend();

    return 3;
});

$results = $scheduler->run();
$items = $results[$parseId];
$count = $results[$metricsId];
```

### Agendamento durante execução

```php
$scheduler->schedule(static function () use ($scheduler): void {
    // A nova tarefa entra no fim da fila.
    $scheduler->schedule(static function (): void {
        echo "child\n";
    });

    echo "parent\n";
});

$scheduler->run();
```

### Tratamento de falha

```php
try {
    $scheduler->schedule(static function (): never {
        throw new RuntimeException('Task failed.');
    });

    $scheduler->run();
} catch (RuntimeException $exception) {
    // As tarefas ainda pendentes foram descartadas.
    error_log($exception->getMessage());
}
```

### Limite de capacidade

```php
use OverflowException;

$scheduler = new FiberScheduler(maxTasks: 2);

$scheduler->schedule(static fn (): int => 1);
$scheduler->schedule(static fn (): int => 2);

try {
    $scheduler->schedule(static fn (): int => 3);
} catch (OverflowException) {
    // Capacidade atingida.
}
```

## Guia completo da API

### `FiberScheduler`

| API | Retorno | Comportamento e exceptions |
|---|---|---|
| `__construct(int $maxTasks = 1000)` | objeto | Valor menor que 1 lança `InvalidArgumentException`. |
| `schedule(callable $task)` | `int` | Enfileira callable sem argumentos e retorna ID. Capacidade atingida lança `OverflowException`. |
| `run()` | `array<int,mixed>` | Executa até a fila esvaziar. Execução aninhada lança `LogicException`; exceptions das tarefas são propagadas. |
| `pendingCount()` | `int` | Conta tarefas enfileiradas, suspensas e atualmente executando. |
| `isEmpty()` | `bool` | Indica ausência de tarefas pendentes. |

## Fluxo interno

```mermaid
stateDiagram-v2
    [*] --> Queued: schedule
    Queued --> Running: start
    Running --> Suspended: Fiber::suspend
    Suspended --> Queued: enqueue O(1)
    Queued --> Running: resume
    Running --> Terminated: return
    Terminated --> Result: getReturn
    Running --> Failed: throw
    Failed --> Cleared: discard queue
```

## Problemas que resolve

Sem o scheduler, o chamador precisa manter Fibers, checar estados e decidir a ordem de resume. Com ele, tarefas cooperativas seguem uma fila previsível e resultados possuem IDs estáveis.

## Comparações

| Solução | Escopo |
|---|---|
| Fiber nativa | Primitiva de suspensão; controle totalmente manual. |
| FiberScheduler | Round-robin mínimo, sem I/O, timers ou promises. |
| Revolt EventLoop | Event loop completo usado por bibliotecas assíncronas. |
| Amp / ReactPHP | Ecossistemas de concorrência assíncrona, streams, rede e promises/futures. |
| ext-parallel / processos | Paralelismo real, outro modelo de execução e comunicação. |

## Performance

Não há benchmark publicado. `schedule()`, dequeue e requeue são O(1). A classe não ordena resultados nem cria objetos de contexto. Memória cresce linearmente com tarefas pendentes e com o estado capturado pelas closures. O custo de troca de Fiber pertence à implementação nativa.

## Melhores práticas

- coloque `Fiber::suspend()` em pontos explícitos e seguros;
- mantenha trechos entre yields curtos;
- não execute chamadas bloqueantes esperando concorrência;
- use os IDs retornados para acessar resultados;
- limite `maxTasks` conforme a origem da carga;
- capture exceptions ao redor de `run()`;
- não chame `run()` dentro de uma tarefa do mesmo scheduler.

## FAQ

**Isso torna HTTP ou banco de dados assíncronos?** Não.

**As tarefas executam em paralelo?** Não, todas usam a mesma thread.

**Posso passar argumentos à callable?** Não diretamente; capture valores com closures.

**Qual valor é enviado ao retomar uma Fiber?** Nenhum; `resume()` é chamado sem valor.

**Posso cancelar uma tarefa?** Não.

## Troubleshooting

| Mensagem | Causa | Solução |
|---|---|---|
| `Maximum task count must be greater than zero` | Capacidade inválida | Use inteiro positivo. |
| `scheduler capacity ... was reached` | Tarefas demais antes de conclusão | Execute a fila ou reduza produção. |
| `scheduler is already running` | `run()` aninhado | Agende a tarefa e deixe o loop atual executá-la. |
| Tarefas não alternam | A callable não suspende | Adicione yields explícitos com `Fiber::suspend()`. |
| Aplicação continua bloqueando | I/O ou CPU bloqueante | Use event loop/worker apropriado. |

## Oportunidades de melhoria

- Valores de resume exigiriam uma política explícita de mensagens por tarefa.
- Cancelamento precisaria definir cleanup de Fibers suspensas e recursos capturados.
- Um adapter futuro para event loop deve ficar em pacote separado para preservar leveza.
- Benchmarks comparando controle manual e scheduler quantificariam o overhead.
- Generics mais expressivos em ferramentas estáticas poderiam tipar resultados por tarefa.

## Contribuição

Execute `composer check`. Mudanças devem preservar O(1) na fila, propagação de exceptions e cobertura integral do scheduler.

## Licença

[MIT](../../LICENSE)
