<?php

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

$composerPath = $root . '/composer.json';
assert_true(is_file($composerPath), 'composer.json exists');
$composer = json_decode((string) file_get_contents($composerPath), true);
assert_true(is_array($composer), 'composer.json is valid JSON');
assert_true(($composer['name'] ?? '') === 'elzobrito/olivia-framework', 'package name is elzobrito/olivia-framework');
assert_true(($composer['require']['php'] ?? '') === '>=8.2 <8.6', 'php constraint is >=8.2 <8.6');
assert_true(($composer['autoload']['psr-4']['OliviaLib\\'] ?? '') === 'lib/', 'OliviaLib autoload maps to lib/');

$elzobritoRequires = array_filter(
    array_keys($composer['require'] ?? []),
    static fn($name) => strpos($name, 'elzobrito/olivia-') === 0
);
assert_true($elzobritoRequires === [], 'require has no elzobrito/olivia-* packages');
assert_true(($composer['require']['ramsey/uuid'] ?? '') !== '', 'ramsey/uuid remains a third-party require');
assert_true(($composer['require']['fpdf/fpdf'] ?? '') !== '', 'fpdf/fpdf remains a third-party require');

$replaced = [
    'elzobrito/olivia-router',
    'elzobrito/olivia-pagination',
    'elzobrito/olivia-pdf',
    'elzobrito/olivia-upload-file',
    'elzobrito/olivia-force-download',
    'elzobrito/olivia-databaselibrary',
    'elzobrito/olivia-cache',
];
foreach ($replaced as $packageName) {
    assert_true(isset($composer['replace'][$packageName]), "replace covers {$packageName}");
}

$psr4 = $composer['autoload']['psr-4'] ?? [];
assert_true(($psr4['OliviaRouter\\'] ?? '') === 'src/router/', 'OliviaRouter maps to src/router/');
assert_true(($psr4['OliviaDatabaseLibrary\\'] ?? '') === 'src/database/', 'OliviaDatabaseLibrary maps to src/database/');
assert_true(is_file($root . '/src/router/ContextStoreInterface.php'), 'in-tree router is 2.4 (ContextStoreInterface)');
assert_true(is_file($root . '/src/download/Download.php'), 'in-tree force-download exists');
assert_true(is_file($root . '/src/database/ADatabase.php'), 'in-tree databaselibrary exists');
assert_true(is_file($root . '/src/cache/SessionCache.php'), 'in-tree cache exists');

foreach (['CommandController.php', 'Email.php', 'Config.php', 'Database.php', 'Route.php'] as $kernelFile) {
    $path = $root . '/lib/' . $kernelFile;
    $source = is_file($path) ? (string) file_get_contents($path) : '';
    assert_true($source !== '', "lib/{$kernelFile} exists");
    assert_true(strpos($source, 'namespace OliviaLib;') !== false, "lib/{$kernelFile} declares OliviaLib");
}

$config = (string) file_get_contents($root . '/lib/Config.php');
assert_true(strpos($config, 'UserSessionService') === false, 'Config has no UserSessionService');
assert_true(strpos($config, 'UserIdentityService') === false, 'Config has no UserIdentityService');
assert_true(strpos($config, "getenv('APP_DEBUG')") !== false, 'Config honors APP_DEBUG');
assert_true(strpos($config, 'parse_url') !== false, 'Config sanitizes BASE_PATH via parse_url');

$database = (string) file_get_contents($root . '/lib/Database.php') . (string) file_get_contents($root . '/src/database/DatabaseConfig.php');
assert_true(strpos($database, 'HUB_OIDC_DB_DSN') === false, 'Database has no HUB_OIDC_DB_DSN');
assert_true(strpos($database, 'naufragio') === false, 'Database has no Hub default database name');

$dockerfile = (string) file_get_contents($root . '/Dockerfile');
assert_true(strpos($dockerfile, 'PHP_VERSION=8.5.12-fpm-alpine3.24') !== false, 'Dockerfile pins php:8.5.12-fpm-alpine3.24');
assert_true(preg_match('/docker-php-ext-install[^\n]*\bpdo_mysql\b[^\n]*\bzip\b/', $dockerfile) === 1, 'Dockerfile installs pdo_mysql and zip');
assert_true(strpos($dockerfile, 'apk del --no-network .olivia-build-deps') !== false, 'Dockerfile removes build toolchain in the same layer');
foreach (['pdo_mysql', 'pdo_sqlite', 'zip', 'fileinfo', 'openssl', 'sodium'] as $requiredExtension) {
    assert_true(strpos($dockerfile, '"' . $requiredExtension . '"') !== false, "Dockerfile asserts extension {$requiredExtension} at build time");
}
assert_true(strpos($dockerfile, 'php.ini-production') !== false, 'Dockerfile enables php.ini-production');
assert_true(strpos($dockerfile, 'COPY docker/php/production.ini $PHP_INI_DIR/conf.d/zz-olivia-production.ini') !== false, 'Dockerfile installs docker/php/production.ini');

$productionIni = is_file($root . '/docker/php/production.ini') ? (string) file_get_contents($root . '/docker/php/production.ini') : '';
foreach (['expose_php = Off', 'display_errors = Off', 'display_startup_errors = Off', 'log_errors = On'] as $iniLine) {
    assert_true(preg_match('/^' . preg_quote($iniLine, '/') . '$/m', $productionIni) === 1, "production.ini sets {$iniLine}");
}

$workflow = (string) file_get_contents($root . '/.github/workflows/quality.yml');
assert_true(strpos($workflow, 'for ext in pdo_mysql pdo_sqlite zip fileinfo openssl sodium') !== false, 'CI asserts required extensions in the image');
assert_true(stripos($workflow, 'X-Powered-By header present') !== false, 'CI asserts no X-Powered-By header');

$route = (string) file_get_contents($root . '/lib/Route.php');
assert_true(strpos($route, "/livez") !== false, 'Route registers /livez');

$home = (string) file_get_contents($root . '/app/Controller/HomeController.php');
assert_true(strpos($home, 'function livez') !== false, 'HomeController implements livez');

$forbidden = ['HUB_OIDC', 'UserSessionService', 'cpsrepositorio'];
foreach (['lib', 'src'] as $tree) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $tree, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $contents = (string) file_get_contents($file->getPathname());
        foreach ($forbidden as $needle) {
            assert_true(
                strpos($contents, $needle) === false,
                $tree . '/' . basename($file->getPathname()) . " has no {$needle}"
            );
        }
    }
}

require $root . '/vendor/autoload.php';

assert_true(class_exists('OliviaRouter\\Router'), 'OliviaRouter\\Router autoloads from src/');
assert_true(interface_exists('OliviaRouter\\ContextStoreInterface'), 'OliviaRouter\\ContextStoreInterface autoloads');
assert_true(class_exists('OliviaDownload\\Download'), 'OliviaDownload\\Download autoloads from src/');
assert_true(class_exists('OliviaDatabaseLibrary\\ADatabase'), 'OliviaDatabaseLibrary\\ADatabase autoloads from src/');
assert_true(class_exists('OliviaCache\\SessionCache'), 'OliviaCache\\SessionCache autoloads from src/');
$routerFile = (new ReflectionClass('OliviaRouter\\Router'))->getFileName();
assert_true(strpos($routerFile, '/src/router/Router.php') !== false, 'Router class file is in src/router');

foreach (['OLIVIA_DB_DSN', 'DB_DRIVER', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_USER', 'DB_PASSWORD', 'OLIVIA_DB_SSL', 'DB_SSL', 'OLIVIA_DB_SSL_CA', 'DB_SSL_CA'] as $envName) {
    putenv($envName);
    unset($_ENV[$envName], $_SERVER[$envName]);
}

function db_config_error(?string $name = null): string
{
    try {
        \OliviaLib\Database::getDB($name);
    } catch (\RuntimeException $exception) {
        return get_class($exception) . ': ' . $exception->getMessage();
    }
    return 'no exception';
}

assert_true(
    db_config_error() === 'RuntimeException: Database driver is not configured: set OLIVIA_DB_DSN or DB_DRIVER.',
    'Database fails closed without env (no empty stub)'
);
$legacyConfig = (string) file_get_contents($root . '/src/database-config/Database.php');
assert_true(strpos($legacyConfig, "'root'") === false && strpos($legacyConfig, 'obs_escolar') === false, 'OliviaDatabaseConfig has no hardcoded root/obs_escolar fallback');

putenv('OLIVIA_DB_DSN=mysql:host=127.0.0.1;port=3306;dbname=olivia');
assert_true(
    db_config_error() === 'RuntimeException: Database user is not configured: set DB_USERNAME.',
    'mysql without DB_USERNAME fails closed'
);
putenv('DB_USERNAME=olivia');
putenv('DB_PASSWORD=secret');
$mysql = \OliviaLib\Database::getDB();
assert_true($mysql === [
    'driver' => 'mysql',
    'host' => '127.0.0.1',
    'port' => '3306',
    'database' => 'olivia',
    'user' => 'olivia',
    'password' => 'secret',
    'ssl' => false,
    'ssl_ca' => '',
], 'OLIVIA_DB_DSN resolves the exact mysql config; credentials come from env');
assert_true(\OliviaLib\Database::getDB('default') === $mysql, 'getDB("default") is the default connection');
assert_true(\OliviaLib\Database::getDB('mysql') === $mysql, 'legacy $drive = driver name resolves to the default connection');
assert_true(db_config_error('sqlite') === 'RuntimeException: Unknown database connection "sqlite".', 'driver name different from the default driver is rejected');
assert_true(db_config_error('relatorios') === 'RuntimeException: Unknown database connection "relatorios".', 'undeclared connection name is rejected');
assert_true(\OliviaDatabaseConfig\Database::getDB() === $mysql, 'OliviaDatabaseConfig delegates to the application connections');

putenv('OLIVIA_DB_DSN=mysql:host=127.0.0.1;user=root;password=secret;dbname=olivia');
assert_true(
    db_config_error() === 'RuntimeException: Database credentials must not be embedded in OLIVIA_DB_DSN.',
    'embedded DSN credentials are rejected'
);

putenv('OLIVIA_DB_DSN=sqlite:/tmp/olivia.sqlite');
putenv('DB_DRIVER');
$sqlite = \OliviaLib\Database::getDB();
assert_true($sqlite['driver'] === 'sqlite' && $sqlite['database'] === '/tmp/olivia.sqlite', 'OLIVIA_DB_DSN selects sqlite');
assert_true($sqlite['user'] === '' && $sqlite['password'] === '', 'sqlite clears credentials');
assert_true(\OliviaLib\Database::getDB('sqlite') === $sqlite, 'legacy $drive = sqlite resolves to the default sqlite connection');

foreach (['OLIVIA_DB_DSN', 'DB_USERNAME', 'DB_PASSWORD'] as $envName) {
    putenv($envName);
}

if ($failures > 0) {
    fwrite(STDERR, "framework contract failed: {$failures} assertion(s)\n");
    exit(1);
}

echo "framework contract passed\n";
exit(0);
