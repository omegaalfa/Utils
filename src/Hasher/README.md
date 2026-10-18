# Hasher

> Hash de senhas e comparações HMAC em tempo constante.

[← Portal](../../README.md)

## Introdução

Hasher embrulha `password_hash`/`password_verify`, geração e verificação de HMAC e comparação segura de strings — sempre com `hash_equals()` quando o valor é comparado em contexto de segurança.

Use em autenticação, assinatura de webhooks e validação de tokens. Não use para criptografia reversível, armazenamento de chaves ou hashing de alta vazão sem revisão; esses casos pedem biblioteca especializada.

## Recursos

- ✅ `PASSWORD_DEFAULT` ou `PASSWORD_ARGON2ID` quando disponível;
- ✅ `passwordNeedsRehash()` após mudança de parâmetros;
- ✅ HMAC em hex ou binário;
- ✅ verificação de assinatura com `hash_equals()`;
- ✅ comparação de strings em tempo constante;
- ✅ rejeição explícita de chave HMAC vazia;
- ✅ zero dependências.

## Início rápido

```php
use Omegaalfa\Utils\Hasher\Hasher;

$hash = Hasher::passwordHash('segredo');
Hasher::passwordVerify('segredo', $hash); // true

$signature = Hasher::hmac($payload, $key);
Hasher::hmacVerify($payload, $key, $signature); // true
```

PHP 8.4+.

## Estratégia

Senha segue o fluxo nativo: salt embutido no hash, verificação por `password_verify()` e rehash quando os parâmetros mudam. HMAC e `equals()` comparam sempre em tempo constante — inclusive a verificação de assinatura, que recalcula o HMAC esperado antes de comparar.

```mermaid
flowchart LR
    A[senha] -->|passwordHash| B[hash]
    A -->|passwordVerify| B
    C[payload + key] -->|hmac| D[assinatura]
    C + D -->|hmacVerify| E{hash_equals}
    E -->|sim| F[aceita]
    E -->|não| X[rejeita]
```

## Casos de uso

### Senhas com Argon2id

```php
if (!defined('PASSWORD_ARGON2ID')) {
    // build do PHP sem Argon2 — use PASSWORD_DEFAULT
}

$hash = Hasher::passwordHash($password, PASSWORD_ARGON2ID, [
    'memory_cost' => 65536,
    'time_cost' => 4,
    'threads' => 1,
]);
```

### Rehash transparente no login

```php
if (!Hasher::passwordVerify($input, $stored)) {
    throw new AuthenticationException('Credenciais inválidas.');
}

if (Hasher::passwordNeedsRehash($stored, PASSWORD_DEFAULT, ['cost' => 12])) {
    $repository->save($user, Hasher::passwordHash($input, PASSWORD_DEFAULT, ['cost' => 12]));
}
```

### Webhook assinado

```php
$expected = Hasher::hmac($body, $secret);

if (!Hasher::hmacVerify($body, $secret, $receivedSignature)) {
    throw new AuthenticationException('Assinatura inválida.');
}
```

## API

| Método | Retorna | Comportamento |
|---|---|---|
| `Hasher::passwordHash(string $password, string $algorithm = PASSWORD_DEFAULT, array $options = [])` | `string` | Lança `InvalidArgumentException` para algoritmo desconhecido. |
| `Hasher::passwordVerify(string $password, string $hash)` | `bool` | Verificação nativa em tempo constante. |
| `Hasher::passwordNeedsRehash(string $hash, string $algorithm = PASSWORD_DEFAULT, array $options = [])` | `bool` | Detecta mudança de algoritmo ou parâmetros. |
| `Hasher::hmac(string $payload, string $key, string $algorithm = 'sha256', bool $raw = false)` | `string` | Rejeita chave vazia ou com NUL e algoritmo fora de `hash_hmac_algos()`. |
| `Hasher::hmacVerify(string $payload, string $key, string $signature, string $algorithm = 'sha256', bool $raw = false)` | `bool` | Recalcula e compara com `hash_equals()`. |
| `Hasher::equals(string $known, string $given)` | `bool` | `hash_equals()` sem pré-processamento. |

## Performance e limitações

`password_hash()` é deliberadamente lento (custo configurável); não o use em loops de alto volume. HMAC e `equals()` são baratos, mas `equals()` exige strings de mesmo propósito — não compare valores truncados. Não há suporte a KDFs de arquivo, rotação de segredos ou envelope de criptografia.

## Boas práticas

- nunca compare assinaturas ou tokens com `===`;
- mantenha a verificação de senha e o rehash no mesmo caminho de código;
- escolha Argon2id com parâmetros revisados para o seu hardware;
- não logue senhas, hashes ou chaves HMAC;
- para chaves de webhook, prefira alta entropia (>= 32 bytes aleatórios).

## FAQ e troubleshooting

**`passwordHash()` retorna `false`?** Não neste contrato: algoritmo inválido vira `InvalidArgumentException` antes da chamada nativa.

**HMAC com chave vazia é permitida?** Não; é rejeitada para evitar assinaturas triviais.

**`equals()` troca argumentos?** Passe o valor esperado primeiro (`known`), mas ambos precisam ter o mesmo formato.

| Problema | Solução |
|---|---|
| `Unsupported password hashing algorithm` | Use `password_algos()` como referência do build atual. |
| `Unsupported HMAC algorithm` | Escolha entre os retornados por `hash_hmac_algos()`. |
| Login falha após subir o cost | Rehash esperado — rode `passwordNeedsRehash()` e atualize. |
| Assinatura sempre inválida em raw | Confirme que ambos os lados usam `raw: true`. |

## Oportunidades de melhoria

Rotação de segredos, verificação de política de senha e tokens opacos devem ser APIs separadas. Um encoder de hash no formato PHC customizado não deve entrar no núcleo.

## Contribuição e licença

Execute `composer check`; teste sucesso, rejeição e caminho de tempo constante. Licença [MIT](../../LICENSE).
