# OLIVIA-FW-050 — QA in-tree libs PHP 8.5.12 / Alpine 3.24

**Tarefa:** OLIVIA-FW-050  
**Data:** 2026-10-26  
**Branch:** `feat/olivia-framework-php85-alpine`

## Escopo

Reexecutar o contrato do kernel após incorporar as sete libs Olivia em `src/`, na imagem oficial `php:8.5.12-fpm-alpine3.24`, volume somente leitura e sem rede.

## Evidência

### Runtime oficial (sem rede)

PHP `8.5.12`, `ID=alpine`, `VERSION_ID=3.24.3`.

### Kernel contract (volume RO, sem rede)

`php tests/kernel/framework_contract_test.php` → `framework contract passed`.

- `require` sem `elzobrito/olivia-*`
- `replace` das sete libs
- `OliviaRouter\ContextStoreInterface` (router 2.4 / 27a3f81)
- classes in-tree autoloadam de `src/`
- `ramsey/uuid` e `fpdf/fpdf` permanecem terceiros
- imagem construída reporta PHP `8.5.12`

## Parecer

**Aprovado.** O pin único `>=8.2 <8.6` com runtime `php:8.5.12-fpm-alpine3.24` cobre kernel e libs. As CVEs CVE-2026-109618 e CVE-2026-109619 são do runtime PHP; a imagem oficial 8.5.12 é o patch.
