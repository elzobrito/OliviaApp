# Olivia Framework

Olivia é um framework PHP para aplicações web com organização em MVC, roteamento com suporte a árvore Trie e middlewares, proteção CSRF baseada em sessão, bibliotecas auxiliares consolidadas *in-tree* sob um único pin de PHP e uma camada de views baseada em componentes reutilizáveis.

- **Pacote Composer:** `elzobrito/olivia-framework`
- **Versão atual:** `2.0.1` (`COMPOSER_ROOT_VERSION=2.0.1`)
- **Repositório:** https://github.com/elzobrito/OliviaApp

Este repositório publica o kernel (`OliviaLib\\` em `lib/`), todas as bibliotecas do ecossistema Olivia incorporadas *in-tree* (`src/`) e o skeleton de aplicação (`app/`, `publico/`, `index.php`). Um único pin de PHP cobre tanto o kernel quanto as bibliotecas. O Hub ATRIO consome o pacote e mantém as classes específicas do Hub (`Config`, `Database`, `Route`) no próprio aplicativo.

---

## Novidades da versão `2.0` (PHP 8.5 / Alpine 3.24)

- **Pacote unificado e pin único de PHP:** renomeado de `elzobrito/olivia-app` para `elzobrito/olivia-framework`, exigindo PHP `>=8.2 <8.6` no Composer e homologado na imagem oficial `php:8.5.12-fpm-alpine3.24` (mitigação de CVEs de runtime PHP).
- **Bibliotecas Olivia *in-tree* (`src/`):** incorporação direta de `OliviaRouter` (v2.4), `OliviaDatabaseLibrary`, `OliviaDatabaseModel`, `OliviaDatabaseConfig`, `OliviaCache`, `OliviaPagination`, `Oliviapdf`, `OliviaUpload` e `OliviaDownload`, declarando `replace` no Composer para os antigos pacotes `elzobrito/olivia-*`.
- **Segurança endurecida (Router, Middleware e CSRF):**
  - `OliviaRouter\RouterConfig` lê configurações (`CSRF`, `UUID`, `App_folder`, `Controller_folder`, `Middleware_folder`, `BASENAME`) exclusivamente da sessão (`SessionContextStore`) ou de injeção explícita — cookies `OLIVIA_*` não sobrepõem a sessão nem desativam o CSRF.
  - Interrupção real do despacho quando um middleware retorna `false` ou lança `OliviaRouter\MiddlewareHaltException`.
  - Métodos `CommandController::view()` e `route()` não expõem o token `_token` na query string da URL.
- **Banco de Dados compatível com PHP 8.5 e TLS/SSL flexível:**
  - `OliviaLib\Database::getDB()` resolve configuração por variáveis de ambiente (`OLIVIA_DB_DSN` ou `DB_*`) para `mysql` e `sqlite`, bloqueando credenciais embutidas no DSN.
  - `OliviaDatabaseLibrary\Connection` utiliza constantes modernas `Pdo\Mysql::ATTR_*` do PHP 8.5 (com fallback compatível) sem warnings de depreciação e inclui o detalhe da `PDOException` em falhas de conexão.
  - Suporte TLS/SSL com bundle CA atualizado em `src/database/BaltimoreCyberTrustRoot.crt.pem` (incluindo raízes Baltimore, DigiCert Global Root G2/CA e Microsoft RSA Root CA 2017 para Azure MySQL). Conexões com `ssl=true` exigem verificação de certificado CA; conexões com `ssl=false` explícito funcionam tanto em loopback quanto em servidores MySQL remotos/containers sem TLS (`ISS-OLIVIA-DB-SSL-001`).
  - `QueryBuilder` aceita `string` ou `array` em `fields()` e nas cláusulas (`where()`, `group()`, `order()`, `having()`, `limit()`).
  - `OliviaDatabaseModel\Model` e `OliviaDatabaseConfig\Database` delegam a resolução de conexão para `OliviaLib\Database::getDB()` da aplicação e expõem `find_table()` além de `find()`, `findForId()`, `all()`, `count()`, `save()`, `update()`, `delete()` e `deleteFull()`.
- **Criptografia reforçada (`CommandController`):**
  - `encryptIt()` e `decryptIt()` (`AES-256-CBC`) validam estritamente os tipos de entrada (tratando arrays/nulos com segurança), usam `base64_decode(..., true)` estrito e falham fechado (`false`) quando `secret_key` ou `secret_iv` estão ausentes ou inválidos.
- **Resiliência do Cache de Sessão (`2.0.1`):**
  - `OliviaCache\SessionCache` valida explicitamente a integridade de array antes de ler (`get()`) ou gravar (`set()`), evitando warnings de *illegal string offset* e *array to string conversion* caso o offset de sessão estivesse previamente instanciado com tipo escalar/string.

---

## Requisitos

- PHP `>=8.2 <8.6` (resolução Composer)
- Runtime de produção/qualidade: Linux Alpine 3.24 e PHP 8.5, imagem `php:8.5.12-fpm-alpine3.24`
- Extensões PHP: `ext-pdo`, `ext-fileinfo`, `openssl` (para criptografia e TLS)
- Composer 2
- Ambiente web compatível com `index.php` na raiz do projeto e sessão PHP habilitada

---

## Instalação do skeleton

```bash
composer install
```

Aponte o servidor local para a pasta do projeto e acesse `/`.

---

## Consumo como pacote

O pacote está publicado no Packagist (`elzobrito/olivia-framework`) e também pode ser consumido diretamente via repositório VCS no `composer.json` do consumidor:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/elzobrito/OliviaApp.git"
    }
  ],
  "require": {
    "elzobrito/olivia-framework": "^2.0"
  }
}
```

Quando o aplicativo consumidor mapeia o mesmo prefixo PSR-4 (por exemplo, `OliviaLib\\` → `lib/` no ATRIO), os arquivos locais do aplicativo têm precedência no autoload do Composer sobre os arquivos homônimos do pacote. Além disso, a seção `replace` do `composer.json` substitui automaticamente todos os pacotes `elzobrito/olivia-*` legados.

---

## Kernel vs Bibliotecas In-Tree vs Hub

### Kernel neste pacote (`lib/` → `OliviaLib\\`)

- `OliviaLib\CommandController` — classe base de controllers com helpers de requisição (`requestGet`, `requestPost`, `requestArrayGet`, `requestArrayPost`, `requestFile`), navegação (`view`, `route`, `url`), CSRF (`csrf_field`, `csrf_token`), criptografia (`encryptIt`, `decryptIt` em AES-256-CBC), validação de CPF (`validaCPF`) e formatação (`mask`, `format_string`, `formataData`, `tirarAcentos`, `letra_maiuscula`, `mes_do_ano_por_extenso`).
- `OliviaLib\Email` — envio SMTP via PHPMailer.
- `OliviaLib\Config` — inicialização de sessão segura (`httponly`, `secure`), controle de erros via `APP_DEBUG`, sanitização de `BASE_PATH` e geração de `UUID`, `secret_key` e `secret_iv` em sessão.
- `OliviaLib\Database` — configuração genérica de conexão via `OLIVIA_DB_DSN` e variáveis `DB_*` (sem acoplamento ao `HUB_OIDC_DB_DSN`).
- `OliviaLib\Route` — registro de rotas do skeleton (incluindo probe `/livez`).

### Bibliotecas in-tree (`src/`), pinadas em PHP `>=8.2 <8.6`

- `OliviaRouter\\` (`src/router/`) — Router 2.4 com indexação em árvore `Trie`, despacho de controllers/middlewares, `RouterConfig`, `SessionContextStore` e `MiddlewareHaltException`.
- `OliviaDatabaseLibrary\\` (`src/database/`) — `ADatabase`, `Connection` (com retry, opções PDO para PHP 8.5 e bundle CA TLS), `QueryBuilder` e `ConnectionInterface`.
- `OliviaDatabaseModel\\` (`src/database-model/`) — `Model` base com operações CRUD (`find`, `find_table`, `findForId`, `all`, `count`, `save`, `update`, `delete`, `deleteFull`) e hidratação via `request()` / `request_cripto()`.
- `OliviaDatabaseConfig\\` (`src/database-config/`) — `Database` que delega automaticamente para `OliviaLib\Database::getDB()` quando disponível.
- `OliviaCache\\` (`src/cache/`) — `CacheInterface` e `SessionCache` (usado pelo `QueryBuilder` para cachear consultas `SELECT` em sessão e invalidar em escritas).
- `OliviaPagination\\` (`src/pagination/`) — `Pagination` com geração de controles HTML (`page_links`), sanitização contra XSS e exportação estruturada (`to_array()`, `get_limit_keys()`).
- `Oliviapdf\\` (`src/pdf/`) — `Pdf` estendendo `FPDF` com suporte a tabelas (`SetWidths`, `SetAligns`, `Row`), HTML básico (`WriteHTML`), formas geométricas (`Circle`, `Ellipse`), texto rotacionado/direcional e cabeçalho/rodapé customizáveis.
- `OliviaUpload\\` (`src/upload/`) — `Upload` com validação de extensão e tamanho (em MB) e renomeação opcional com UUID v4.
- `OliviaDownload\\` (`src/download/`) — `Download` com streaming em chunks de 8 KB, suporte a HTTP Range (`206 Partial Content` / download resumível) e modos `now()` (`attachment`) ou `inline()`.

### Permanecem no aplicativo consumidor (ex.: Hub ATRIO)

- `Config`, `Database` e `Route` específicos do domínio do Hub.

---

## Estrutura do projeto

```text
OliviaApp/
|-- app/
|   |-- Controller/
|   |   |-- AuthController.php
|   |   `-- HomeController.php
|   |-- Middleware/
|   |   |-- RequestGet.php
|   |   `-- RequestPost.php
|   `-- Models/
|       |-- Configuracoes.php
|       `-- User.php
|-- docs/
|   |-- qa/
|   `-- spec/
|-- lib/
|   |-- CommandController.php
|   |-- Config.php
|   |-- Database.php
|   |-- Email.php
|   `-- Route.php
|-- publico/
|   |-- Components/
|   |-- Home/
|   |-- View/
|   `-- E404.php
|-- src/
|   |-- cache/
|   |-- database/
|   |-- database-config/
|   |-- database-model/
|   |-- download/
|   |-- pagination/
|   |-- pdf/
|   |-- router/
|   `-- upload/
|-- tests/
|   `-- kernel/
|       |-- framework_contract_test.php
|       `-- security_contract_test.php
|-- Dockerfile
|-- composer.json
|-- index.php
`-- README.md
```

---

## Namespaces (PSR-4)

O autoload em `composer.json` registra os 12 namespaces PSR-4 abaixo:

| Namespace | Diretório | Escopo |
| --- | --- | --- |
| `OliviaApp\\` | `app/` | Aplicação (skeleton) |
| `OliviaLib\\` | `lib/` | Kernel do framework |
| `OliviaPublico\\` | `publico/` | Views e componentes visuais (skeleton) |
| `OliviaRouter\\` | `src/router/` | Roteador HTTP, Trie, Config e Middlewares |
| `OliviaPagination\\` | `src/pagination/` | Paginação de registros |
| `Oliviapdf\\` | `src/pdf/` | Geração de relatórios PDF (FPDF) |
| `OliviaUpload\\` | `src/upload/` | Upload e validação de arquivos |
| `OliviaDownload\\` | `src/download/` | Download via streaming e Range HTTP |
| `OliviaDatabaseLibrary\\` | `src/database/` | Conexão PDO, ADatabase e QueryBuilder |
| `OliviaDatabaseModel\\` | `src/database-model/` | Abstração de Models CRUD |
| `OliviaDatabaseConfig\\` | `src/database-config/` | Resolução de configuração de banco |
| `OliviaCache\\` | `src/cache/` | Cache de consultas em sessão |

---

## Configuração de Banco de Dados

`OliviaLib\Database::getDB()` resolve as credenciais e opções de conexão a partir das variáveis de ambiente:

| Variável | Descrição |
| --- | --- |
| `OLIVIA_DB_DSN` | DSN de conexão (`mysql:host=...;port=3306;dbname=...` ou `sqlite:/caminho/arquivo.sqlite`). Credenciais (`user`/`password`) embutidas no DSN são rejeitadas por segurança. |
| `DB_DRIVER` | Driver explícito (`mysql` ou `sqlite`), caso `OLIVIA_DB_DSN` não seja informado. |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` | Host, porta e nome do banco (sobrescrevem ou complementam o DSN). |
| `DB_USERNAME` (ou `DB_USER`) / `DB_PASSWORD` | Credenciais de autenticação do banco. |
| `OLIVIA_DB_SSL` (ou `DB_SSL`) | Ativa (`true`) ou desativa (`false`) TLS no MySQL. Padrão: `false` para loopback (`127.0.0.1`, `localhost`, `::1`) e SQLite; `true` para hosts MySQL remotos. |
| `OLIVIA_DB_SSL_CA` (ou `DB_SSL_CA`) | Caminho para arquivo CA PEM. Quando vazio e `ssl=true`, usa o bundle padrão `src/database/BaltimoreCyberTrustRoot.crt.pem`. |

---

## Fluxo da aplicação e Rotas

O ponto de entrada é `index.php`, que:

1. Carrega o autoload do Composer (`vendor/autoload.php`)
2. Inicializa a configuração base da aplicação (`new OliviaLib\Config()`)
3. Executa o roteador principal (`(new OliviaLib\Route())->execute()`)

Rotas base registradas em `lib/Route.php`:

- `GET /` -> `HomeController#index`
- `GET /livez` -> `HomeController#livez` (health/liveness probe, responde `200 ok`)
- `GET /error404` -> `HomeController#error404`
- Rotas auxiliares dinâmicas para testes com prefixo `/olivia-*`

---

## Camada de views e componentes

A renderização principal utiliza as classes base:

- `publico/View/ViewModel.php`
- `publico/View/ViewModelErro.php`

Essas classes oferecem suporte a componentes reutilizáveis por meio do método `renderComponent()`.

Componentes disponíveis no skeleton:

- `publico/Components/Component.php`
- `publico/Components/Navigation/BrandHeader.php`
- `publico/Components/Sections/HeroSection.php`
- `publico/Components/Footers/SimpleFooter.php`
- `publico/Components/Feedback/ErrorState.php`

---

## Qualidade e Testes

```bash
composer validate --no-check-publish --strict
php tests/kernel/framework_contract_test.php
php tests/kernel/security_contract_test.php
docker build --build-arg PHP_VERSION=8.5.12-fpm-alpine3.24 --tag olivia-framework:smoke .
```

O workflow de Quality Gate (`.github/workflows/quality.yml`) valida o `composer.json`, executa lint (`php -l`) em `lib/`, `src/` e `app/`, roda as suítes de contrato (`framework_contract_test.php` e `security_contract_test.php`) em PHP 8.5 e constrói a imagem Docker exigindo `PHP 8.5.12`, `ID=alpine` e `VERSION_ID=3.24`.

---

## Segurança

- **CSRF e Autoridade de Sessão:** inicializado em `lib/Config.php` com token UUID v4 armazenado em `$_SESSION['UUID']`. O roteador lê CSRF, namespace e pastas **somente da sessão** (ou de um `RouterConfig` explícito); cookies `OLIVIA_*` não desativam CSRF nem alteram o namespace de controllers/middlewares.
- **Proteção de URLs:** `csrf_field()` injeta o campo oculto `_token` para formulários `POST`; `view()` e `route()` não expõem o token na URL.
- **Halt de Middleware:** middlewares que retornam `false` ou lançam `OliviaRouter\MiddlewareHaltException` interrompem imediatamente o despacho antes da execução do controller.
- **MySQL TLS/SSL:** conexões MySQL remotas assumem `ssl=true` por padrão (`OLIVIA_DB_SSL` / `DB_SSL`) com verificação estrita de certificado CA; `ssl=false` pode ser configurado explicitamente quando o servidor de banco não oferece TLS.
- **Sessão e Erros:** cookies de sessão utilizam `httponly` e `secure`. `display_errors` e `display_startup_errors` permanecem desligados por padrão e só são ativados quando `APP_DEBUG=true`.

Se você descobrir uma vulnerabilidade de segurança no framework, entre em contato com Elzo Brito pelo e-mail `elzo.santos@cps.sp.gov.br`.

---

## Dependências de terceiros

As bibliotecas Olivia residem *in-tree* em `src/`. Dependências externas gerenciadas via Composer:

- `ramsey/uuid` (`^4.0`)
- `fpdf/fpdf` (`^1.82`)

---

## Licença

Olivia Framework é um software de código aberto licenciado sob a licença MIT.
