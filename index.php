<?php
namespace OliviaApp;

use OliviaLib\Config;
use OliviaLib\ErrorHandler;
use OliviaLib\Route;

require_once  __DIR__ . '/vendor/autoload.php';

class Index
{
    public function __construct()
    {
        // 403 (CSRF), 405 (método) e 500 com página própria e log; ver OliviaLib\ErrorHandler.
        ErrorHandler::register();
        new Config();
        $r = new Route();
        $r->execute();
    }
}
new Index();
