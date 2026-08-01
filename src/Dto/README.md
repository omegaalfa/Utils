# DTO

> Base readonly mínima para transportar dados tipados entre camadas.

[← Portal](../../README.md)

## Introdução

DataTransferObject substitui arrays sem estrutura por objetos com construtor e tipos definidos pela aplicação. A base acrescenta criação por array, exportação, JSON e cópia imutável sem reflection.

Use em boundaries de controller, service, fila e integração. Não use como entidade de domínio, ORM, validador automático ou mapper recursivo.

## Recursos

- ✅ `abstract readonly class`;
- ✅ tipos impostos pelo construtor filho;
- ✅ named arguments;
- ✅ `fromArray()`;
- ✅ `toArray()`;
- ✅ `with()` imutável;
- ✅ `JsonSerializable`;
- ✅ zero reflection e dependências;
- ❌ sem validação de domínio;
- ❌ sem hidratação recursiva;
- ❌ sem atributos ou casts automáticos.

## Início rápido

```php
use Omegaalfa\Utils\Dto\DataTransferObject;

final readonly class CreateUserData extends DataTransferObject
{
    public function __construct(
        public string $name,
        public string $email,
        public int $age,
    ) {
    }
}

$data = new CreateUserData(
    name: 'Wesley',
    email: 'wesley@example.com',
    age: 45,
);

$service->create($data);
```

## Conceitos

O DTO filho deve ser `readonly` e expor propriedades públicas pelo construtor. O PHP valida tipos, argumentos ausentes e nomes desconhecidos. A base não usa reflection, setters ou estado oculto.

`toArray()` é raso: objetos internos permanecem objetos. `with()` reconstrói pelo construtor, portanto todas as invariantes do filho continuam aplicadas.

## Casos de uso

### Array de request confiável e já validado

```php
$data = CreateUserData::fromArray([
    'name' => 'Ada',
    'email' => 'ada@example.com',
    'age' => 36,
]);
```

> [!WARNING]
> `fromArray()` não valida email, faixa de idade ou autorização. Valide entrada antes.

### Cópia imutável

```php
$nextBirthday = $data->with([
    'age' => $data->age + 1,
]);
```

O objeto original não muda.

### JSON

```php
$json = json_encode($data, JSON_THROW_ON_ERROR);
```

### Camadas

```mermaid
flowchart LR
 A[HTTP/CLI input] --> B[Validar]
 B --> C[DTO]
 C --> D[Service]
 D --> E[Repository/Queue]
```

## API

| Método | Retorno | Contrato |
|---|---|---|
| `fromArray(array $data)` | `static` | Usa unpack por named arguments; errors nativos para shape/tipos inválidos. |
| `toArray()` | `array<string,mixed>` | Propriedades públicas visíveis, conversão rasa. |
| `with(array $changes)` | `static` | Cria nova instância aplicando mudanças. |
| `jsonSerialize()` | `array<string,mixed>` | Mesmo resultado de `toArray()`. |

## Problema que resolve

Arrays permitem typo de chave, campos ausentes e valores com tipos inesperados atravessarem camadas. DTOs tornam o contrato visível no construtor, analisável estaticamente e amigável a autocomplete.

## Comparações e performance

Uma classe readonly pura sem herança tem overhead mínimo e pode ser suficiente. Esta base agrega conversões comuns. Bibliotecas de DTO com reflection, attributes, casts e validation oferecem automação maior com custo e regras adicionais.

Não há benchmark. Construção usa named argument unpack nativo; `toArray()` usa `get_object_vars()`; `with()` aloca necessariamente outro objeto.

## Boas práticas

- mantenha DTO pequeno e específico por caso de uso;
- use propriedades públicas readonly;
- valide antes da construção ou no construtor;
- não coloque persistência no DTO;
- não herde DTOs concretos;
- não envie arrays de request diretamente sem allow-list;
- trate conversão aninhada explicitamente.

## FAQ e troubleshooting

**DTO valida email?** Não.

**Propriedades privadas aparecem?** O contrato esperado são propriedades públicas promovidas.

**FromArray ignora campos extras?** Não; o PHP gera Error.

**With altera original?** Não.

| Problema | Causa |
|---|---|
| Classe filha deve ser readonly | A base é readonly por design. |
| Unknown named parameter | Chave não existe no construtor. |
| TypeError | Valor incompatível com tipo nativo. |
| Objeto interno não virou array | Conversão é rasa. |

## Oportunidades de melhoria

Interfaces opcionais de mapper e validação devem ficar separadas. Conversão recursiva precisa de política para datas, enums e objetos. Generics mais ricos dependem de ferramentas estáticas. Code generation pode atender grandes projetos, mas contraria a simplicidade do núcleo.

## Contribuição e licença

Execute `composer check`; preserve ausência de reflection e imutabilidade. Licença [MIT](../../LICENSE).
