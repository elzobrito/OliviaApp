# Olivia-framework — contrato PHP 8.5.12 / Alpine 3.24

**Tarefa:** OLIVIA-FW-001  
**Repositório:** `elzobrito/OliviaApp` (público, default `master`)  
**SHA de origem:** `57eb048` (não reescrever)  
**Consumidor imediato:** CGTIC-ATRIO  
**Fora de escopo:** Horus

## Objetivo

Publicar o kernel Olivia deste repositório como pacote Composer `elzobrito/olivia-framework`, pinado no runtime obrigatório Linux Alpine 3.24 e PHP 8.5 (imagem `php:8.5.12-fpm-alpine3.24`). O ATRIO passa a depender desse pacote via VCS GitHub e deixa de copiar `CommandController` e `Email`.

O repositório continua sendo o skeleton de aplicação (MVC, `app/`, `publico/`, `index.php`) e a fonte do pacote. Não há segundo repositório.

## Pacote

| Campo | Valor |
| --- | --- |
| Composer name | `elzobrito/olivia-framework` |
| GitHub | `https://github.com/elzobrito/OliviaApp.git` |
| Branch de entrega | `feat/olivia-framework-php85-alpine` |
| Versão inicial | `0.1.0` (alias `dev-feat/olivia-framework-php85-alpine as 0.1.0`; tag `v0.1.0` depois do merge) |
| PHP (Composer) | `>=8.2 <8.6` |
| Runtime obrigatório | `php:8.5.12-fpm-alpine3.24` |
| License | MIT (já usada no README do skeleton) |

Composer `>=8.2 <8.6` cobre resolução local e o PHP 8.5.12 da imagem. Alpine 3.24 é restrição de imagem/CI, não de `composer.json`.

O pacote **não** se chama `elzobrito/olivia-app`. Esse nome sai deste `composer.json` e, no ATRIO, deixa de ser o `name` do aplicativo.

## Autoload

Kernel permanece em `lib/` (Orchestrator não apaga arquivos; mover para `src/` duplicaria classes).

```json
"autoload": {
  "psr-4": {
    "OliviaLib\\": "lib/",
    "OliviaApp\\": "app/",
    "OliviaPublico\\": "publico/"
  }
}
```

O prefixo publicado para consumidores é `OliviaLib\\` → `lib/`. `OliviaApp\\` e `OliviaPublico\\` existem para o skeleton deste repositório. Consumidores **não** devem depender de classes `OliviaApp\\` do pacote: o ATRIO já mapeia `OliviaApp\\` → `atrio/app/` e vence no autoload.

Quando o ATRIO declara o mesmo prefixo `OliviaLib\\` → `atrio/lib/`, o Composer une os diretórios. O primeiro arquivo existente vence. Depois de apagar `atrio/lib/CommandController.php` e `atrio/lib/Email.php`, essas classes caem no vendor. `Config.php`, `Database.php` e `Route.php` do Hub continuam em `atrio/lib/` e vencem sobre o pacote.

## Kernel vs aplicação vs Hub

### Kernel (neste pacote, `lib/`)

| Classe | Papel | Origem |
| --- | --- | --- |
| `OliviaLib\CommandController` | Base de controllers: CSRF, CPF, máscaras, encrypt | Byte-idêntica ao ATRIO (272 linhas). Manter. |
| `OliviaLib\Email` | Envio via PHPMailer (placeholders) | Byte-idêntica ao ATRIO (88 linhas). Manter. **Não** adicionar `phpmailer/phpmailer` só porque o arquivo importa a classe. |
| `OliviaLib\Config` | Sessão, CSRF UUID, `BASE_PATH` | Genericizar (ver abaixo). Sem `UserSessionService`. |
| `OliviaLib\Database` | DSN para `OliviaDatabaseLibrary\ADatabase` | Genericizar (ver abaixo). Sem `HUB_OIDC_DB_DSN`. |
| `OliviaLib\Route` | Rotas do skeleton | Fica no pacote como demo. ATRIO **não** a consome. |

### Genericizar `Config` (OLIVIA-FW-010)

- `display_errors` / `display_startup_errors` ligados somente se `APP_DEBUG=true`.
- Sanitizar `BASE_PATH` com `parse_url(..., PHP_URL_PATH)` e rejeitar `://` e `//` no início.
- Cookie de sessão: `httponly` e `secure` permanecem.
- `$_SESSION['App_folder']` continua `'OliviaApp'` (consumidores Hub sobrescrevem a classe).
- **Proibido:** `OliviaApp\Classes\UserSessionService`, `UserIdentityService`, qualquer import do Hub.

### Genericizar `Database` (OLIVIA-FW-010)

Ler DSN de env, nesta ordem:

1. `OLIVIA_DB_DSN` (formato `mysql:...` ou `sqlite:...`)
2. `DB_DRIVER` + `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`
3. Stub vazio (comportamento atual do skeleton)

Regras:

- Credenciais não podem ir embutidas no DSN (`user`/`username`/`password` no DSN → `RuntimeException`).
- Drivers suportados: `mysql`, `sqlite`.
- **Proibido:** `HUB_OIDC_DB_DSN`, política “produção só MySQL” do Hub, default `naufragio`.

### Hub (permanece no ATRIO)

- `atrio/lib/Config.php` — `UserSessionService`, `APP_DEBUG`, sanitização de path.
- `atrio/lib/Database.php` — `HUB_OIDC_DB_DSN`, política MySQL em produção.
- `atrio/lib/Route.php` — catálogo Hub, OIDC, `/livez` do app, 83 registros.

### Skeleton (permanece neste repositório)

`app/`, `publico/`, `index.php`, `HomeController`, `AuthController`, models demo. Não entram no contrato do kernel publicado além do autoload.

## Runtime e qualidade

Imagem obrigatória: `php:8.5.12-fpm-alpine3.24`.

| Artefato | Contrato |
| --- | --- |
| `Dockerfile` | `ARG PHP_VERSION=8.5.12-fpm-alpine3.24` e `FROM php:${PHP_VERSION}`. |
| Smoke | `php -r 'echo PHP_VERSION;'` = `8.5.12`; `/etc/os-release` tem `ID=alpine` e `VERSION_ID` 3.24. |
| `.github/workflows/quality.yml` | Gate único em PHP 8.5. Sem matriz 8.2/8.4. `composer validate`, lint do kernel, teste `tests/kernel/framework_contract_test.php`, smoke da imagem. |
| `/livez` | Rota GET no skeleton que responde 200 texto `ok` (probe). Não copiar o Nginx estático do ATRIO. |

O Dockerfile deste repo é o do skeleton (PHP-FPM Alpine). Não precisa Nginx+AKS nesta entrega.

## Consumo pelo ATRIO (ATRIO-OLIVIA-FW-010)

O CI do ATRIO **não** vê `../OliviaApp`. Path local é proibido.

```json
"name": "cpsrepositorio/cgtic-atrio",
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/elzobrito/OliviaApp.git"
  }
],
"require": {
  "elzobrito/olivia-framework": "dev-feat/olivia-framework-php85-alpine as 0.1.0"
}
```

O `name` do ATRIO deixa de ser `elzobrito/olivia-app` para não colidir com o pacote antigo. Preferência: `cpsrepositorio/cgtic-atrio`.

Passos no ATRIO (depois do push desta branch no GitHub):

1. Adicionar o repositório VCS e o `require` acima.
2. Remover `atrio/lib/CommandController.php` e `atrio/lib/Email.php`.
3. Manter `Config.php`, `Database.php`, `Route.php` do Hub.
4. Regenerar `atrio/composer.lock`.
5. Runtime continua `php:8.5.12-fpm-alpine3.24`. CI-500 continua 185 testes.
6. Branch empilhada `feat/olivia-framework-consume` sobre `fix/test-inventory-185`. Não misturar no PR #4. Não mergear em `main` sem aceite do deploy AKS.

## Teste de kernel

`tests/kernel/framework_contract_test.php` (CLI, sem PHPUnit obrigatório) deve afirmar:

1. `composer.json` `name` = `elzobrito/olivia-framework`.
2. `require.php` = `>=8.2 <8.6`.
3. Autoload `OliviaLib\\` aponta para `lib/`.
4. `lib/CommandController.php` e `lib/Email.php` existem e declaram `namespace OliviaLib`.
5. `lib/Config.php` **não** contém `UserSessionService` nem `UserIdentityService`.
6. `lib/Database.php` **não** contém `HUB_OIDC_DB_DSN`.
7. `Dockerfile` declara `PHP_VERSION=8.5.12-fpm-alpine3.24`.
8. Nenhuma string `HUB_OIDC`, `UserSessionService` ou `cpsrepositorio` no diretório `lib/`.

## README

O README do produto permanece (não o stub ESAA). Atualizar requisitos para PHP `>=8.2 <8.6`, runtime `php:8.5.12-fpm-alpine3.24`, nome do pacote `elzobrito/olivia-framework` e o recorte kernel vs Hub.

## Fora de escopo

- Horus (`/home/elzobrito/desenvolvimento/horus`): cliente HTTPS/SSO do ATRIO; re-plataforma Azure App Service é entrega separada.
- Reescrever SHA `57eb048`.
- Publicar no Packagist nesta entrega (VCS GitHub basta para o ATRIO).
- Copiar Route/Config/Database do Hub para este pacote.
- Adicionar PHPMailer como dependência nova.
- Merge do ATRIO PR #4 em `main`.
- Mover kernel de `lib/` para `src/`.
- Governança ESAA deste repo (`.roadmap/`, `AGENTS.md`) não entra no pacote publicado; `.gitattributes` pode `export-ignore` esses paths se conveniente, sem ser critério de aceite.

## Critérios de aceite (esta spec)

1. Nomeia `elzobrito/olivia-framework`, runtime `php:8.5.12-fpm-alpine3.24` e Composer `>=8.2 <8.6`.
2. Separa kernel (`CommandController`, `Email`, Config/Database genéricos) de Route/Config/Database do Hub.
3. Descreve o consumo pelo ATRIO via repositório VCS GitHub, sem path local no CI.
