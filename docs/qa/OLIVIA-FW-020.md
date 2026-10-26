# OLIVIA-FW-020 — QA independente PHP 8.5.12 / Alpine 3.24

**Tarefa:** OLIVIA-FW-020  
**Revisor:** agent-qa  
**Data:** 2026-10-26  
**Árvore:** `/home/elzobrito/desenvolvimento/OliviaApp` branch `feat/olivia-framework-php85-alpine`  
**Imagem oficial:** `php:8.5.12-fpm-alpine3.24`

## Escopo

Reexecutar o contrato do kernel e o smoke da imagem obrigatória em revisão independente: volume somente leitura e sem rede. Não reexecutar o build Docker (o build de OLIVIA-FW-010 já produziu `olivia-framework:quality`).

## Evidência

### Imagem oficial (sem rede)

```text
docker run --rm --network=none php:8.5.12-fpm-alpine3.24 php -r 'echo PHP_VERSION, PHP_EOL; echo file_get_contents("/etc/os-release");'
```

Resultado:

- PHP `8.5.12`
- `ID=alpine`
- `VERSION_ID=3.24.3`
- `PRETTY_NAME="Alpine Linux v3.24"`

### Contrato do kernel na imagem oficial (volume RO, sem rede)

```text
docker run --rm --network=none \
  -v /home/elzobrito/desenvolvimento/OliviaApp:/src:ro \
  -w /src php:8.5.12-fpm-alpine3.24 \
  php tests/kernel/framework_contract_test.php
```

Resultado: `framework contract passed` (todas as asserções OK), incluindo:

- `composer.json` name `elzobrito/olivia-framework`
- PHP Composer `>=8.2 <8.6`
- `OliviaLib\\` → `lib/`
- `lib/` sem `HUB_OIDC`, `UserSessionService` ou `cpsrepositorio`
- Dockerfile pina `PHP_VERSION=8.5.12-fpm-alpine3.24`
- `/livez` registrado
- `Database::getDB()` lê `OLIVIA_DB_DSN` e rejeita credenciais no DSN

### Imagem construída nesta entrega (sem rede)

```text
docker run --rm --network=none --entrypoint php olivia-framework:quality \
  -r 'echo PHP_VERSION, PHP_EOL; echo file_get_contents("/etc/os-release");'
```

Resultado: PHP `8.5.12`, `ID=alpine`, `VERSION_ID=3.24.3`.

O build anterior também executou `composer check-platform-reqs --no-dev` com `php 8.5.12 success`.

## Recorte Hub

`lib/Config.php`, `lib/Database.php` e `lib/Route.php` não carregam serviços do ATRIO. `CommandController.php` e `Email.php` permanecem kernel. Horus fora desta entrega.

## Parecer

**Aprovado.** A entrega atende o runtime obrigatório Linux Alpine 3.24 e PHP 8.5 (imagem `php:8.5.12-fpm-alpine3.24`). O teste de kernel passou na imagem oficial, com volume somente leitura e sem rede.
