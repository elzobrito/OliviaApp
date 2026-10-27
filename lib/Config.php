<?php

namespace OliviaLib;

use Ramsey\Uuid\Uuid;

ini_set('display_errors', getenv('APP_DEBUG') === 'true' ? 1 : 0);
ini_set('display_startup_errors', getenv('APP_DEBUG') === 'true' ? 1 : 0);
error_reporting(E_ALL);

ini_set('memory_limit', '-1');

ini_set('session.cache_expire', 60);
ini_set('session.cookie_httponly', true);
ini_set('session.cookie_secure', true);
session_start();

setlocale(LC_MONETARY, 'pt_BR');

class Config
{
    public function __construct()
    {
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
        $scriptPath = parse_url($scriptName, PHP_URL_PATH);
        if (is_string($scriptPath) && $scriptPath !== '') {
            $scriptName = $scriptPath;
        }
        $basePath = str_replace('\\', '/', dirname($scriptName));
        $basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');
        if ($basePath !== '' && (strpos($basePath, '://') !== false || strpos($basePath, '//') === 0)) {
            $basePath = '';
        }
        if ($basePath !== '' && $basePath[0] !== '/') {
            $basePath = '/' . $basePath;
        }
        $basename = $basePath === '' ? '' : basename($basePath);

        $_SESSION['e404'] = false;
        $_SESSION['CSRF'] = true;
        $_SESSION['App_folder'] = 'OliviaApp';
        $_SESSION['Middleware_folder'] = 'Middleware';
        $_SESSION['Controller_folder'] = 'Controller';
        $_SESSION['BASE_PATH'] = $basePath;
        $_SESSION['BASENAME'] = $basename;

        if (!isset($_SESSION['UUID'])) {
            $uuid = Uuid::uuid4();
            $_SESSION['UUID'] = $uuid->toString();
            $_SESSION['CSRF_FIELD'] = '<input type="hidden" name="_token" value="' . $_SESSION['UUID'] . '">';
        }

        if (!isset($_SESSION['secret_key'])) {
            $uuid = Uuid::uuid4();
            $_SESSION['secret_key'] = $uuid->toString();
        }

        if (!isset($_SESSION['secret_iv'])) {
            $uuid = Uuid::uuid4();
            $_SESSION['secret_iv'] = $uuid->toString();
        }
    }
}
