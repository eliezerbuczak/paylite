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

### `POST /transfer` — transferência entre usuários

Transfere dinheiro do `payer` para o `payee`. Usuário comum transfere para qualquer
um; lojista apenas recebe. A ordem do fluxo falha barato primeiro: validações locais
e pré-checagem de saldo antes do autorizador externo; o débito/crédito acontece em
transação com re-checagem de saldo sob lock de linha (locks adquiridos em ordem
determinística para evitar deadlock).

```bash
curl -s -X POST http://localhost:9501/transfer \
  -H 'Content-Type: application/json' \
  -d '{"value": 100.0, "payer": 4, "payee": 15}'
```

Respostas:

| HTTP | `error.code` | Quando |
|---|---|---|
| `201` + `Location` | — | transferência concluída |
| `400` | `MALFORMED_REQUEST` | campos ausentes ou de tipo errado |
| `403` | `MERCHANT_CANNOT_TRANSFER` | lojista tentando enviar |
| `404` | `USER_NOT_FOUND` | payer ou payee inexistente |
| `422` | `SAME_PAYER_PAYEE` / `INVALID_AMOUNT` / `INSUFFICIENT_BALANCE` / `TRANSFER_NOT_AUTHORIZED` | regra de negócio rejeitou |
| `502` | `AUTHORIZER_UNAVAILABLE` | autorizador externo fora (timeout/5xx) |
| `503` + `Retry-After` | `SERVICE_UNAVAILABLE` | circuit breaker aberto — nem tentamos |

O autorizador externo é consultado fora da transação (fail-closed: negou ou falhou,
não transfere) e fica atrás de um **circuit breaker** com estado compartilhado no
Redis: após falhas consecutivas o circuito abre e as requisições seguintes recebem
`503` imediato com `Retry-After`, em vez de pagar o timeout do serviço morto. Após o
cooldown, uma única requisição de sonda testa o serviço (sucesso fecha o circuito;
falha reabre). Thresholds configuráveis via `AUTHORIZER_BREAKER_FAILURE_THRESHOLD` e
`AUTHORIZER_BREAKER_COOLDOWN_SECONDS`.

### Idempotência (`Idempotency-Key`)

Requisições `POST` aceitam o header opcional `Idempotency-Key` (UUID gerado pelo
cliente). Com ele, um retry de rede recebe a resposta original gravada em vez de
reprocessar — um depósito nunca é creditado, nem uma transferência executada, duas
vezes pela mesma chave:

```bash
curl -s -X POST http://localhost:9501/wallets/1/deposits \
  -H 'Content-Type: application/json' \
  -H "Idempotency-Key: $(uuidgen)" \
  -d '{"value": 50.0}'
```

- A resposta ecoa o header `Idempotency-Key`; replays trazem também
  `Idempotent-Replayed: true` para distingui-los da resposta original.
- Chaves expiram em 24h (Redis).
- Repetir a chave enquanto a requisição original ainda processa → `409`
  (`IDEMPOTENT_REQUEST_IN_FLIGHT`).
- Falhas de negócio (ex.: `422`) também são gravadas e reapresentadas — retry não
  muda o resultado.
- Sem o header, a requisição é processada normalmente, sem deduplicação.

### Notificação assíncrona

Transferência concluída dispara um evento após o commit; um listener publica a
mensagem no RabbitMQ (exchange `transfers`, fila `transfer-notifications`) e um
consumer dedicado faz o `POST` no serviço externo de notificação — que é instável
por contrato (responde `204` ou `504` aleatório). Falha de notificação **nunca**
desfaz a transferência: o dinheiro já mudou de mãos; a mensagem é retentada.

- **Consumer idempotente**: entrega at-least-once pode duplicar mensagens (POST ok,
  ack perdido); uma chave `notified:{transfer_id}` no Redis garante um único envio.
- **Circuit breaker no notificador** (mesma implementação do autorizador, estado no
  Redis): circuito aberto → o consumer nem tenta o POST e devolve a mensagem à fila
  após o cooldown informado. Thresholds via `NOTIFIER_BREAKER_FAILURE_THRESHOLD` e
  `NOTIFIER_BREAKER_COOLDOWN_SECONDS`.
- **Retry com backoff exponencial** (2s, 4s, 8s, 16s); esgotadas as tentativas, a
  mensagem é dead-lettered para `transfer-notifications.failed`, que fica retida
  para inspeção (nada a consome).
- **Trade-off aceito no MVP**: se o processo morrer entre o commit e a publicação,
  a transferência existe mas o evento se perde (janela commit→publish). A correção
  canônica — Transactional Outbox — está registrada como melhoria futura.

## Banco de dados

- PostgreSQL com migrations em `migrations/` (`make migrate` / `make migrate-rollback`).
- Os testes usam um banco dedicado (`paylite_test`), criado automaticamente; a suite
  nunca toca o banco de desenvolvimento.
- Pelo mesmo motivo, os testes usam um vhost dedicado no RabbitMQ (`testing`, criado
  por `make test`): o servidor de dev consome as filas do vhost padrão e roubaria as
  mensagens publicadas pela suite.

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
