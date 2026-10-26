# Correções de segurança do olivia-framework (5ace265)

**Tarefa:** OLIVIA-FW-SEC-001  
**Revisão analisada:** `5ace265bce3306b2784846506aa563f72481bda7`  
**Runtime obrigatório:** `php:8.5.12-fpm-alpine3.24`  
**Fora de escopo:** Horus; Packagist; reescrever SHA `57eb048`; merge em `master`

## Origem

Security Review estática do repositório OliviaApp: 4 achados (2 medium, 2 low). Este spec é o contrato de correção.

## Finding 1 — CSRF e autoridade de roteamento (CWE-352)

`RouterConfig::fromGlobals()` / `fromRequest()` montam `FallbackContextStore(Cookie, Session)`. Cookies `OLIVIA_CSRF`, `OLIVIA_CSRF_TOKEN`, `OLIVIA_APP_NAMESPACE`, `OLIVIA_CONTROLLER_FOLDER`, `OLIVIA_MIDDLEWARE_FOLDER` e `OLIVIA_BASE_PATH` vencem a sessão. `Router::execute()` reconstrói o config a partir do request e descarta o `RouterConfig` do construtor. `validateCsrf()` retorna se `isCsrfEnabled()` for falso.

### Contrato

1. `RouterConfig::fromGlobals()`, `fromRequest()` e `fromRequestData()` leem **somente** `SessionContextStore` (chaves de sessão `CSRF`, `UUID`, `App_folder`, `Controller_folder`, `Middleware_folder`, `BASENAME`). Cookies não alimentam o config.
2. `Router::execute()` reutiliza `$this->config` do construtor. Não reconstrói a partir de cookies do request.
3. Quem precisa de config estável passa `new Router($config)` explícito.
4. CookieContextStore permanece no pacote para outros usos; deixa de ser fonte de CSRF/namespace/pastas.

### Teste

Com `$_SESSION['CSRF']=true` e `$_SESSION['UUID']='session-token'`, cookies `OLIVIA_CSRF=0` e `OLIVIA_CSRF_TOKEN=attacker` não desligam CSRF nem trocam o token. POST/PUT/PATCH/DELETE sem `_token` válido lançam `RuntimeException`.

## Finding 2 — Middleware não interrompe o handler (CWE-863)

`executeMiddlewares()` ignora o retorno. `RequestPost::index()` envia `Location` e retorna; o dispatcher segue para o handler.

### Contrato

1. Nova exceção `OliviaRouter\MiddlewareHaltException`.
2. Se o método do middleware retorna `false` **ou** lança `MiddlewareHaltException`, o dispatcher marca a rota como casada, não chama o handler e retorna.
3. `RequestPost::index()` em token inválido envia `Location` e lança `MiddlewareHaltException`.

### Teste

Registrar middleware que retorna `false` (e outro que lança halt). O handler não executa.

## Finding 3 — Token CSRF em URL (CWE-598)

`CommandController::view()` e `route()` acrescentam `_token` à query.

### Contrato

1. `view()` e `route()` não incluem `_token`.
2. `csrf_field()` e `csrf_token()` permanecem para formulários e comparação POST.

### Teste

Com `$_SESSION['CSRF']=true` e UUID definido, `route('admin', ['id'=>1])` não contém `_token`.

## Finding 4 — TLS MySQL sem verificação (CWE-295)

`Connection` define `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false` quando `ssl` não é true. `OliviaLib\Database` não monta `ssl`.

### Contrato

1. `OliviaLib\Database::getDB()` inclui `ssl` (bool) e `ssl_ca` (string, opcional).
2. `ssl` vem de `OLIVIA_DB_SSL` ou `DB_SSL`. Default: `false` em loopback (`127.0.0.1`, `localhost`, `::1`, host vazio) e SQLite; `true` em host MySQL não-loopback.
3. `ssl_ca` vem de `OLIVIA_DB_SSL_CA` ou `DB_SSL_CA`. Vazio usa o PEM empacotado em `src/database/`.
4. `Connection`: MySQL não-loopback com `ssl` falso lança `InvalidArgumentException` no construtor. Com `ssl` true, `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT` é true e o arquivo CA precisa existir; ausência de CA lança `InvalidArgumentException`. Loopback com `ssl` falso mantém verify false (desenvolvimento).
5. SQLite não aplica atributos MySQL SSL.

### Teste

- Host `db.example.com` + `ssl=false` → exceção.
- Host `127.0.0.1` + `ssl=false` → opções com verify false.
- Host remoto + `ssl=true` + CA existente → verify true e `MYSQL_ATTR_SSL_CA`.
- Host remoto + `ssl=true` + `ssl_ca` inexistente → exceção.
- `getDB()` com `OLIVIA_DB_DSN` mysql em `127.0.0.1` sem env SSL → `ssl=false`.
- `getDB()` com host `db.example.com` sem env SSL → `ssl=true`.

## Pacote e CI

- Versão Composer raiz: `COMPOSER_ROOT_VERSION=0.2.1` (Dockerfile).
- Quality Gate executa `tests/kernel/framework_contract_test.php` e `tests/kernel/security_contract_test.php`.
- Imagem continua `php:8.5.12-fpm-alpine3.24`.

## ATRIO

Depois do push desta branch: `ATRIO-OLIVIA-FW-SEC-040` atualiza o `type: package` para o novo SHA e versão `0.2.1`. Hub `Config`/`Database`/`Route` permanecem. Inventário 185. Sem merge em `main` sem aceite AKS.

## Fora de escopo

- Horus.
- QueryBuilder (cláusulas estruturais) e autorização de Upload/Download.
- Exploração em implantação e cookie tossing em subdomínio CPS.
