# Olivia Framework

Olivia é um framework PHP para aplicações web com organização em MVC, roteamento simples, suporte a CSRF, integração com bibliotecas auxiliares do ecossistema Olivia e uma camada de views baseada em componentes reutilizáveis.

Pacote Composer: `elzobrito/olivia-framework`  
Repositório: https://github.com/elzobrito/OliviaApp

Este repositório publica o kernel (`OliviaLib\\` em `lib/`), as libs Olivia in-tree (`src/`) e o skeleton de aplicação. Um único pin de PHP cobre o kernel e as libs. O Hub ATRIO consome o pacote e mantém as classes Hub (`Config`, `Database`, `Route`) no próprio aplicativo.

## Requisitos

- PHP `>=8.2 <8.6` (resolução Composer)
- Runtime obrigatório: Linux Alpine 3.24 e PHP 8.5, imagem `php:8.5.12-fpm-alpine3.24`
- Composer
- Ambiente web compatível com `index.php` na raiz do projeto
- Sessão PHP habilitada

## Instalação do skeleton

```bash
composer install
```

Aponte o servidor local para a pasta do projeto e acesse `/`.

## Consumo como pacote

O CI de consumidores não vê um path local. Use o repositório VCS:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/elzobrito/OliviaApp.git"
    }
  ],
  "require": {
    "elzobrito/olivia-framework": "0.2.0"
  }
}
```

O driver VCS do GitHub ainda identifica este repositório pelo `name` em `master` (`elzobrito/olivia-app`). Consumidores CI usam `type: package` apontando para o SHA da branch, sem path local.

Prefixos publicados: `OliviaLib\\` → `lib/` e os namespaces in-tree (`OliviaRouter\\`, `OliviaDatabaseLibrary\\`, etc.). Classes do aplicativo consumidor com o mesmo prefixo vencem no autoload do Composer. O pacote `replace` as libs `elzobrito/olivia-*` antigas.

## Kernel vs Hub

Kernel neste pacote (`lib/`):

- `OliviaLib\CommandController`
- `OliviaLib\Email`
- `OliviaLib\Config` (genérico: `APP_DEBUG`, sanitização de `BASE_PATH`)
- `OliviaLib\Database` (DSN via `OLIVIA_DB_DSN` / `DB_*`; sem `HUB_OIDC_DB_DSN`)
- `OliviaLib\Route` (rotas do skeleton, incluindo `/livez`)

Libs in-tree (`src/`), pinadas no mesmo PHP `>=8.2 <8.6`:

- `OliviaRouter\\` (router 2.4, SHA `27a3f81`)
- `OliviaPagination\\`
- `Oliviapdf\\`
- `OliviaUpload\\`
- `OliviaDownload\\`
- `OliviaDatabaseLibrary\\` / `OliviaDatabaseModel\\` / `OliviaDatabaseConfig\\`
- `OliviaCache\\`

Permanecem no ATRIO: `Config`, `Database` e `Route` do Hub.

## Estrutura do projeto

```text
OliviaApp/
|-- app/
|   |-- Controller/
|   |-- Middleware/
|   `-- Models/
|-- lib/
|   |-- CommandController.php
|   |-- Config.php
|   |-- Database.php
|   |-- Email.php
|   `-- Route.php
|-- src/
|   |-- router/
|   |-- pagination/
|   |-- pdf/
|   |-- upload/
|   |-- download/
|   |-- database/
|   `-- cache/
|-- publico/
|   |-- Components/
|   |-- Home/
|   |-- View/
|   `-- E404.php
|-- tests/
|   `-- kernel/
|-- Dockerfile
|-- composer.json
|-- index.php
`-- README.md
```

## Namespaces

O autoload segue PSR-4:

- `OliviaApp\\` -> `app/`
- `OliviaLib\\` -> `lib/`
- `OliviaPublico\\` -> `publico/`

## Fluxo da aplicação

O ponto de entrada é `index.php`. Ele:

1. carrega o autoload do Composer
2. inicializa a configuração base da aplicação
3. executa o roteador principal

As rotas são registradas em `lib/Route.php`.

## Rotas atuais

- `/` -> `HomeController#index`
- `/livez` -> `HomeController#livez` (probe, responde `ok`)
- `/error404` -> `HomeController#error404`

Também existem rotas auxiliares montadas dinamicamente para testes com prefixo `olivia`.

## Qualidade

```bash
composer validate --no-check-publish --strict
php tests/kernel/framework_contract_test.php
docker build --build-arg PHP_VERSION=8.5.12-fpm-alpine3.24 --tag olivia-framework:smoke .
```

O Quality Gate (`.github/workflows/quality.yml`) usa PHP 8.5 e exige que a imagem reporte PHP 8.5.12, `ID=alpine` e `VERSION_ID` 3.24.

## Camada de views e componentes

A renderização principal usa:

- `publico/View/ViewModel.php`
- `publico/View/ViewModelErro.php`

Essas classes oferecem suporte a componentes por meio do método `renderComponent()`.

Componentes disponíveis nesta base:

- `publico/Components/Component.php`
- `publico/Components/Navigation/BrandHeader.php`
- `publico/Components/Sections/HeroSection.php`
- `publico/Components/Footers/SimpleFooter.php`
- `publico/Components/Feedback/ErrorState.php`

## Segurança

O projeto inicializa proteção CSRF na configuração base e armazena o token em sessão (`lib/Config.php`). Cookies de sessão usam `httponly` e `secure`. `display_errors` só liga com `APP_DEBUG=true`.

Se você descobrir uma vulnerabilidade de segurança no framework, entre em contato com Elzo Brito pelo e-mail `elzo.santos@cps.sp.gov.br`.

## Dependências principais

Libs Olivia estão in-tree em `src/` (não são mais pacotes Composer separados). Terceiros:

- `ramsey/uuid`
- `fpdf/fpdf`

## Licença

Olivia Framework é um software de código aberto licenciado sob a licença MIT.
