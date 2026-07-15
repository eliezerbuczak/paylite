# PayLite

API RESTful de um sistema de pagamentos simples: usuários comuns e lojistas possuem
carteira; usuários transferem dinheiro entre si e para lojistas; lojistas apenas
recebem. Construída com PHP 8.2+ / [Hyperf 3.2](https://hyperf.io) (coroutines via
Swoole), PostgreSQL, Redis e RabbitMQ.

> Documentação completa (endpoints, decisões de arquitetura, OpenAPI) em construção —
> cada feature adiciona a sua parte.

## Arquitetura

A aplicação é um **monolito modular**: um único deploy e um único banco, com o código
organizado por módulos de negócio em vez de camadas técnicas globais. Cada módulo em
`app/` segue os mesmos princípios hexagonais — domínio e aplicação dependem apenas de
interfaces (ports), a infraestrutura implementa os adapters e os controllers vivem na
borda HTTP:

```
app/
├── Shared/        # exceções base, envelope de erro, middleware de idempotência,
│                  # circuit breaker, clock, outbox transacional (Shared/Outbox)
├── User/          # cadastro: entidades, VOs (Document, Email), repo, controller
├── Wallet/        # carteira e depósitos: Money, Deposit, repo, controller
├── Transfer/      # transferência: use case, autorizador externo, repo
└── Notification/  # notificação assíncrona: notifier, consumer AMQP, retry/DLQ,
                   # publisher da outbox
```

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

A notificação de uma transferência concluída viaja em duas etapas independentes,
ligadas por uma tabela (**transactional outbox**), não por um evento em memória:

```
POST /transfer
  → move o dinheiro entre wallets
  → grava outbox_events (event_type=TransferCompleted, status=pending)   ┐ mesma
  → COMMIT                                                                ┘ transação

outbox:publish (rodado periodicamente)
  → reivindica um evento pending com available_at <= now()
  → publica no RabbitMQ (exchange transfers, routing key transfer.completed)
  → confirmado?  status=published
  → falhou?      attempts++, last_error, reagenda available_at (backoff)
  → esgotou attempts?  status=failed

transfer-notifications (consumer, inalterado)
  → POST no serviço externo de notificação, idempotente, com retry/DLQ próprios
```

O fato "a transferência aconteceu" é gravado em `outbox_events` **na mesma transação**
que debita/credita as carteiras e cria a linha em `transfers` — não existe mais uma
janela entre o commit e a publicação em que o processo pode cair e perder a
notificação: se a escrita na outbox falhar, a transferência inteira é revertida junto.

- **Publicação assíncrona, fora da requisição HTTP**: `POST /transfer` nunca fala com
  o RabbitMQ; quem publica é o comando `php bin/hyperf.php outbox:publish`, pensado
  para rodar em intervalo curto (cron, supervisor, ou um processo dedicado) fora do
  ciclo de request/response.
- **Entrega at-least-once**: se o processo do publisher morrer depois de publicar mas
  antes de marcar `published`, o evento é publicado de novo na próxima execução — por
  isso o consumer de notificação (abaixo) continua precisando ser idempotente; o
  outbox resolve a janela *commit → publish*, não substitui a idempotência do
  consumer.
- **Claim concorrente sem lock longo**: `outbox:publish` reivindica um evento por vez
  com `SELECT ... FOR UPDATE SKIP LOCKED`, empurra seu `available_at` para uma janela
  de lease de 30s e libera a transação **antes** de publicar — o lock nunca fica preso
  durante a chamada de rede. Duas execuções concorrentes do comando não processam o
  mesmo evento ao mesmo tempo; no pior caso (processo morre dentro da janela de
  lease), o mesmo evento pode ser publicado duas vezes, absorvido pela idempotência
  do consumer.
- **Backoff e limite de tentativas configuráveis** (`config/autoload/outbox.php`):
  tamanho do lote (`OUTBOX_PUBLISH_BATCH_SIZE`), tentativas máximas
  (`OUTBOX_PUBLISH_MAX_ATTEMPTS`), backoff inicial e máximo em segundos
  (`OUTBOX_PUBLISH_INITIAL_BACKOFF_SECONDS`, `OUTBOX_PUBLISH_MAX_BACKOFF_SECONDS`,
  dobrando por tentativa até o teto). Esgotadas as tentativas, o evento vira
  `status=failed` — fica retido em `outbox_events` para inspeção/replay manual, nunca
  é descartado.
- **Não é Event Sourcing**: a outbox guarda um único evento por escrita de negócio,
  para entrega confiável a um consumidor externo — não é a fonte de verdade do estado
  (`wallets`/`transfers` continuam sendo), nem histórico de todas as mudanças.
- **Consumer idempotente**: entrega at-least-once pode duplicar mensagens (POST ok,
  ack perdido); uma chave `notified:{transfer_id}` no Redis garante um único envio.
- **Circuit breaker no notificador** (mesma implementação do autorizador, estado no
  Redis): circuito aberto → o consumer nem tenta o POST e estaciona a mensagem
  direto na fila de retry. Thresholds via `NOTIFIER_BREAKER_FAILURE_THRESHOLD` e
  `NOTIFIER_BREAKER_COOLDOWN_SECONDS`.
- **Retry via fila de estacionamento** (`transfer-notifications.retry`, sem
  consumidor): a falha é republicada com o contador de tentativas no header
  `x-attempts` e a mensagem original é ACKada; quando o TTL da fila (30s) expira,
  o dead-letter devolve a mensagem à fila principal. O backoff acontece no broker —
  o consumer nunca dorme, e uma notificação problemática não atrasa as demais.
- **Envelhecimento para a DLQ**: após 10 tentativas (incluindo rejeições de circuito
  aberto — uma indisponibilidade longa não acumula fila para sempre), a mensagem é
  dead-lettered para `transfer-notifications.failed`, que fica retida para inspeção
  e replay (nada a consome).

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
