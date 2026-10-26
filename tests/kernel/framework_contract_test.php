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

$database = (string) file_get_contents($root . '/lib/Database.php');
assert_true(strpos($database, 'HUB_OIDC_DB_DSN') === false, 'Database has no HUB_OIDC_DB_DSN');
assert_true(strpos($database, 'OLIVIA_DB_DSN') !== false, 'Database reads OLIVIA_DB_DSN');
assert_true(strpos($database, 'naufragio') === false, 'Database has no Hub default database name');

$dockerfile = (string) file_get_contents($root . '/Dockerfile');
assert_true(strpos($dockerfile, 'PHP_VERSION=8.5.12-fpm-alpine3.24') !== false, 'Dockerfile pins php:8.5.12-fpm-alpine3.24');

$route = (string) file_get_contents($root . '/lib/Route.php');
assert_true(strpos($route, "/livez") !== false, 'Route registers /livez');

$home = (string) file_get_contents($root . '/app/Controller/HomeController.php');
assert_true(strpos($home, 'function livez') !== false, 'HomeController implements livez');

$libIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/lib', FilesystemIterator::SKIP_DOTS)
);
$forbidden = ['HUB_OIDC', 'UserSessionService', 'cpsrepositorio'];
foreach ($libIterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $contents = (string) file_get_contents($file->getPathname());
    foreach ($forbidden as $needle) {
        assert_true(
            strpos($contents, $needle) === false,
            basename($file->getPathname()) . " has no {$needle}"
        );
    }
}

require $root . '/vendor/autoload.php';

foreach (['OLIVIA_DB_DSN', 'DB_DRIVER', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_USER', 'DB_PASSWORD'] as $envName) {
    putenv($envName);
    unset($_ENV[$envName], $_SERVER[$envName]);
}

$empty = \OliviaLib\Database::getDB();
assert_true($empty['driver'] === '' && $empty['host'] === '' && $empty['database'] === '', 'Database stub is empty without env');

putenv('OLIVIA_DB_DSN=mysql:host=127.0.0.1;port=3306;dbname=olivia');
putenv('DB_USERNAME=olivia');
putenv('DB_PASSWORD=secret');
$mysql = \OliviaLib\Database::getDB();
assert_true($mysql['driver'] === 'mysql', 'OLIVIA_DB_DSN selects mysql');
assert_true($mysql['host'] === '127.0.0.1' && $mysql['port'] === '3306' && $mysql['database'] === 'olivia', 'OLIVIA_DB_DSN parses host/port/dbname');
assert_true($mysql['user'] === 'olivia' && $mysql['password'] === 'secret', 'credentials come from env, not DSN');

$credentialRejected = false;
try {
    putenv('OLIVIA_DB_DSN=mysql:host=127.0.0.1;user=root;password=secret;dbname=olivia');
    \OliviaLib\Database::getDB();
} catch (\RuntimeException $exception) {
    $credentialRejected = strpos($exception->getMessage(), 'must not be embedded') !== false;
}
assert_true($credentialRejected, 'embedded DSN credentials are rejected');

putenv('OLIVIA_DB_DSN=sqlite:/tmp/olivia.sqlite');
putenv('DB_DRIVER');
$sqlite = \OliviaLib\Database::getDB();
assert_true($sqlite['driver'] === 'sqlite' && $sqlite['database'] === '/tmp/olivia.sqlite', 'OLIVIA_DB_DSN selects sqlite');
assert_true($sqlite['user'] === '' && $sqlite['password'] === '', 'sqlite clears credentials');

if ($failures > 0) {
    fwrite(STDERR, "framework contract failed: {$failures} assertion(s)\n");
    exit(1);
}

echo "framework contract passed\n";
exit(0);
