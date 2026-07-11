---
name: git-workflow
description: Fluxo git do PayLite — Git Flow completo (master/develop/feature/release/hotfix), conventional commits em inglês e rebase workflow para PRs. Consultar SEMPRE antes de criar branch, commitar, abrir PR ou fazer merge.
---

# Git workflow — PayLite

Git Flow completo + conventional commits + histórico linear via rebase.

## Branches (Git Flow)

| Branch | Origem | Destino | Papel |
|---|---|---|---|
| `master` | — | — | produção; só recebe merge de `release/*` e `hotfix/*`; cada merge ganha tag `vX.Y.Z` |
| `develop` | `master` | — | integração contínua das features |
| `feature/<slug>` | `develop` | `develop` | toda mudança de código/ferramental |
| `release/vX.Y.Z` | `develop` | `master` + `develop` | estabilização de versão |
| `hotfix/<slug>` | `master` | `master` + `develop` | correção urgente em produção |

Regras:
- **Nunca** commitar direto em `master` ou `develop`.
- Slug do branch em inglês, kebab-case, curto: `feature/transfer-endpoint`,
  `feature/wallet-model`.
- Uma feature = um escopo pequeno e mergeável. Se está crescendo, divida.

## Conventional commits (em inglês)

Formato: `type(scope): message` — mensagem no imperativo, minúscula, sem ponto final.

- `feat` — comportamento novo de produção
- `fix` — correção de bug
- `test` — só testes (no TDD, o teste pode vir em commit `test:` antes do `feat:`, ou
  junto no mesmo `feat:` — escolha pelo que deixa o histórico mais legível)
- `refactor` — mudança interna sem alterar comportamento
- `chore` — ferramental, dependências, configs
- `docs` — documentação
- `ci` — pipelines
- `perf`, `style`, `build` — quando couber

Scope opcional e curto (`feat(transfer): ...`, `chore(docker): ...`). Breaking change:
`!` após o type e rodapé `BREAKING CHANGE:`.

Todo commit deve compilar e passar `make quality` — não commite estado quebrado.

## Rebase workflow

Objetivo: histórico linear, sem merge commits de sincronização.

- Para atualizar a feature com `develop`: `git rebase develop` **na feature**. Proibido
  `git merge develop` para dentro da feature.
- `git rebase -i` não funciona neste ambiente (sem editor interativo). Para arrumar
  commits: `git commit --fixup <sha>` durante o trabalho e
  `GIT_SEQUENCE_EDITOR=true git rebase --autosquash develop` antes do PR; ou
  `git reset --soft` + recommit quando for simples.
- Push de branch rebased: `git push --force-with-lease` (nunca `--force` seco). Só em
  branches `feature/*` seus — jamais reescrever `master`/`develop`.

## Pull requests

- PR de `feature/*` → `develop` via `gh pr create --base develop` (quando houver remoto
  GitHub; sem remoto, simule: rebase + `git checkout develop && git merge --ff-only feature/...`).
- Antes de abrir/mergear o PR, na feature:
  1. `make quality` verde;
  2. `git rebase develop` (resolver conflitos aqui, nunca no merge);
  3. commits limpos (fixups já esmagados).
- Merge preservando linearidade: fast-forward (`--ff-only`) quando os commits da feature
  são bons individualmente; squash quando a feature é um único assunto com histórico
  ruidoso.
- Corpo do PR: o que mudou, por quê, como verificar. Terminar com:
  `🤖 Generated with [Claude Code](https://claude.com/claude-code)`.

## Release / hotfix (resumo)

- `release/vX.Y.Z` de `develop`: só fixes/docs de estabilização → merge em `master`
  (tag `vX.Y.Z`, dispara o workflow de release) e de volta em `develop`.
- `hotfix/<slug>` de `master`: fix + teste → merge em `master` (tag patch) e em
  `develop`.
