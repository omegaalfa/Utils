# Debug

> Inspeção de valores opt-in para desenvolvimento, sem contaminar o autoload global.

[← Portal](../../README.md)

## Introdução

O módulo Debug centraliza dumps pequenos para scripts, CLIs e micro-aplicações. A classe namespaced está sempre disponível pelo PSR-4; os atalhos globais só existem quando o projeto importa explicitamente `helpers/debug.php`.

Use durante desenvolvimento e diagnóstico local. Remova chamadas que encerram o processo antes de publicar código de produção.

## Recursos

- ✅ `Debug::dump()` exibe e continua;
- ✅ `Debug::ss()` preserva o atalho curto;
- ✅ `Debug::dd()` exibe e encerra com status 1;
- ✅ múltiplos valores e tipos nativos;
- ✅ cores ANSI automáticas em terminais interativos;
- ✅ saída HTML estilizada no navegador;
- ✅ suporte ao padrão `NO_COLOR`;
- ✅ apresentação `<pre>` fora de CLI;
- ✅ aliases globais opcionais `dump_debug()`, `ss()` e `dd()`;
- ✅ zero dependências;
- ❌ sem toolbar, servidor de dump ou coleta remota;
- ❌ sem redaction automática de segredos.

## Uso recomendado

```php
use Omegaalfa\Utils\Debug\Debug;

Debug::dump($user, $request); // continua
Debug::ss($payload);          // continua
Debug::dd($response);         // encerra
```

A classe namespaced evita colisões e deve ser a escolha padrão em bibliotecas.

## Atalhos globais opcionais

```php
require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/vendor/omegaalfa/utils/helpers/debug.php';

ss($payload); // dump sem encerrar
dd($payload); // dump and die
```

O arquivo opcional usa `function_exists()` para não redeclarar funções existentes. Como a primeira implementação carregada prevalece, importe-o somente em aplicações que controlam seu bootstrap.

## API

| API | Retorno | Comportamento |
|---|---|---|
| `Debug::dump(mixed ...$values)` | `void` | Exibe todos os valores e continua. |
| `Debug::ss(mixed ...$values)` | `void` | Alias curto de `dump()`. |
| `Debug::dd(mixed ...$values)` | `never` | Exibe valores e executa `exit(1)`. |
| `dump_debug(...)` | `void` | Alias global opcional de `Debug::dump()`. |
| `ss(...)` | `void` | Alias global opcional de `Debug::ss()`. |
| `dd(...)` | `never` | Alias global opcional de `Debug::dd()`. |

## Segurança e performance

O módulo delega a `var_dump()` e colore somente cabeçalhos, sem analisar ou copiar a estrutura completa do dump. Em CLI, ANSI é habilitado apenas quando `STDOUT` é um terminal interativo. Pipes, arquivos e logs permanecem limpos.

Use `NO_COLOR=1` para desabilitar cores ou `OMEGA_DEBUG_COLORS=1` para forçá-las em uma ferramenta CLI:

```bash
NO_COLOR=1 php console.php
OMEGA_DEBUG_COLORS=1 php console.php
```

No navegador, o conteúdo é envolvido por um bloco `<pre>` estilizado. Não faça dump de senhas, tokens, cookies, dados pessoais ou payloads extensos. Dumps podem expor informações e consumir memória proporcional ao valor inspecionado.

## Boas práticas

- prefira `Debug::dump()` ou `Debug::ss()` durante investigação;
- use `dd()` somente quando interromper a execução for intencional;
- não deixe dumps em commits de produção;
- não habilite os aliases globais em bibliotecas reutilizáveis;
- aplique redaction antes de inspecionar dados sensíveis;
- defina `NO_COLOR` em ambientes que não aceitam ANSI.

## FAQ

**`ss()` encerra a aplicação?** Não. Ele apenas exibe e continua.

**`dd()` permanece disponível?** Sim, pela classe ou pelo arquivo global opt-in.

**Por que os aliases não estão no Composer autoload?** Para evitar funções globais e comportamento dependente da ordem de carregamento em projetos que não escolheram o módulo.

## Contribuição e licença

Execute `composer check`. Alterações devem preservar o comportamento opt-in e não adicionar dependências. Licença [MIT](../../LICENSE).
