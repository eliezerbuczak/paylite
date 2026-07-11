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
