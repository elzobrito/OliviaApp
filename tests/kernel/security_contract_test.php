<?php

namespace OliviaApp\Controller {
    class SecurityProbeController
    {
        public static $called = false;

        public function index($params)
        {
            self::$called = true;
        }
    }
}

namespace OliviaApp\Middleware {
    class SecurityHaltFalse
    {
        public function handle()
        {
            return false;
        }
    }

    class SecurityHaltThrow
    {
        public function handle()
        {
            throw new \OliviaRouter\MiddlewareHaltException('denied');
        }
    }
}

namespace {
    $root = dirname(__DIR__, 2);
    $failures = 0;

    function assert_true($condition, $message)
    {
        global $failures;
        if ($condition) {
            echo "OK   {$message}\n";
            return;
        }
        $failures++;
        echo "FAIL {$message}\n";
    }

    require $root . '/vendor/autoload.php';

    foreach (['OLIVIA_DB_DSN', 'DB_DRIVER', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_USER', 'DB_PASSWORD', 'OLIVIA_DB_SSL', 'DB_SSL', 'OLIVIA_DB_SSL_CA', 'DB_SSL_CA'] as $envName) {
        putenv($envName);
        unset($_ENV[$envName], $_SERVER[$envName]);
    }

    $_SESSION = [
        'CSRF' => true,
        'UUID' => 'session-token',
        'App_folder' => 'OliviaApp',
        'Controller_folder' => 'Controller',
        'Middleware_folder' => 'Middleware',
        'BASENAME' => '',
    ];
    $_COOKIE = [
        'OLIVIA_CSRF' => '0',
        'OLIVIA_CSRF_TOKEN' => 'attacker',
        'OLIVIA_APP_NAMESPACE' => 'Evil',
        'OLIVIA_CONTROLLER_FOLDER' => 'Pwn',
        'OLIVIA_MIDDLEWARE_FOLDER' => 'Pwn',
        'OLIVIA_BASE_PATH' => 'evil',
    ];

    $config = \OliviaRouter\RouterConfig::fromGlobals();
    assert_true($config->isCsrfEnabled() === true, 'session CSRF stays enabled despite OLIVIA_CSRF cookie');
    assert_true($config->getCsrfToken() === 'session-token', 'CSRF token comes from session UUID');
    assert_true($config->getAppNamespace() === 'OliviaApp', 'namespace comes from session, not cookie');
    assert_true($config->getControllerFolder() === 'Controller', 'controller folder comes from session, not cookie');
    assert_true($config->getBasePath() === '', 'base path ignores OLIVIA_BASE_PATH cookie');

    $router = new \OliviaRouter\Router($config);
    $router->post('/probe', 'SecurityProbeController#index');
    \OliviaApp\Controller\SecurityProbeController::$called = false;
    $csrfBlocked = false;
    try {
        $router->execute(new \OliviaRouter\Request('POST', '/probe', ['_token' => 'wrong']));
    } catch (\RuntimeException $exception) {
        $csrfBlocked = strpos($exception->getMessage(), 'CSRF') !== false;
    }
    assert_true($csrfBlocked, 'POST with invalid token is blocked while cookies try to disable CSRF');
    assert_true(\OliviaApp\Controller\SecurityProbeController::$called === false, 'handler does not run on invalid CSRF');

    $routerOk = new \OliviaRouter\Router($config);
    $routerOk->post('/probe', 'SecurityProbeController#index');
    \OliviaApp\Controller\SecurityProbeController::$called = false;
    $routerOk->execute(new \OliviaRouter\Request('POST', '/probe', ['_token' => 'session-token']));
    assert_true(\OliviaApp\Controller\SecurityProbeController::$called === true, 'POST with session token reaches handler');

    \OliviaApp\Controller\SecurityProbeController::$called = false;
    $haltRouter = new \OliviaRouter\Router($config);
    $haltRouter->middleware('SecurityHaltFalse#handle')->get('/halt-false', 'SecurityProbeController#index');
    $haltRouter->execute(new \OliviaRouter\Request('GET', '/halt-false'));
    assert_true(\OliviaApp\Controller\SecurityProbeController::$called === false, 'middleware returning false skips handler');

    \OliviaApp\Controller\SecurityProbeController::$called = false;
    $throwRouter = new \OliviaRouter\Router($config);
    $throwRouter->middleware('SecurityHaltThrow#handle')->get('/halt-throw', 'SecurityProbeController#index');
    $throwRouter->execute(new \OliviaRouter\Request('GET', '/halt-throw'));
    assert_true(\OliviaApp\Controller\SecurityProbeController::$called === false, 'MiddlewareHaltException skips handler');

    $controller = new \OliviaLib\CommandController();
    $url = $controller->route('admin', ['id' => '1']);
    assert_true(strpos($url, '_token') === false, 'route() does not put CSRF token in the URL');
    assert_true(strpos($url, 'id=1') !== false, 'route() keeps caller parameters');

    $nonSslRemote = new \OliviaDatabaseLibrary\Connection([
        'driver' => 'mysql',
        'host' => 'db.example.com',
        'user' => 'olivia',
        'password' => 'secret',
        'ssl' => false,
    ]);
    // Mesma resolução do Connection: Pdo\Mysql::ATTR_* (PHP 8.4+), PDO::MYSQL_ATTR_* antigo, ou o valor
    // numérico quando pdo_mysql não está carregado. Evita depreciações no 8.5 e erro fatal sem pdo_mysql.
    $mysqlAttr = static function (string $nome, int $padrao): int {
        if (defined('Pdo\\Mysql::ATTR_' . $nome)) {
            return constant('Pdo\\Mysql::ATTR_' . $nome);
        }
        return defined('PDO::MYSQL_ATTR_' . $nome) ? constant('PDO::MYSQL_ATTR_' . $nome) : $padrao;
    };
    $sslVerifyAttr = $mysqlAttr('SSL_VERIFY_SERVER_CERT', 1014);
    $sslCaAttr = $mysqlAttr('SSL_CA', 1012);
    $nonSslOptions = $nonSslRemote->mysqlClientOptions();
    assert_true($nonSslOptions[$sslVerifyAttr] === false, 'explicit ssl=false disables cert verify');
    assert_true(!array_key_exists($sslCaAttr, $nonSslOptions), 'ssl=false does not set MYSQL_ATTR_SSL_CA');
    $mandatoryTls = false;
    try {
        $nonSslRemote->mysqlClientOptions();
    } catch (\InvalidArgumentException $exception) {
        $mandatoryTls = strpos($exception->getMessage(), 'Remote MySQL requires ssl=true') !== false;
    }
    assert_true($mandatoryTls === false, 'remote ssl=false does not throw mandatory TLS exception');

    $local = new \OliviaDatabaseLibrary\Connection([
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'user' => 'olivia',
        'password' => 'secret',
        'ssl' => false,
    ]);
    $localOptions = $local->mysqlClientOptions();
    assert_true($localOptions[$sslVerifyAttr] === false, 'loopback MySQL may disable cert verify');

    $caFile = $root . '/src/database/BaltimoreCyberTrustRoot.crt.pem';
    assert_true(is_file($caFile), 'bundled TLS CA exists');
    $remote = new \OliviaDatabaseLibrary\Connection([
        'driver' => 'mysql',
        'host' => 'db.example.com',
        'user' => 'olivia',
        'password' => 'secret',
        'ssl' => true,
        'ssl_ca' => $caFile,
    ]);
    $remoteOptions = $remote->mysqlClientOptions();
    assert_true($remoteOptions[$sslVerifyAttr] === true, 'remote MySQL verifies TLS certificate');
    assert_true(($remoteOptions[$sslCaAttr] ?? '') === $caFile, 'remote MySQL uses configured CA');

    $missingCa = false;
    try {
        $broken = new \OliviaDatabaseLibrary\Connection([
            'driver' => 'mysql',
            'host' => 'db.example.com',
            'user' => 'olivia',
            'password' => 'secret',
            'ssl' => true,
            'ssl_ca' => '/tmp/olivia-missing-ca.pem',
        ]);
        $broken->mysqlClientOptions();
    } catch (\InvalidArgumentException $exception) {
        $missingCa = strpos($exception->getMessage(), 'CA certificate') !== false;
    }
    assert_true($missingCa, 'ssl=true without CA file is rejected');

    putenv('DB_USERNAME=olivia');
    putenv('OLIVIA_DB_DSN=mysql:host=127.0.0.1;port=3306;dbname=olivia');
    $loopbackDb = \OliviaLib\Database::getDB();
    assert_true($loopbackDb['ssl'] === false, 'loopback DSN defaults ssl=false');

    putenv('OLIVIA_DB_DSN=mysql:host=db.example.com;port=3306;dbname=olivia');
    $remoteDb = \OliviaLib\Database::getDB();
    assert_true($remoteDb['ssl'] === true, 'remote DSN defaults ssl=true');

    putenv('OLIVIA_DB_DSN');
    putenv('DB_DRIVER=mysql');
    putenv('DB_DATABASE=olivia');
    $semHost = '';
    try {
        \OliviaLib\Database::getDB();
    } catch (\RuntimeException $exception) {
        $semHost = $exception->getMessage();
    }
    assert_true($semHost === 'Database host is not configured: set DB_HOST or host in OLIVIA_DB_DSN.', 'mysql without host fails closed (no implicit 127.0.0.1)');
    foreach (['DB_USERNAME', 'DB_DRIVER', 'DB_DATABASE'] as $envName) {
        putenv($envName);
    }

    // OLIVIA-SEC-320: .htaccess sem CSP permissiva nem headers presos ao mod_rewrite
    $htaccess = (string) file_get_contents($root . '/.htaccess');
    $blocoRewrite = preg_match('/<IfModule mod_rewrite\.c>(.*?)<\/IfModule>/s', $htaccess, $m) === 1 ? $m[1] : '';
    assert_true($blocoRewrite !== '' && stripos($blocoRewrite, 'Header ') === false, '.htaccess has no Header directive inside the mod_rewrite block');
    $blocoHeaders = preg_match('/<IfModule mod_headers\.c>(.*?)<\/IfModule>/s', $htaccess, $m) === 1 ? $m[1] : '';
    $semComentarios = preg_replace('/^\s*#.*$/m', '', $htaccess);
    assert_true($blocoHeaders !== '' && preg_match_all('/^\s*Header\s/m', (string) $semComentarios) === preg_match_all('/^\s*Header\s/m', $blocoHeaders), '.htaccess Header directives are all guarded by IfModule mod_headers');
    assert_true(preg_match('/^\s*Header\s+(?!setifempty\b|always\s+unset\b)/m', $blocoHeaders) !== 1, '.htaccess only sets headers with setifempty (no duplicates of PHP headers)');
    assert_true(stripos($htaccess, 'X-XSS-Protection') === false && stripos($htaccess, 'Strict-Transport-Security') === false, '.htaccess drops X-XSS-Protection and HSTS over plain HTTP');
    assert_true(preg_match("/(?<![\w-])(https?|data):(?=[\s;\"])/", (string) preg_replace('/(img|font)-src[^;\"]*/', '', $htaccess)) !== 1 && stripos($htaccess, 'unsafe-inline') === false, '.htaccess CSP has no broad scheme sources nor unsafe-inline');

    $configSource = (string) file_get_contents($root . '/lib/Config.php');
    assert_true(strpos($configSource, 'self::sendSecurityHeaders();') !== false, 'Config sends security headers on every request');
    foreach (['publico/View/ViewModel.php', 'publico/View/ViewModelErro.php'] as $viewFile) {
        $viewSource = (string) file_get_contents($root . '/' . $viewFile);
        assert_true(strpos($viewSource, '<style nonce="<?= htmlspecialchars(\\OliviaLib\\Config::cspNonce()') !== false && substr_count($viewSource, '<style') === 1, "{$viewFile} inline style carries the CSP nonce");
    }

    // OLIVIA-SEC-322: tratamento global de erros e memory_limit do ambiente
    assert_true(strpos($configSource, 'memory_limit') === false || preg_match("/ini_set\\(\\s*'memory_limit'/", $configSource) !== 1, 'Config does not change memory_limit');
    $indexSource = (string) file_get_contents($root . '/index.php');
    $posHandler = strpos($indexSource, 'ErrorHandler::register();');
    assert_true($posHandler !== false && $posHandler < strpos($indexSource, 'new Config();'), 'index.php registers ErrorHandler before Config');
    $dispatcherSource = (string) file_get_contents($root . '/src/router/RouterDispatcher.php');
    assert_true(substr_count($dispatcherSource, 'throw new CsrfException();') === 2 && strpos($dispatcherSource, "RuntimeException('CSRF") === false, 'CSRF failures throw the dedicated CsrfException');
    assert_true(is_subclass_of(\OliviaRouter\CsrfException::class, \RuntimeException::class) && is_subclass_of(\OliviaRouter\MethodNotAllowedException::class, \RuntimeException::class), 'router HTTP exceptions stay RuntimeException-compatible');

    // OLIVIA-QA-317: exemplos de configuração sem segredo e .env fora do git
    $gitignore = preg_split('/\R/', (string) file_get_contents($root . '/.gitignore'));
    assert_true(in_array('.env', $gitignore, true) && in_array('.env.*', $gitignore, true) && in_array('!.env.example', $gitignore, true) && in_array('secrets/', $gitignore, true), '.gitignore ignores .env, .env.* and secrets/ but keeps .env.example');
    $envExample = (string) file_get_contents($root . '/.env.example');
    assert_true(preg_match('/^(?:[A-Z]+_)?(?:DB_PASSWORD|OLIVIA_SMTP_PASSWORD|OLIVIA_APP_KEY)=\S/m', $envExample) !== 1, '.env.example has no real secret value');
    foreach (['OLIVIA_DB_DSN=', 'DB_USERNAME=', 'DB_PASSWORD_FILE=', 'OLIVIA_DB_SSL_CA=', 'OLIVIA_DB_ALLOW_INSECURE=', 'MIG_DB_USERNAME=', 'MIG_OLIVIA_DB_DSN='] as $envKey) {
        assert_true(strpos($envExample, $envKey) !== false, ".env.example documents {$envKey}");
    }
    $compose = (string) file_get_contents($root . '/docker-compose.example.yml');
    assert_true(strpos($compose, 'DB_PASSWORD_FILE: /run/secrets/db_password') !== false && strpos($compose, 'MYSQL_PASSWORD_FILE: /run/secrets/db_password') !== false && strpos($compose, 'file: ./secrets/db_password.txt') !== false, 'compose example reads the password from a secret file');
    assert_true(preg_match('/^\s*(?:DB_PASSWORD|MYSQL_PASSWORD|MYSQL_ROOT_PASSWORD)\s*:/m', $compose) !== 1, 'compose example has no inline password');
    $readmeQa = (string) file_get_contents($root . '/README.md');
    assert_true(strpos($readmeQa, '(.env.example)') !== false && strpos($readmeQa, '(docker-compose.example.yml)') !== false && strpos($readmeQa, '### Guia de migração da configuração de banco') !== false, 'README links both examples and the migration guide');
    assert_true(strpos($readmeQa, "GRANT SELECT, INSERT, UPDATE, DELETE ON olivia.* TO 'olivia_app'") !== false && strpos($readmeQa, "fromEnvironment('MIG_')") !== false, 'README recommends least privilege and a separate migrations user');

    if ($failures > 0) {
        fwrite(STDERR, "security contract failed: {$failures} assertion(s)\n");
        exit(1);
    }

    echo "security contract passed\n";
    exit(0);
}
