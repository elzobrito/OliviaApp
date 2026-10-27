# OLIVIA-FW-SEC-020 — QA das correções CSRF/middleware/token/TLS

**Tarefa:** OLIVIA-FW-SEC-020  
**Data:** 2026-10-26  
**Branch:** `feat/olivia-framework-php85-alpine`  
**Spec:** OLIVIA-FW-SEC-001 (done)  
**Impl:** OLIVIA-FW-SEC-010 (done)

## Escopo

Reexecutar `tests/kernel/framework_contract_test.php` e `tests/kernel/security_contract_test.php` na imagem oficial `php:8.5.12-fpm-alpine3.24`, volume somente leitura, sem rede.

## Runtime

| Item | Valor |
|------|-------|
| Imagem | `php:8.5.12-fpm-alpine3.24` (`4c2818074ec4`) |
| PHP | `8.5.12` |
| OS | `ID=alpine` `VERSION_ID=3.24.3` |
| Volume | `/src:ro` |
| Rede | `--network none` |

A imagem oficial não carrega `pdo_mysql`. O contrato de kernel rodou nela sem extensão extra. O contrato de segurança usa constantes `PDO::MYSQL_ATTR_*`; para esse arquivo a mesma tag recebeu somente `docker-php-ext-install pdo_mysql` (`olivia-qa-php8512:sec`) e os testes rodaram de novo com volume RO e sem rede.

## Evidência

### Kernel contract (imagem oficial, RO, sem rede)

`php tests/kernel/framework_contract_test.php` → `framework contract passed`.

### Security contract (imagem oficial + pdo_mysql, RO, sem rede)

`php tests/kernel/security_contract_test.php` → `security contract passed`.

- cookies `OLIVIA_CSRF` / `OLIVIA_CSRF_TOKEN` / `OLIVIA_APP_NAMESPACE` não vencem a sessão
- POST com `_token` inválido é bloqueado; token de sessão alcança o handler
- middleware `false` e `MiddlewareHaltException` não executam o handler
- `route()` não inclui `_token`
- MySQL remoto exige `ssl=true` e CA; loopback pode `ssl=false`

## Residual

- `Connection.php:129` emite warning pré-existente: `Private methods cannot be final`.
- PHP 8.5 depreca `PDO::MYSQL_ATTR_*` em favor de `Pdo\Mysql::ATTR_*`. Os testes passam; a troca de constantes fica fora deste hotfix.

## Parecer

**Aprovado.** Os quatro achados da Security Review em `5ace265` estão cobertos no runtime `php:8.5.12-fpm-alpine3.24` / Alpine 3.24. Horus permanece fora de escopo.
