---
name: php-standards
description: Padrões de código PHP do PayLite — PSRs, SOLID, clean code, arquitetura em camadas, design patterns, tratamento de erros e regras Hyperf. Consultar SEMPRE antes de escrever ou revisar qualquer código PHP de produção.
---

# Padrões de código PHP — PayLite

O estilo mecânico (indentação, imports, espaçamento) é responsabilidade do PHP-CS-Fixer —
não gaste atenção com isso. Esta skill cobre o que a ferramenta **não** pega.

## PSRs

- **PSR-1/PSR-12**: uma classe por arquivo, `declare(strict_types=1)` logo após `<?php`,
  nomes de classe em `StudlyCaps`, métodos em `camelCase`.
- **PSR-4**: namespace `App\` mapeia `app/`. O caminho do arquivo espelha o namespace.
- Interfaces terminam em `Interface` (ex.: `TransferAuthorizerInterface`); exceções em
  `Exception`; nada de prefixo `I` ou sufixo `Impl`.

## SOLID aplicado a este projeto

- **SRP**: cada service/use case resolve UM caso de uso (`TransferMoneyService`, não
  `WalletManager` genérico). Se a classe tem "e" na descrição, divida.
- **OCP + DIP**: dependa de abstrações nas bordas do domínio. Serviços externos entram
  por interface — ex.: `TransferAuthorizerInterface` (mock GET authorize) e
  `NotifierInterface` (mock POST notify) — com implementações HTTP em camada de
  infraestrutura. O binding fica em `config/dependencies.php`.
- **LSP**: implementações de uma interface devem ser intercambiáveis sem `instanceof`
  no consumidor.
- **ISP**: interfaces pequenas e focadas no consumidor. Um repository de leitura não
  precisa expor escrita.

## Clean code

- Nomes revelam intenção: `payerWallet`, não `w1`. Sem abreviações.
- Funções curtas, um nível de abstração por função, early return em vez de `else`
  aninhado.
- Comentário só para restrição que o código não consegue expressar. Código autoexplicativo
  não leva comentário.
- **Dinheiro nunca em float**: value object `Money` guardando inteiro em centavos.
- Value objects para conceitos com regra própria: `Cpf`/`Document` (validação e
  unicidade de formato), `Money`, `Email`. Imutáveis (`readonly`), validam no construtor.
- Sem números/strings mágicos: constantes ou enums (`UserType::COMMON`,
  `UserType::MERCHANT`).

## Arquitetura em camadas

Fluxo de uma request:

```
Controller (HTTP puro: valida forma, monta DTO, chama service, formata resposta)
   → Service / UseCase (regra de negócio, orquestra transação)
      → Repository (interface no domínio, implementação com Hyperf DB/Model)
      → Gateways externos (interface no domínio, implementação HTTP/AMQP)
```

- **Controller** não conhece Model nem regra de negócio. Recebe request, valida
  estrutura, delega, retorna resposta.
- **DTOs** imutáveis para entrada (`TransferInput`) e saída — nunca passar array
  associativo cru entre camadas.
- **Service** é dono da transação de banco (`Db::transaction` encapsulado) e das regras:
  saldo suficiente, lojista não envia, autorização externa.
- **Repository**: interface por agregado (`WalletRepositoryInterface`), implementação
  em `app/Repository/`. Models Eloquent não vazam para fora da camada de persistência
  quando evitável — prefira retornar entidades/DTOs.
- **Eventos/Listeners** para efeitos colaterais (notificação após transferência) —
  desacopla e permite reprocessar via fila (RabbitMQ) quando o notificador estiver fora.

## Design patterns preferidos

Use pattern quando o problema pedir, e nomeie pelo pattern:

- **Repository** — persistência atrás de interface.
- **Strategy** — variações de regra por tipo de usuário (ex.: política de quem pode
  enviar), em vez de `if ($user->isMerchant())` espalhado.
- **Factory** — construção de agregados/VOs com validação não trivial.
- **Event/Listener (Observer)** — notificação e demais efeitos pós-transferência.
- **Adapter/Gateway** — clientes HTTP dos serviços externos por trás das interfaces do
  domínio.

## Tratamento de erros

- Exceções de domínio específicas (`InsufficientBalanceException`,
  `TransferNotAuthorizedException`, `MerchantCannotTransferException`) estendendo uma
  `DomainException` base do projeto, carregando status HTTP sugerido.
- Um `ExceptionHandler` central mapeia exceção → resposta JSON padronizada
  (`{"error": {"code": ..., "message": ...}}`). Controller nunca faz try/catch de regra
  de negócio.
- Nunca vazar exceção crua, stack trace ou SQL na resposta HTTP.
- Falha do autorizador externo = transferência negada (fail-closed). Falha do
  notificador ≠ falha da transferência (retry assíncrono via fila).

## Regras Hyperf / coroutines

- Evitar métodos mágicos e atalhos prontos do framework: injeção **sempre via
  construtor** com promoted properties; proibido `#[Inject]` em domínio, `make()`,
  acesso direto ao container e chamadas estáticas mágicas fora da camada de
  infraestrutura.
- **Coroutine-safety**: workers Swoole vivem entre requests. Proibido estado estático
  mutável e propriedades de serviço que mudem por request — serviços são stateless.
- Cliente HTTP externo: `hyperf/guzzle` com pool + timeouts explícitos. Sempre defina
  timeout; nunca confie no default infinito.
- Config via `config/` + `.env` (funções `env()` só dentro de arquivos de config).
