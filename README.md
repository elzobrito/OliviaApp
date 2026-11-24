# Olivia Framework

Olivia é um framework PHP para aplicações web com organização em MVC, roteamento HTTP com middlewares, proteção CSRF baseada em sessão, bibliotecas auxiliares consolidadas *in-tree* sob um único pin de PHP e uma camada de views baseada em componentes reutilizáveis. O roteador percorre as rotas na ordem de registro e compara o método HTTP e o padrão de URL, convertido em expressão regular.

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
  - `CommandController::route()` e `redirect()` não expõem o token `_token` na query string da URL.
- **Banco de Dados compatível com PHP 8.5 e TLS/SSL flexível:**
  - `OliviaLib\Database` só declara as conexões; `OliviaDatabaseLibrary\DatabaseConfig` resolve cada uma pelas variáveis de ambiente (`OLIVIA_DB_DSN` ou `DB_*`) para `mysql` e `sqlite`, bloqueando credenciais embutidas no DSN e falhando fechado quando falta configuração (ver [Configuração de Banco de Dados](#configuração-de-banco-de-dados)).
  - `OliviaDatabaseLibrary\Connection` utiliza constantes modernas `Pdo\Mysql::ATTR_*` do PHP 8.5 (com fallback compatível) sem warnings de depreciação e inclui o detalhe da `PDOException` em falhas de conexão.
  - Suporte TLS/SSL com bundle CA atualizado em `src/database/BaltimoreCyberTrustRoot.crt.pem` (incluindo raízes Baltimore, DigiCert Global Root G2/CA e Microsoft RSA Root CA 2017 para Azure MySQL). Conexões com `ssl=true` exigem verificação de certificado CA; conexões com `ssl=false` explícito funcionam tanto em loopback quanto em servidores MySQL remotos/containers sem TLS (`ISS-OLIVIA-DB-SSL-001`); pela configuração de ambiente, desligar TLS para host remoto exige `OLIVIA_DB_ALLOW_INSECURE=true` (`OLIVIA-SEC-316`).
  - `QueryBuilder` aceita `string` ou `array` em `fields()` e nas cláusulas (`where()`, `group()`, `order()`, `having()`, `limit()`).
  - Desde a `OLIVIA-SEC-301`, identificadores em `table()`/`fields()`/`group()`/`order()`/`limit()` são validados (ver [Segurança](#segurança)); use `Raw::sql()` para expressões confiáveis.
  - `OliviaDatabaseModel\Model` e `OliviaDatabaseConfig\Database` delegam a resolução de conexão para `OliviaLib\Database::getDB()` da aplicação e expõem `find_table()` além de `find()`, `findForId()`, `all()`, `count()`, `save()`, `update()`, `delete()` e `deleteFull()`.
- **Criptografia autenticada (`OliviaLib\Support\Crypto`, `OLIVIA-SEC-303`):**
  - `Crypto::encrypt()` (e o atalho legado `encryptIt()`) gera o formato `v2.` + base64url(IV de 12 bytes aleatório, tag de 16 bytes, texto cifrado) com `AES-256-GCM`: a mesma entrada produz valores diferentes e qualquer adulteração faz `Crypto::decrypt()` / `decryptIt()` retornar `false`.
  - Chave: parâmetro explícito do chamador → `OLIVIA_APP_KEY` (`base64:<32 bytes>` ou texto com ≥ 32 caracteres) → `secret_key` da sessão (com aviso em log; os valores expiram com a sessão). Gere uma chave com `php -r "echo 'base64:'.base64_encode(random_bytes(32)), PHP_EOL;"`. Desde `OLIVIA-SEC-313`, uma chave explícita precisa ser string com ≥ 16 caracteres (contados em caracteres quando é UTF-8 válido — `'çççççççç'` tem 16 bytes mas só 8 caracteres e é recusada — e em bytes quando é binária); qualquer outra (vazia, curta, número, array, objeto) faz `encrypt`/`decrypt`/`decryptIt` retornarem `false`, inclusive na leitura legada com `OLIVIA_CRYPTO_LEGACY=true` — nunca cai em `OLIVIA_APP_KEY` nem na sessão. Chaves curtas antigas só são aceitas pelo caminho explícito de migração `Crypto::decryptLegacy()`.
  - Contexto (`OLIVIA-SEC-313`): `Crypto::encrypt($valor, null, 'usuario.id')` vincula o valor ao contexto no AAD do GCM; ele só decifra com `Crypto::decrypt($cifrado, null, 'usuario.id')` e falha em outro contexto ou sem contexto. Sem contexto (ou com `''`) o AAD é o original, então valores `v2` já emitidos continuam válidos. Contexto não-string falha fechado.
  - Legado (`OLIVIA-SEC-313`): `Crypto::decrypt()` e `decryptIt()` aceitam **somente** `v2`. Valores antigos em `AES-256-CBC` (sem autenticação) são lidos apenas por `Crypto::decryptLegacy($valor, $chave = null, $iv = null)` ou, durante uma migração, por `decrypt()`/`decryptIt()` com `OLIVIA_CRYPTO_LEGACY=true` (só sem contexto). Migre lendo com `decryptLegacy()` e regravando com `encrypt()`. **Quebra de compatibilidade:** o terceiro parâmetro de `Crypto::decrypt()` deixou de ser o IV legado e passou a ser o contexto; `decryptIt($q, $chave, $iv)` mantém a assinatura e usa o `$iv` informado na leitura legada (também quando `$q` é array).
- **Resiliência do Cache de Sessão (`2.0.1`):**
  - `OliviaCache\SessionCache` valida explicitamente a integridade de array antes de ler (`get()`) ou gravar (`set()`), evitando warnings de *illegal string offset* e *array to string conversion* caso o offset de sessão estivesse previamente instanciado com tipo escalar/string.

---

## Requisitos

- PHP `>=8.2 <8.6` (resolução Composer)
- Runtime de produção/qualidade: Linux Alpine 3.24 e PHP 8.5, imagem `php:8.5.12-fpm-alpine3.24`
- Extensões PHP: `pdo` com `pdo_mysql` e/ou `pdo_sqlite`, `fileinfo`, `openssl` (criptografia e TLS), `sodium` e `zip` (validação de uploads Office/ODF; sem ela esses formatos são recusados)
- A imagem Docker já traz todas: `pdo_mysql` e `zip` são compiladas no build e o toolchain é removido na mesma camada; o build falha se faltar alguma extensão
- `php.ini` de produção: a imagem usa o `php.ini-production` oficial mais [`docker/php/production.ini`](docker/php/production.ini) (`expose_php=Off`, sem `X-Powered-By`; `display_errors=Off` e `display_startup_errors=Off`; `log_errors=On` para o stderr do container). Fora do Docker, aplique as mesmas diretivas no seu `php.ini`
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

- `OliviaLib\CommandController` — classe base de controllers, middlewares e views, reduzida a navegação (`route`, `url`, `redirect` — encerra a requisição), mensagens flash (`flash`, `pullFlash`), CSRF (`csrf_field`, `csrf_token`) e leitura de requisição (`requestGet`, `requestPost`).
- `OliviaLib\Support\Formatos` — utilitários pt-BR estáticos: `mesPorExtenso` (1 = Janeiro), `mask`, `formatString`, `formataData`, `validaCpf`, `tirarAcentos`, `letraMaiuscula`.
- `OliviaLib\Support\Crypto` — `encrypt`/`decrypt` em AES-256-GCM com contexto opcional no AAD e `decryptLegacy` para migração de CBC (ver [Segurança](#segurança)).
- **Compatibilidade (`OLIVIA-CLEAN-307`):** `mask`, `format_string`, `formataData`, `validaCPF`, `tirarAcentos`, `letra_maiuscula`, `mes_do_ano_por_extenso`, `encryptIt` e `decryptIt` continuam no `CommandController` como atalhos `@deprecated` para as classes `Support` e serão removidos na 3.0. `mes_do_ano_por_extenso()` passou a ser 1-indexado (antes `1` retornava "Fevereiro"). Foram **removidos**: `msgbox`, `view`, `find_object`, `buscar_objeto_in_array`, `getArrays`/`setArrays`, `getParametros`/`setParametros`/`getAllParametros`, `requestArrayGet`, `requestArrayPost`, `requestFile`, `getDateTime`, `Model::__call` (métodos inexistentes agora lançam `Error`), `RouterConfig::fromRequest`/`fromRequestData`, `Connection::getInstance`, `AuthController` e o middleware `RequestGet`. `Router` aceita apenas `middleware()` como cláusula; outros nomes lançam `BadMethodCallException`.
- `OliviaLib\Email` — envio SMTP via PHPMailer (dependência opcional, configurada pelas variáveis `OLIVIA_SMTP_*`; sem PHPMailer ou sem `OLIVIA_SMTP_HOST`/`OLIVIA_SMTP_USER`, `enviar()` devolve a mensagem de erro em vez de enviar). Anexos só são lidos de dentro de `OLIVIA_UPLOAD_DIR`.
- `OliviaLib\Config` — inicialização de sessão segura (`httponly`, `secure`), controle de erros via `APP_DEBUG`, sanitização de `BASE_PATH` e geração de `UUID`, `secret_key` e `secret_iv` em sessão.
- `OliviaLib\Database` — declaração das conexões da aplicação (`connections()`); a leitura de `OLIVIA_DB_DSN`/`DB_*` e as regras de segurança ficam em `OliviaDatabaseLibrary\DatabaseConfig`.
- `OliviaLib\Route` — registro de rotas do skeleton (incluindo probe `/livez`).

### Bibliotecas in-tree (`src/`), pinadas em PHP `>=8.2 <8.6`

- `OliviaRouter\\` (`src/router/`) — Router 2.4 que percorre as rotas na ordem de registro e seleciona a primeira com método HTTP e padrão de URL compatíveis. O padrão é convertido em expressão regular e comparado com o caminho da URL. A classe `Trie` mantém o nome por compatibilidade e faz essa comparação. Inclui despacho de controllers e middlewares, `RouterConfig`, `SessionContextStore` e `MiddlewareHaltException`.
- `OliviaDatabaseLibrary\\` (`src/database/`) — `ADatabase` (registro de conexões nomeadas), `DatabaseConfig` (configuração lida do ambiente, com as regras de segurança), `Connection` (com retry, opções PDO para PHP 8.5 e bundle CA TLS), `QueryBuilder` e `ConnectionInterface`.
- `OliviaDatabaseModel\\` (`src/database-model/`) — `Model` base com operações CRUD (`find`, `find_table`, `findForId`, `all`, `count`, `save`, `update`, `delete`, `deleteFull`) e hidratação via `request()` / `request_cripto()`. **Mudança de comportamento:** `Model::delete()` sem argumento usa a chave do próprio model (`$this->id` ou a `$primaryKey` informada) e, sem identificador, lança `InvalidArgumentException` — antes apagava o registro `id = 1`. `Model::deleteFull()` sem identificador e sem valores também lança `InvalidArgumentException`. `save()` devolve o ID inserido, mas não preenche `$this->id`; atribua-o se for reutilizar o objeto.
- `OliviaDatabaseConfig\\` (`src/database-config/`) — `Database` que delega para `OliviaLib\Database::getDB()`; sem essa classe, lança `RuntimeException` (o antigo padrão embutido com `root`, senha vazia e banco `obs_escolar` foi removido).
- `OliviaCache\\` (`src/cache/`) — `CacheInterface` e `SessionCache` (usado pelo `QueryBuilder` para cachear consultas `SELECT` em sessão e invalidar em escritas).
- `OliviaPagination\\` (`src/pagination/`) — `Pagination` com geração de controles HTML (`page_links`), sanitização contra XSS e exportação estruturada (`to_array()`, `get_limit_keys()`).
- `Oliviapdf\\` (`src/pdf/`) — `Pdf` estendendo `FPDF` com suporte a tabelas (`SetWidths`, `SetAligns`, `Row`), HTML básico (`WriteHTML` — somente HTML confiável: links `<a href>` são gravados no PDF sem validação; nunca repasse HTML vindo do usuário), formas geométricas (`Circle`, `Ellipse`), texto rotacionado/direcional e cabeçalho/rodapé customizáveis.
- `OliviaUpload\\` (`src/upload/`) — `Upload` endurecido (`OLIVIA-SEC-304`): grava em `OLIVIA_UPLOAD_DIR` (padrão `storage/uploads`), valida allowlist de extensões, bloqueia extensões executáveis (inclusive dupla extensão), confere o MIME real com `finfo` e recusa conteúdo `<?php`; sempre renomeia para UUID v4 e aplica permissões 0750/0640 explicitamente, independentemente do umask (falha de `chmod` desfaz o upload e é reportada em `getError()`; pasta recém-criada cujo `chmod` falhou é removida para ser recriada na próxima tentativa). Pastas criadas pelo framework, inclusive a base e seus ancestrais ausentes, recebem 0750 uma a uma; pastas que já existiam (geridas pelo operador) não têm o modo alterado. Desde `OLIVIA-SEC-312`: cada pasta ancestral é conferida dentro da base **antes** de ser criada (links simbólicos para fora são recusados); docx/xlsx/pptx exigem `[Content_Types].xml` e a parte principal, e odt/ods/odp exigem o arquivo `mimetype` correto (lido com limite do tamanho esperado; entrada maior é recusada sem ser descomprimida) — esses formatos dependem da extensão PHP `zip` e são recusados sem ela; extensões sem MIME conhecido só são aceitas com MIME declarado: `new Upload('docs', 5, ['pdf', 'dwg' => 'image/vnd.dwg'], $_FILES['f'])`. Use `isValid()`, `getPath()`, `getArqNameCripto()` e `getError()`. Os parâmetros `$renomear` e `$permissao` são ignorados (compatibilidade).
- `OliviaDownload\\` (`src/download/`) — `Download` com streaming em chunks de 8 KB, suporte a HTTP Range (`206 Partial Content` / download resumível) e modos `now()` (`attachment`) ou `inline()`. Desde `OLIVIA-SEC-321` só serve arquivos **dentro de um diretório base**: por padrão `OLIVIA_UPLOAD_DIR` (o mesmo do `Upload`), ou o informado no construtor. O caminho pode ser relativo à base ou absoluto dentro dela; é resolvido com `realpath`. Caminho fora da base, link simbólico que aponta para fora, diretório e byte NUL são recusados com 404 (`RuntimeException`, sem o caminho na mensagem). O nome enviado vai saneado no `Content-Disposition` (`filename` ASCII sem aspas, `;`, barras, CR/LF nem controles, mais `filename*` UTF-8 da RFC 5987). Range segue a RFC 9110 e só vale em `GET`: `bytes=S-E`, `bytes=S-` e o sufixo `bytes=-N` (últimos N bytes) respondem 206; range fora do arquivo responde 416 com `Content-Range: bytes */tamanho`; múltiplos ranges, range invertido (inclusive com números enormes), espaços internos ou sintaxe inválida recebem o arquivo inteiro com 200. A resposta traz `ETag` forte (SHA-256 dos bytes) e `Last-Modified`; `If-Range` só mantém o Range quando é exatamente o `ETag` atual (data, ETag fraco ou diferente: 200 com o arquivo inteiro). `HEAD` responde os headers sem corpo e sem Range. Calcular o `ETag` exige uma leitura do arquivo antes de enviar os headers. Publique atualizações por substituição atômica do arquivo para que transferências em curso mantenham a versão aberta. Toda resposta, inclusive os erros 404/403 e mesmo sem o bootstrap do `Config`, leva `X-Content-Type-Options: nosniff`. Quem chamava `now()` com caminho absoluto fora de `OLIVIA_UPLOAD_DIR` precisa passar a base: `(new Download('/srv/relatorios'))->now('2026/marco.pdf', 'Relatório de março');`.

### Permanecem no aplicativo consumidor (ex.: Hub ATRIO)

- `Config`, `Database` e `Route` específicos do domínio do Hub.

---

## Estrutura do projeto

```text
OliviaApp/
|-- app/
|   |-- Controller/
|   |   `-- HomeController.php
|   |-- Middleware/
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
|   |-- Route.php
|   `-- Support/
|       |-- Crypto.php
|       `-- Formatos.php
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
|       |-- behavior_test.php
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
| `OliviaRouter\\` | `src/router/` | Roteamento por método HTTP e padrão de URL, verificado em ordem de registro com expressões regulares. Inclui configuração e middlewares. |
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

A aplicação só declara as conexões em `lib/Database.php`; host, banco e credenciais vêm do ambiente, e as regras de segurança ficam no framework (`OliviaDatabaseLibrary\DatabaseConfig`):

```php
namespace OliviaLib;

use OliviaDatabaseLibrary\ADatabase;
use OliviaDatabaseLibrary\DatabaseConfig;

class Database extends ADatabase
{
    protected static function connections(): array
    {
        return [
            'default'    => DatabaseConfig::fromEnvironment(),        // OLIVIA_DB_DSN, DB_*
            'relatorios' => DatabaseConfig::fromEnvironment('REL_'),  // REL_OLIVIA_DB_DSN, REL_DB_*
        ];
    }
}
```

- `getDB()` (ou `getDB('default')`) devolve a conexão `default`; `getDB('relatorios')` devolve a conexão nomeada. O parâmetro continua se chamando `$name`, então `getDB(name: 'relatorios')` também funciona. Num model, `protected $drive = 'relatorios';` escolhe a conexão.
- **Compatibilidade de `$drive`:** models antigos usam o nome do driver (`'mysql'`, `'sqlite'`). Um nome não declarado igual ao driver da conexão `default` resolve para ela; qualquer outro nome desconhecido lança `RuntimeException`.
- **Prefixo:** com `fromEnvironment('REL_')`, **todos** os nomes de banco da tabela abaixo recebem o prefixo (`REL_OLIVIA_DB_DSN`, `REL_DB_HOST`, `REL_DB_USERNAME`, `REL_OLIVIA_DB_SSL`...). O prefixo usa letras maiúsculas, dígitos e `_`, terminando em `_`.
- **Leitura preguiçosa:** o ambiente de uma conexão só é lido quando ela é pedida; uma conexão secundária sem variáveis não atrapalha a `default`.
- **Falha fechada:** sem driver (`OLIVIA_DB_DSN` ou `DB_DRIVER`), sem banco ou, no MySQL, sem host ou sem usuário, `getDB()` lança `RuntimeException` com o nome da variável que falta. `OLIVIA_DB_SSL`/`DB_SSL` com valor que não é booleano (ex.: `talvez`) também lança, em vez de desligar o TLS. Não existe mais configuração padrão: antes a falta de ambiente devolvia uma configuração vazia, e `OliviaDatabaseConfig\Database` caía em `root` com senha vazia. Declarar uma conexão com array fixo em vez de `DatabaseConfig` é recusado: credenciais não ficam no código.
- **Migração do `lib/Database.php` antigo** (passo a passo no [guia de migração](#guia-de-migração-da-configuração-de-banco)): troque a classe que sobrescrevia `getDB()` pela declaração acima e garanta `DB_USERNAME` e `DB_HOST` (ou `host` no DSN) no ambiente do MySQL. Uma subclasse antiga que ainda sobrescreve `getDB()` continua funcionando, mas sem as regras do framework.

Variáveis de ambiente:

| Variável | Descrição |
| --- | --- |
| `OLIVIA_DB_DSN` | DSN de conexão (`mysql:host=...;port=3306;dbname=...` ou `sqlite:/caminho/arquivo.sqlite`). Credenciais (`user`/`password`) embutidas no DSN são rejeitadas por segurança. |
| `DB_DRIVER` | Driver explícito (`mysql` ou `sqlite`), caso `OLIVIA_DB_DSN` não seja informado. |
| `DB_HOST` / `DB_PORT` / `DB_DATABASE` | Host, porta e nome do banco (sobrescrevem ou complementam o DSN). |
| `DB_USERNAME` (ou `DB_USER`) / `DB_PASSWORD` | Credenciais de autenticação do banco. |
| `DB_PASSWORD_FILE` | Caminho de um arquivo com a senha (secret do Docker/Kubernetes, ex.: `/run/secrets/db_password`). Uma quebra de linha final é removida; limite de 4096 bytes. Definir junto com `DB_PASSWORD` (mesmo vazio), ou apontar arquivo inexistente/ilegível/grande demais, lança `RuntimeException` sem expor o caminho. |
| `OLIVIA_DB_SSL` (ou `DB_SSL`) | Ativa (`true`) ou desativa (`false`) TLS no MySQL. Padrão: `false` para loopback (`127.0.0.1`, `localhost`, `::1`) e SQLite; `true` para hosts MySQL remotos. Para host remoto, `false` só vale com `OLIVIA_DB_ALLOW_INSECURE=true`. |
| `OLIVIA_DB_ALLOW_INSECURE` | `true` permite TLS desligado para host fora do loopback (um aviso vai ao log uma vez por processo). Sem ela, `getDB()` falha fechado. Nomes de serviço do Docker (`db`, `mysql`) contam como remotos. |
| `OLIVIA_DB_SSL_CA` (ou `DB_SSL_CA`) | Caminho para arquivo CA PEM. Quando vazio e `ssl=true`, usa o bundle padrão `src/database/BaltimoreCyberTrustRoot.crt.pem`. |
| `OLIVIA_FORCE_SECURE` | Força (`true`) ou desliga (`false`) o atributo `Secure` do cookie de sessão. Sem valor, é ativado automaticamente em requisições HTTPS. Valor inválido (ex.: `talvez`) é ignorado, registrado em log, e a detecção automática continua. |
| `OLIVIA_TRUSTED_PROXIES` | IPs ou CIDRs (IPv4/IPv6), separados por vírgula, dos proxies reversos confiáveis. `X-Forwarded-Proto` só é considerado quando `REMOTE_ADDR` está nessa lista; sem ela, o header é ignorado. O proxy listado **deve sobrescrever** (não acrescentar a) qualquer `X-Forwarded-Proto` recebido do cliente, porque o Olivia usa o primeiro valor do header. |
| `OLIVIA_CRYPTO_LEGACY` | `true` faz `Crypto::decrypt()`/`decryptIt()` também lerem valores legados `AES-256-CBC` (sem autenticação), apenas para migração. Padrão: desligado; valor inválido é ignorado e registrado em log. Prefira `Crypto::decryptLegacy()` explícito. |
| `OLIVIA_UPLOAD_DIR` | Diretório base de uploads e de anexos do `Email` (absoluto). Padrão: `<raiz>/storage/uploads`. Mantenha-o fora do webroot e sirva os arquivos via `OliviaDownload\Download`. O padrão fica sob a raiz que serve `index.php`: no Apache, `storage/.htaccess` nega tudo e desliga o PHP (resposta efetiva: 403, com ou sem `mod_rewrite`), e o `.htaccess` da raiz tem uma regra de reforço que responde 404 caso `storage/.htaccess` não seja lido; no nginx/PHP-FPM (que ignoram `.htaccess`) adicione `location ^~ /storage/ { deny all; return 404; }` ou aponte `OLIVIA_UPLOAD_DIR` para fora do docroot. |
| `OLIVIA_SMTP_HOST` / `OLIVIA_SMTP_PORT` | Servidor SMTP do `OliviaLib\Email` e porta (padrão `587`). Sem `OLIVIA_SMTP_HOST`, `enviar()` não envia. |
| `OLIVIA_SMTP_USER` / `OLIVIA_SMTP_PASSWORD` | Credenciais SMTP (autenticação sempre ligada). Sem `OLIVIA_SMTP_USER`, `enviar()` não envia. |
| `OLIVIA_SMTP_FROM` / `OLIVIA_SMTP_FROM_NAME` | Remetente e nome exibido. Padrão do remetente: `OLIVIA_SMTP_USER`. |
| `OLIVIA_SMTP_SECURE` | `tls` (padrão), `ssl` ou vazio para SMTP sem TLS. |
| `OLIVIA_DB_TIMEOUT` | Timeout de conexão em segundos (`PDO::ATTR_TIMEOUT`). Padrão: `5`. |
| `OLIVIA_DB_CONNECT_RETRIES` | Novas tentativas de conexão após falha (intervalo de 200 ms). Padrão: `1`. |
| `OLIVIA_QUERY_CACHE` | `session` liga o cache de consultas na sessão PHP (opção `query_cache` na configuração tem precedência). Padrão: **desligado** — a sessão não enxerga escritas feitas por outros processos ou usuários. |
| `OLIVIA_DB_PERSISTENT` | Usa `PDO::ATTR_PERSISTENT` entre requisições do PHP-FPM. Padrão: `false` — uma transação ou lock esquecido passaria para a próxima requisição. |

### Guia de migração da configuração de banco

Exemplos prontos: [`.env.example`](.env.example) (todas as variáveis de banco, sem segredo real) e [`docker-compose.example.yml`](docker-compose.example.yml) (app PHP-FPM, nginx e MySQL, com a senha vinda de um secret em arquivo). Copie `.env.example` para `.env`; `.env`, `.env.*` e `secrets/` estão no `.gitignore`.

**1. Troque o `lib/Database.php` antigo pela declaração.** Antes, a classe montava o array de conexão (às vezes com usuário e senha no código):

```php
// ANTES: credenciais e regras na aplicação
class Database extends ADatabase
{
    public static function getDB($name = null)
    {
        return ['driver' => 'mysql', 'host' => '10.0.0.5', 'database' => 'app',
                'user' => 'root', 'password' => 'segredo', 'ssl' => false];
    }
}
```

Depois, só a declaração (exemplo completo no início desta seção); valores vêm do ambiente:

```php
// DEPOIS: a aplicação só declara; o framework valida e falha fechado
protected static function connections(): array
{
    return ['default' => DatabaseConfig::fromEnvironment()];
}
```

**2. Leve os valores para o ambiente** (`.env` fora do git, variáveis do orquestrador ou secrets):

| Antes (no código) | Depois (ambiente) |
| --- | --- |
| `'host' => '10.0.0.5'`, `'database' => 'app'` | `OLIVIA_DB_DSN=mysql:host=10.0.0.5;port=3306;dbname=app` (ou `DB_HOST`/`DB_PORT`/`DB_DATABASE`) |
| `'user' => '...'` | `DB_USERNAME=...` (obrigatório no MySQL) |
| `'password' => '...'` | `DB_PASSWORD_FILE=/run/secrets/db_password` (preferido) ou `DB_PASSWORD` — nunca os dois |
| `'ssl' => false` com host remoto | `OLIVIA_DB_SSL=false` **e** `OLIVIA_DB_ALLOW_INSECURE=true`; melhor: manter TLS e apontar `OLIVIA_DB_SSL_CA` |
| segunda conexão com array próprio | `'relatorios' => DatabaseConfig::fromEnvironment('REL_')` e variáveis `REL_*` |

**3. O que deixa de funcionar** (todos falham com `RuntimeException` e mensagem exata, em vez de cair num padrão inseguro): host ausente (não há mais `127.0.0.1` implícito), usuário ausente, credencial dentro do DSN, `DB_PASSWORD` junto com `DB_PASSWORD_FILE`, arquivo de senha ilegível, TLS desligado para host remoto sem `OLIVIA_DB_ALLOW_INSECURE=true` e nome de conexão não declarado. Nomes de serviço do Docker (`db`, `mysql`) contam como host remoto.

**4. Confira a configuração sem imprimir a senha:**

```bash
php -r 'require "vendor/autoload.php"; $c = OliviaLib\Database::getDB(); unset($c["password"]); print_r($c);'
```

**5. Use usuários de menor privilégio.** A aplicação web não precisa de DDL nem de privilégios administrativos; migrations rodam com outro usuário, numa conexão própria usada só por scripts de linha de comando:

```sql
-- Usuário da aplicação (DB_USERNAME): só dados
CREATE USER 'olivia_app'@'10.0.0.%' IDENTIFIED BY '<senha do secret>' REQUIRE SSL;
GRANT SELECT, INSERT, UPDATE, DELETE ON olivia.* TO 'olivia_app'@'10.0.0.%';

-- Usuário de migrations (MIG_DB_USERNAME): estrutura, nunca usado pela app web
CREATE USER 'olivia_migrator'@'10.0.0.%' IDENTIFIED BY '<outra senha>' REQUIRE SSL;
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON olivia.* TO 'olivia_migrator'@'10.0.0.%';
```

```php
// lib/Database.php
return [
    'default'   => DatabaseConfig::fromEnvironment(),       // olivia_app
    'migracoes' => DatabaseConfig::fromEnvironment('MIG_'), // olivia_migrator, só em scripts CLI
];
```

Evite `ALL PRIVILEGES`, `GRANT OPTION`, `FILE`, `SUPER` e o usuário `root` na aplicação; restrinja o host do usuário à rede da aplicação e, em host remoto, use `REQUIRE SSL`. O exemplo do Docker Compose cria o usuário com `MYSQL_USER`, que recebe `ALL` no banco: aplique os `GRANT`s acima antes de produção.

**6. Outras mudanças das fases 1–3 que podem exigir ajuste:**

- `getDB()` continua devolvendo o array com a senha (compatibilidade); não o registre nem o despeje. A `Connection` mascara a senha (`OLIVIA-SEC-316`).
- `OliviaDownload\Download` só serve arquivos dentro de `OLIVIA_UPLOAD_DIR` ou da base passada no construtor (`OLIVIA-SEC-321`).
- Falha de CSRF lança `OliviaRouter\CsrfException` (subclasse de `RuntimeException`) e vira 403; quem compara a classe exata precisa atualizar (`OLIVIA-SEC-322`).
- Método não aceito pela rota responde 405 com `Allow`, e `HEAD` usa a rota `GET` (`OLIVIA-SEC-322`).
- O `Config` não altera mais o `memory_limit`; ajuste o `php.ini` se a aplicação dependia do limite ilimitado (`OLIVIA-SEC-322`).

### Conexões e transações

- **Pool por configuração:** `OliviaDatabaseLibrary\ConnectionManager::get($options)` devolve sempre a mesma `Connection` (e o mesmo PDO) para a mesma configuração; bancos diferentes ficam isolados. O `QueryBuilder` não herda mais de `Connection`: recebe um array de opções (resolvido pelo pool) ou uma `ConnectionInterface` injetada. Use `ConnectionManager::reset()` em testes e ao fim de cada job em workers de longa duração (o pool e as conexões vivem enquanto o processo viver). **`close()` é compartilhado:** como o pool entrega a mesma `Connection` a todos os `QueryBuilder`/models da mesma configuração, `close()` fecha o PDO de todos eles — uma transação aberta sofre rollback e a próxima consulta reconecta. Prefira `reset()` a chamar `close()` no meio da requisição.
- **Transações:** `Model::transaction()` executa um callback atomicamente — commit no sucesso; rollback e relançamento da exceção na falha. Chamadas aninhadas — e transações abertas diretamente no PDO — participam da transação externa, e o cache de sessão é ignorado dentro dela. Se o `rollBack` falhar (ex.: conexão perdida), a exceção original é relançada e o PDO quebrado é descartado.

  ```php
  Pedido::transaction(function () use ($pedido, $itens) {
      $id = $pedido->save();
      foreach ($itens as $item) {
          $item->id_pedido = $id;
          $item->save();
      }
  });
  ```
- **`limit(0)`:** passou a gerar `LIMIT 0` (zero linhas). Quem usava `0` como "sem limite" em `find()`, `all()` ou `count()` deve passar `null`.
- **`ConnectionInterface` (quebra de compatibilidade):** ganhou `executeUpdate()`, `executeDelete()`, `transaction()`, `inTransaction()`, `driver()`, `identity()` e `queryCacheEnabled()`. Implementações próprias da interface precisam desses métodos; estender `Connection` é o caminho recomendado.
- **Falhas:** a conexão faz no máximo `OLIVIA_DB_CONNECT_RETRIES` novas tentativas. Consultas não são repetidas, exceto **leituras** com uma reconexão única quando o MySQL fecha a conexão (erros 2006/2013) **fora** de transação; escritas nunca são repetidas, porque o resultado de uma escrita interrompida é desconhecido. Em qualquer perda de conexão fora de transação o PDO morto é descartado, e a próxima operação reconecta. Em `transaction()`, `beginTransaction()`, `commit()` ou `rollBack()` que retornem `false` (por exemplo com `errorMode` `PDO::ERRMODE_SILENT`) viram exceção: uma falha de commit nunca devolve o valor do callback como sucesso, a transação é desfeita e, se o rollback também falhar, o PDO é descartado. Se a aplicação abrir uma transação diretamente no PDO, cabe a ela fazer o `rollBack()` em caso de falha. `Connection::getInstance()` foi removido.
- **Cache de consultas (`OLIVIA-DB-310`):** opcional (`OLIVIA_QUERY_CACHE=session`) e particionado pela identidade da conexão (driver, host, porta, banco e usuário), então bancos ou tenants diferentes nunca compartilham resultados. SQLite em memória ou temporário nunca usa o cache, porque o banco é privado de cada PDO e some ao reconectar. Com o cache ligado, a classificação consulta o destino que o SQLite realmente abriu (`PRAGMA database_list`), então URIs com codificação percentual ou outras formas equivalentes também são reconhecidas; na dúvida, o cache fica desligado. Leituras com parâmetros não escalares (ex.: objetos `Stringable`) também vão sempre ao banco. Configurações diferentes que apontam para o mesmo banco (ex.: `localhost` e `127.0.0.1`) têm identidades diferentes e não invalidam o cache uma da outra. O cache não separa contextos de autorização dentro da mesma conexão: o resultado é do SQL e dos parâmetros, não do usuário. Qualquer escrita feita pelo `QueryBuilder` invalida **todo** o cache daquela conexão (leituras com `join`, schema qualificado ou views dependem de tabelas que a chave não nomeia); escritas de outros processos **não** são vistas até a sessão expirar — ligue o cache só para dados que toleram essa defasagem.
- **Escritas exigem `where`:** `update()`, `delete()` e `deleteFull()` (e `Model::deleteFull()`) lançam `InvalidArgumentException` com `where` ausente, `''` ou `[]` — um where vazio com `join` apagava todas as linhas alcançadas. Para afetar a tabela inteira, declare a intenção: `where('1 = 1')`. Condições em array são unidas por `AND` (ou `separator` `OR`), como no `select`; antes eram concatenadas com espaço.
- **Exclusões:** `QueryBuilder::delete()` não aceita `join` e normaliza alias para `tabela AS alias`. `deleteFull()` gera `DELETE FROM tabela [AS alias] WHERE ...` (SQLite e MySQL ≥ 8.0.16) e, com `join`, a sintaxe multi-tabela do MySQL (`DELETE alias FROM ...`), rejeitada nos demais drivers. `group`, `having`, `order` e `limit` em `update()`, `delete()` e `deleteFull()` — inclusive via `Model::deleteFull()` — lançam `InvalidArgumentException` (antes eram descartados em silêncio e a escrita atingia todo o conjunto filtrado). Para limitar uma escrita, restrinja pelo `where` (ex.: `id IN (...)`).

---

## Fluxo da aplicação e Rotas

O ponto de entrada é `index.php`, que:

1. Carrega o autoload do Composer (`vendor/autoload.php`)
2. Inicializa a configuração base da aplicação (`new OliviaLib\Config()`)
3. Executa o roteador principal (`(new OliviaLib\Route())->execute()`)

Rotas base registradas em `lib/Route.php`:

- `GET /` -> `HomeController#index`
- `GET /livez` -> `HomeController#livez` (health/liveness probe, responde `200 ok`)
- `GET /error404` -> `HomeController#error404` (responde **status HTTP 404**; o HTML do skeleton é apenas o corpo)

Não há outras rotas no kernel (as antigas rotas `/olivia-*` e as do `AuthController` foram removidas). Uma rota inexistente responde `302` para `BASE_PATH/error404` (caminho relativo ao host, sem usar o header `Host`) e encerra a requisição.

---

## Camada de views e componentes

A renderização principal utiliza as classes base:

- `publico/View/ViewModel.php`
- `publico/View/ViewModelErro.php`

Essas classes oferecem suporte a componentes reutilizáveis por meio do método `renderComponent()`.

O `<style>` de cada classe base carrega o nonce da CSP da requisição. Em views próprias, todo `<style>` ou `<script>` inline precisa do mesmo atributo, senão o navegador o bloqueia; atributos `style="..."` e handlers `onclick` não são aceitos pela CSP padrão:

```php
<style nonce="<?= htmlspecialchars(\OliviaLib\Config::cspNonce(), ENT_QUOTES, 'UTF-8') ?>">...</style>
```

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
php tests/kernel/behavior_test.php
docker build --build-arg PHP_VERSION=8.5.12-fpm-alpine3.24 --tag olivia-framework:smoke .
```

O workflow de Quality Gate (`.github/workflows/quality.yml`) valida o `composer.json`, executa lint (`php -l`) em `lib/`, `src/` e `app/`, roda as suítes de contrato (`framework_contract_test.php` e `security_contract_test.php`) e a suíte de comportamento (`behavior_test.php`) em PHP 8.5 e constrói a imagem Docker exigindo `PHP 8.5.12`, `ID=alpine` e `VERSION_ID=3.24`, as extensões `pdo_mysql`, `pdo_sqlite`, `zip`, `fileinfo`, `openssl` e `sodium`, o `php.ini` de produção (`expose_php`, `display_errors` e `display_startup_errors` desligados, `log_errors` ligado), ausência de compilador na imagem final e nenhuma resposta com `X-Powered-By`.

A `behavior_test.php` exercita o kernel de ponta a ponta: CRUD em SQLite real, reconexão e transações com dublês de PDO, roteador e CSRF, criptografia, uploads e, por HTTP real, um servidor embutido do PHP (`php -S` em `127.0.0.1`) para redirects, status 404, respostas 403 (CSRF), 405 (com `Allow`) e 500 (página genérica, log e erro fatal), cookies de sessão, headers de segurança e CSP por nonce, `RequestPost` com formulário e upload multipart. Requisitos: `pdo_sqlite`, `proc_open` habilitado e porta local livre; os checks de documentos Office/ODF que dependem de `ext-zip` mudam para o caminho de falha fechada quando a extensão não existe. A suíte parte de um ambiente limpo (variáveis `OLIVIA_*`/`DB_*` herdadas são removidas), restaura ambiente, sessão e superglobais entre grupos e deve rodar sem depreciações com `-d error_reporting=-1`.

---

## Segurança

- **CSRF e Autoridade de Sessão:** inicializado em `lib/Config.php` com token de 256 bits (`random_bytes`, 64 hex) armazenado em `$_SESSION['UUID']`; tokens no formato antigo são renovados automaticamente. O roteador valida o CSRF **antes** de qualquer middleware (uma requisição forjada não dispara efeitos de middlewares), compara o token com `hash_equals`, aceita o campo `_token` do formulário ou, quando ele está ausente ou vazio, o header `X-CSRF-Token` (AJAX/JSON). Um `_token` presente com tipo inválido (`null`, array, número; ex.: `_token[]=x`) é rejeitado sem consultar o header; o header só é usado quando a chave está ausente ou é uma string vazia. Nunca valida quando o token esperado está vazio. O middleware `RequestPost` segue as mesmas regras. Após login, logout ou troca de privilégio chame `OliviaLib\Config::regenerateSession()`, que renova o ID de sessão e o token. O roteador lê CSRF, namespace e pastas **somente da sessão** (ou de um `RouterConfig` explícito); cookies `OLIVIA_*` não desativam CSRF nem alteram o namespace de controllers/middlewares.
- **Proteção de URLs:** `csrf_field()` injeta o campo oculto `_token` para formulários `POST`; `route()` e `redirect()` não expõem o token na URL.
- **Halt de Middleware:** middlewares que retornam `false` ou lançam `OliviaRouter\MiddlewareHaltException` interrompem imediatamente o despacho antes da execução do controller.
- **MySQL TLS/SSL:** conexões MySQL remotas assumem `ssl=true` por padrão (`OLIVIA_DB_SSL` / `DB_SSL`) com verificação estrita de certificado CA. Desligar TLS para host remoto exige também `OLIVIA_DB_ALLOW_INSECURE=true` (`OLIVIA-SEC-316`); sem a flag, `getDB()` lança `RuntimeException`. **Migração:** quem usava `DB_SSL=false` com host remoto (inclusive nome de serviço do Docker) precisa adicionar a flag ou ligar o TLS.
- **Senha do banco (`OLIVIA-SEC-316`):** além de `DB_PASSWORD`, aceita `DB_PASSWORD_FILE` (secret em arquivo). A `Connection` guarda a senha fora das opções: `var_dump`/`print_r` mostram `********` (`__debugInfo`), `var_export` e o cast para array não a alcançam, e a conexão não pode ser serializada. `Connection::__construct` e `ConnectionManager::get()` marcam as opções com `#[\SensitiveParameter]`, então a senha não aparece nos argumentos de stack traces; o PDO já protege a sua. **Limite conhecido:** `getDB()` continua devolvendo o array de configuração com a senha em texto (compatibilidade da API); não registre nem despeje esse array. Ele vai direto para o `QueryBuilder`, cujo frame ainda mostra o array em `getTrace()` quando `zend.exception_ignore_args` está desligado (o `php.ini` de produção da imagem o liga).
- **SQL estrutural no `QueryBuilder`:** `table()`, `fields()`, `group()`, `order()` e `limit()` só aceitam identificadores (`coluna`, `tabela.coluna`, `` `coluna` ``, alias com/sem `AS`, `*`, `tabela.*`, agregados `COUNT/SUM/AVG/MIN/MAX` com `DISTINCT` opcional), direção `ASC`/`DESC` e limites inteiros (`N`, `offset, N`, `N OFFSET M`, `[offset, N]`, inclusive `0`); qualquer outra entrada lança `InvalidArgumentException`. Em cláusulas passadas como array, os metadados `instruction` e `separator` só aceitam os valores padrão (`ORDER BY`/`GROUP BY`/`LIMIT`/`WHERE`/`HAVING`; `,` ou `AND`/`OR`) — antes permitiam injetar SQL estrutural (`OLIVIA-DB-310`). `where()`, `join()`, `having()` e `pre()` continuam sendo SQL do desenvolvedor e **devem** usar placeholders `?` para valores do usuário. Expressões confiáveis fora do padrão usam `OliviaDatabaseLibrary\Raw::sql('...')` — nunca com entrada do usuário.
- **Atribuição em massa:** `Model::request()` e `request_cripto()` preenchem apenas `$fillable` menos `$guarded` (padrão: `id`, `password`, `senha`, `remember_token`, `tipo`, `subTipo`, `status`, `role`, `roles`, `is_admin`, `admin`, `is_superuser`, `superuser`, `permissoes`, `permissions`, `nivel`, `perfil`, `privilegios` e timestamps). Para aceitar um campo protegido, passe a lista explícita — `request(['nome', 'password'])` — ou sobrescreva `$guarded` no model.
- **Headers de segurança (`OLIVIA-SEC-320`):** `Config` envia em toda resposta, pelo próprio PHP (vale para Apache, nginx/PHP-FPM e `php -S`): `Content-Security-Policy`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: same-origin` e `Permissions-Policy` (câmera, microfone, geolocalização, pagamento e USB desligados); `X-Powered-By` é removido. `Strict-Transport-Security: max-age=31536000` só sai quando `Config::isSecureRequest()` é verdadeiro. A CSP padrão (`Config::contentSecurityPolicy()`) aceita só a própria origem (`default-src 'self'`, imagens e fontes também `data:`), libera `<style>`/`<script>` inline apenas com o nonce da requisição (`Config::cspNonce()`, 128 bits, novo a cada requisição) e define `object-src 'none'`, `base-uri 'self'`, `form-action 'self'` e `frame-ancestors 'none'`; não há fontes `http:`/`https:` amplas nem `'unsafe-inline'`. Para outra política (CDN, por exemplo), chame `header('Content-Security-Policy: ...')` depois do `Config`: o valor substitui o padrão. O `.htaccess` não repete esses headers nas páginas: dentro de `<IfModule mod_headers.c>` (fora do bloco de `mod_rewrite`) usa `Header setifempty`, que só completa arquivos estáticos servidos pelo Apache, e remove `X-Powered-By`. `X-XSS-Protection` (obsoleto) e HSTS em HTTP puro foram retirados.
- **Mensagens ao usuário:** `msgbox()` (JavaScript inline, bloqueado pela CSP) foi removido. Use `$this->flash('Salvo!', 'sucesso'); $this->redirect('lista');` e, na view de destino, `pullFlash()` — escapando a mensagem com `htmlspecialchars` ao renderizar.
- **Erros HTTP (`OLIVIA-SEC-322`):** o `index.php` chama `OliviaLib\ErrorHandler::register()` antes do `Config`. O handler abre um buffer de saída (a saída parcial da página é descartada quando algo falha) e responde:
  - **403** para `OliviaRouter\CsrfException`, a exceção dedicada lançada pelo roteador quando o token CSRF falta ou não confere (estende `RuntimeException`, então quem a capturava continua funcionando);
  - **405** com header `Allow` para `OliviaRouter\MethodNotAllowedException`, lançada quando o caminho existe só com outros métodos (ex.: `POST /` com `Allow: GET, HEAD`); caminho inexistente continua no fluxo de 404. `HEAD` usa a rota `GET` do mesmo caminho quando não tem rota própria. A resposta contém apenas status e headers, inclusive em caso de erro. Por isso, todo caminho com `GET` anuncia `HEAD` no `Allow`;
  - **500** para qualquer outra exceção e para erros fatais (pelo `register_shutdown_function`), sempre registrando classe, mensagem, arquivo:linha, rota e trace com `error_log`.

  A página (`publico/E500.php`, com o componente `ErrorState` recebendo o código) é genérica e leva os headers de segurança e o nonce da CSP; classe, mensagem e trace só aparecem, escapados, com `APP_DEBUG=true`. Clientes que pedem JSON (`Accept` sem `text/html`, ou `X-Requested-With: XMLHttpRequest`) recebem `{"status":403,"error":"Acesso negado","message":"..."}`. O `Config` não altera mais o `memory_limit` (antes `-1`, ilimitado): vale o limite do `php.ini` ou do ambiente.
- **Redirect de 404:** `lib/Route.php` redireciona para um caminho relativo (`BASE_PATH/error404`) e encerra a requisição; o header `Host`, controlado pelo cliente, não é usado. A página `/error404` responde com status HTTP 404.
- **Sessão e Erros:** o cookie de sessão usa `HttpOnly`, `SameSite=Lax`, `session.use_strict_mode` e `session.use_only_cookies`; `Secure` é ativado em HTTPS (detectado por `HTTPS`, porta 443 ou `X-Forwarded-Proto` vindo de um proxy listado em `OLIVIA_TRUSTED_PROXIES`) ou forçado com `OLIVIA_FORCE_SECURE=true`. `display_errors` e `display_startup_errors` permanecem desligados por padrão e só são ativados quando `APP_DEBUG=true`.

Se você descobrir uma vulnerabilidade de segurança no framework, entre em contato com Elzo Brito pelo e-mail `elzo.santos@cps.sp.gov.br`.

---

## Dependências de terceiros

As bibliotecas Olivia residem *in-tree* em `src/`. Dependências externas gerenciadas via Composer:

- `ramsey/uuid` (`^4.0`)
- `fpdf/fpdf` (`^1.82`)

---

## Licença

Olivia Framework é um software de código aberto licenciado sob a licença MIT.
