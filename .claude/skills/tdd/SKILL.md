---
name: tdd
description: Fluxo TDD do PayLite — ciclo red-green-refactor, estrutura de suites Unit/Feature, comandos de teste, convenções de nomenclatura e política de mocks. Consultar SEMPRE antes de implementar qualquer funcionalidade ou correção.
---

# TDD — PayLite

Todo código de produção nasce de um teste que falha. Sem exceção: feature nova, bug fix
(reproduza o bug num teste antes de corrigir) ou refactor (rede de testes verde antes de
mexer).

## Ciclo obrigatório

1. **Red** — escreva o menor teste que expressa o próximo comportamento. Rode e **veja
   falhar** (falhar pelo motivo certo — asserção, não erro de sintaxe/classe inexistente
   sem intenção).
2. **Green** — escreva o mínimo de produção para passar. Rode e veja passar.
3. **Refactor** — melhore nomes/estrutura com a suite verde. Rode tudo de novo.

Nunca escreva vários comportamentos de produção de uma vez e teste depois. Se você se
pegou com produção escrita sem teste vermelho antes, pare, comente/reverta e recomece
pelo teste.

## Estrutura de testes

```
test/
├── Unit/       → puro, sem IO: services, value objects, policies. Mockery p/ dependências
├── Feature/    → HTTP de ponta a ponta via Hyperf\Testing\TestCase ($this->get/post),
│                 banco Postgres real do compose
└── bootstrap.php
```

- Namespace `HyperfTest\Unit\...` e `HyperfTest\Feature\...` (autoload-dev já mapeia
  `HyperfTest\` → `test/`).
- Espelhe o namespace de produção: `App\Service\TransferMoneyService` →
  `HyperfTest\Unit\Service\TransferMoneyServiceTest`.

## Comandos (dentro do container)

```bash
make test                                          # tudo
make composer cmd="test -- --testsuite Unit"       # só unit
make composer cmd="test -- --filter TransferMoneyServiceTest"  # uma classe
make composer cmd="test:coverage"                  # com cobertura
```

O runner é `co-phpunit` (roda dentro de coroutine) — sempre pelos scripts do composer,
nunca `phpunit` direto.

## Convenções

- Um comportamento por teste; nome descreve regra de negócio:
  `test_rejects_transfer_when_payer_has_insufficient_balance`.
- Estrutura AAA (arrange-act-assert) com blocos separados por linha em branco.
- Asserção específica (`assertSame`, exceção esperada com `expectException`) — evite
  `assertTrue` genérico.
- Dados de teste por factory/helper do próprio teste, com apenas o que importa para o
  caso — nada de fixtures gigantes compartilhadas.

## Política de mocks

**Mockar** (bordas do sistema, sempre via interface do domínio):
- `TransferAuthorizerInterface` (mock do GET authorize)
- `NotifierInterface` (mock do POST notify)
- Publicação em fila/AMQP

**Não mockar**:
- Banco em testes de **Feature** — usa o Postgres do compose (transação com rollback ou
  truncate entre testes para isolamento).
- Value objects e entidades — instancie de verdade.
- Aquilo que você está testando.

Em testes de **Unit**, repositories entram como mock/stub (Mockery); em **Feature**, a
implementação real com banco real.

## Definição de pronto

Uma tarefa só está pronta quando `make quality` (estilo + phpstan + phpmd + toda a
suite) está verde. Teste falhando = tarefa em andamento, nunca "pronta com ressalva".
