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

    $remoteRejected = false;
    try {
        new \OliviaDatabaseLibrary\Connection([
            'driver' => 'mysql',
            'host' => 'db.example.com',
            'user' => 'olivia',
            'password' => 'secret',
            'ssl' => false,
        ]);
    } catch (\InvalidArgumentException $exception) {
        $remoteRejected = strpos($exception->getMessage(), 'ssl=true') !== false;
    }
    assert_true($remoteRejected, 'remote MySQL without ssl is rejected');

    $local = new \OliviaDatabaseLibrary\Connection([
        'driver' => 'mysql',
        'host' => '127.0.0.1',
        'user' => 'olivia',
        'password' => 'secret',
        'ssl' => false,
    ]);
    $localOptions = $local->mysqlClientOptions();
    assert_true($localOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] === false, 'loopback MySQL may disable cert verify');

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
    assert_true($remoteOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] === true, 'remote MySQL verifies TLS certificate');
    assert_true(($remoteOptions[PDO::MYSQL_ATTR_SSL_CA] ?? '') === $caFile, 'remote MySQL uses configured CA');

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

    putenv('OLIVIA_DB_DSN=mysql:host=127.0.0.1;port=3306;dbname=olivia');
    $loopbackDb = \OliviaLib\Database::getDB();
    assert_true($loopbackDb['ssl'] === false, 'loopback DSN defaults ssl=false');

    putenv('OLIVIA_DB_DSN=mysql:host=db.example.com;port=3306;dbname=olivia');
    $remoteDb = \OliviaLib\Database::getDB();
    assert_true($remoteDb['ssl'] === true, 'remote DSN defaults ssl=true');

    if ($failures > 0) {
        fwrite(STDERR, "security contract failed: {$failures} assertion(s)\n");
        exit(1);
    }

    echo "security contract passed\n";
    exit(0);
}
