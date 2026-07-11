# PayLite

API RESTful de um sistema de pagamentos simples. Usuários comuns e lojistas possuem
carteira; usuários transferem dinheiro entre si e para lojistas; lojistas só recebem.

Os requisitos de produto ficam em `docs-dev/` (diretório local, fora do git — **nunca**
commitar seu conteúdo nem citar sua origem em arquivos versionados).

## Stack

- PHP 8.2+ / Hyperf 3.2 (coroutines via Swoole) — servidor em `bin/hyperf.php`
- PostgreSQL 16, Redis 7, RabbitMQ 3.13 (docker-compose)
- PHPUnit (co-phpunit), Mockery, PHPStan, PHP-CS-Fixer, PHPMD

## Comandos

Tudo roda **dentro do container** (`make up` primeiro). Nunca rode php/composer no host.

| Comando | O que faz |
|---|---|
| `make up` / `make down` | sobe/derruba os serviços |
| `make test` | PHPUnit (todas as suites) |
| `make quality` | cs-check + phpstan + phpmd + testes (rode antes de todo commit) |
| `make cs` / `make cs-check` | corrige / verifica estilo |
| `make stan` / `make md` | PHPStan level 8 / PHPMD |
| `make composer cmd="..."` | composer no container |

## Regras inegociáveis

Consulte a skill correspondente **antes** de agir — elas são a fonte de verdade:

1. **Antes de escrever qualquer código PHP** → skill `php-standards` (PSR-12, SOLID,
   camadas, value objects, tratamento de erros, regras Hyperf).
2. **Todo código de produção nasce de um teste que falha** → skill `tdd`
   (red-green-refactor, Unit vs Feature, o que mockar).
3. **Toda mudança em branch `feature/*` com conventional commit** → skill `git-workflow`
   (Git Flow, rebase workflow, PRs). Nunca commite direto em `master` ou `develop`.

Resumo do que nunca fazer no código:

- Arquivo PHP sem `declare(strict_types=1)`.
- Lógica de negócio em Controller — controller só traduz HTTP ⇄ caso de uso.
- Dependência resolvida por mágica (`make()`, facades, container acessado direto) em
  código de domínio — sempre injeção via construtor, preferindo interfaces.
- Dinheiro em `float` — use inteiro em centavos.
- Marcar tarefa como concluída com `make quality` vermelho.

## Idioma

Código, identificadores e mensagens de commit em **inglês**. Documentação (README, docs,
skills) em **português**.
