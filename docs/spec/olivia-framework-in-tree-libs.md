# Incorporar libs Olivia no olivia-framework

**Tarefa:** OLIVIA-FW-030  
**Branch:** `feat/olivia-framework-php85-alpine`  
**Runtime obrigatório:** `php:8.5.12-fpm-alpine3.24`  
**Fora de escopo:** Horus; Packagist; reescrever SHA `57eb048`

## Problema

CVE-2026-109618 e CVE-2026-109619 são RCE no runtime PHP. O patch é PHP 8.5.12 (também 8.4.27 / 8.3.36 / 8.2.35). O framework já pina a imagem em 8.5.12, mas as libs Olivia ainda declaram PHP 7.1/7.2/8.0/8.1, runtimes sem esse patch.

| Pacote | PHP no composer.json |
| --- | --- |
| `elzobrito/olivia-force-download` | `^7.2.5\|^8.2` |
| `elzobrito/olivia-router` | `^7.1\|^8.2.4` |
| `elzobrito/olivia-pagination` | `^7.1\|^8.2.4` |
| `elzobrito/olivia-pdf` | `^7.1 \|\| ^8.0` |
| `elzobrito/olivia-upload-file` | `^7.2.5\|^8.0` |
| `elzobrito/olivia-databaselibrary` | `^7.1\|^8.2.4` |
| `elzobrito/olivia-cache` (transitiva) | `^7.2.5\|^8.0` |

Há ainda desvio de router: OliviaApp lock 2.3 (`5ec6c7a`); ATRIO `dev-master as 2.4.0` @ `27a3f81`.

## Decisão

Incorporar as sete libs **in-tree** em `elzobrito/olivia-framework`. Um único `require.php`: `>=8.2 <8.6`. Imagem continua `php:8.5.12-fpm-alpine3.24`.

Namespaces originais permanecem. Consumidores não reescrevem `use`.

## Layout

```text
src/router/      OliviaRouter\          ← ATRIO vendor @ 27a3f81 (2.4, inclui ContextStore*)
src/pagination/  OliviaPagination\
src/pdf/         Oliviapdf\
src/upload/      OliviaUpload\
src/download/    OliviaDownload\
src/database/    OliviaDatabaseLibrary\ (ADatabase, Connection, QueryBuilder, Model, PEM)
src/database-model/ OliviaDatabaseModel\
src/database-config/ OliviaDatabaseConfig\  (stub; ATRIO mapeia o próprio config/ e vence)
src/cache/       OliviaCache\
```

Não copiar `vendor/` aninhado, testes nem `public_html` da databaselibrary.

## Composer

`require` do framework:

- `php`: `>=8.2 <8.6`
- `ramsey/uuid`: `^4.0` (Config + Upload)
- `fpdf/fpdf`: `^1.82` (Pdf)
- `ext-fileinfo`: `*` (Download)

Sem `elzobrito/olivia-*`.

`replace` (self.version):

- `elzobrito/olivia-router`
- `elzobrito/olivia-pagination`
- `elzobrito/olivia-pdf`
- `elzobrito/olivia-upload-file`
- `elzobrito/olivia-force-download`
- `elzobrito/olivia-databaselibrary`
- `elzobrito/olivia-cache`

Versão do pacote: `0.2.0` (alias/`COMPOSER_ROOT_VERSION`).

## ATRIO (ATRIO-OLIVIA-FW-040)

Depois do push desta branch:

1. Remover `elzobrito/olivia-router`, `olivia-databaselibrary`, `olivia-force-download` de `require`.
2. Manter `elzobrito/olivia-framework`.
3. Atualizar o bloco `type: package` para SHA novo, versão `0.2.0` e autoload de todos os namespaces in-tree (`OliviaLib`, `OliviaRouter`, `OliviaPagination`, `Oliviapdf`, `OliviaUpload`, `OliviaDownload`, `OliviaDatabaseLibrary`, `OliviaDatabaseModel`, `OliviaDatabaseConfig`, `OliviaCache`).
4. Remover o mapeamento ATRIO `OliviaCache\\` → nested vendor da databaselibrary.
5. `OliviaDatabaseConfig\\` → `atrio/config/` permanece (Hub vence).
6. Ajustar testes que leem `vendor/elzobrito/olivia-router/src/...`.
7. Inventário 185 e imagem `8.5.12-fpm-alpine3.24` inalterados.
8. Sem path local. Sem merge em `main` sem aceite AKS.

O driver VCS do GitHub ainda identifica OliviaApp como `elzobrito/olivia-app` via `master`. Continua `type: package`.

## Teste de kernel

Além do contrato atual:

1. `require` não contém chaves `elzobrito/olivia-*`.
2. `replace` contém as sete libs.
3. `src/router/ContextStoreInterface.php` existe (marca 2.4 / 27a3f81).
4. Autoload instancia `OliviaRouter\Router`, `OliviaDatabaseLibrary\ADatabase` (via `OliviaLib\Database` / existência de classe), `OliviaDownload\Download`.
5. `src/` não contém `HUB_OIDC` nem `UserSessionService`.

## Dockerfile / CI

`COPY src ./src`. Lint inclui `src`. Smoke da imagem inalterado (PHP 8.5.12, Alpine 3.24).

## Fora de escopo

- Incorporar `fpdf/fpdf` ou `ramsey/uuid` (terceiros).
- Publicar no Packagist.
- Horus.
- Renomear `master` nesta entrega.
