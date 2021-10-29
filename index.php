<?php
namespace OliviaApp;

use OliviaLib\Config;
use OliviaLib\Route;

require_once  __DIR__ . '/vendor/autoload.php';

class Index
{
    public function __construct()
    {
        new Config();
        $r = new Route();
        $r->execute();
    }
}
new Index();
