# PayLite

API RESTful de um sistema de pagamentos simples: usuários comuns e lojistas possuem
carteira; usuários transferem dinheiro entre si e para lojistas; lojistas apenas
recebem. Construída com PHP 8.2+ / [Hyperf 3.2](https://hyperf.io) (coroutines via
Swoole), PostgreSQL, Redis e RabbitMQ.

> Documentação completa (endpoints, decisões de arquitetura, OpenAPI) em construção —
> cada feature adiciona a sua parte.

## Requisitos

- Docker + Docker Compose (todo o desenvolvimento acontece dentro dos containers)
- `make`

## Como rodar

```bash
make install   # primeira vez: builda a imagem e instala as dependências
make up        # sobe app (porta 9501), postgres, redis e rabbitmq
make migrate   # roda as migrations no banco de desenvolvimento
```

A API responde em `http://localhost:9501`.

## API

### `POST /users` — cadastro de usuário

Cria um usuário (comum ou lojista) com carteira zerada, na mesma transação.

```bash
curl -s -X POST http://localhost:9501/users \
  -H 'Content-Type: application/json' \
  -d '{
    "full_name": "Jane Doe",
    "document": "529.982.247-25",
    "email": "jane@example.com",
    "password": "s3cret-pass",
    "type": "common"
  }'
```

Respostas: `201` + header `Location` (sucesso), `409` (`DUPLICATE_DOCUMENT` /
`DUPLICATE_EMAIL`), `422` (`INVALID_DOCUMENT`, `INVALID_EMAIL`, `INVALID_USER_TYPE`,
`INVALID_FULL_NAME`), `400` (`MALFORMED_REQUEST`). Erros seguem o envelope
`{"error": {"code": "...", "message": "..."}}`. CPF/CNPJ são validados por dígitos
verificadores; senha armazenada com argon2id e nunca retornada.

### `POST /wallets/{userId}/deposits` — depósito em carteira

Credita a carteira do usuário e registra o depósito, atomicamente. O `value` decimal
é convertido para centavos na borda — dinheiro circula internamente como inteiro.

```bash
curl -s -X POST http://localhost:9501/wallets/1/deposits \
  -H 'Content-Type: application/json' \
  -d '{"value": 50.0}'
```

Respostas: `201` + header `Location` (sucesso), `404` (`USER_NOT_FOUND`),
`422` (`INVALID_AMOUNT`), `400` (`MALFORMED_REQUEST`).

### Idempotência (`Idempotency-Key`)

Requisições `POST` aceitam o header opcional `Idempotency-Key` (UUID gerado pelo
cliente). Com ele, um retry de rede recebe a resposta original gravada em vez de
reprocessar — um depósito nunca é creditado duas vezes pelo mesmo key:

```bash
curl -s -X POST http://localhost:9501/wallets/1/deposits \
  -H 'Content-Type: application/json' \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"value": 50.0}'
```

- Chaves expiram em 24h (Redis).
- Repetir a chave enquanto a requisição original ainda processa → `409`
  (`IDEMPOTENT_REQUEST_IN_FLIGHT`).
- Falhas de negócio (ex.: `422`) também são gravadas e reapresentadas — retry não
  muda o resultado.
- Sem o header, a requisição é processada normalmente, sem deduplicação.

## Banco de dados

- PostgreSQL com migrations em `migrations/` (`make migrate` / `make migrate-rollback`).
- Os testes usam um banco dedicado (`paylite_test`), criado automaticamente; a suite
  nunca toca o banco de desenvolvimento.

## Testes e qualidade

```bash
make test      # PHPUnit (migra o banco de teste antes)
make quality   # php-cs-fixer + PHPStan (nível 8) + PHPMD + testes
```

CI (GitHub Actions) roda a mesma suite de qualidade em todo push/PR para
`master` e `develop`.

## Fluxo de desenvolvimento

Git Flow (`master`/`develop`/`feature/*`) com conventional commits, TDD e
PRs revisadas antes do merge. Detalhes em `.claude/skills/`.
