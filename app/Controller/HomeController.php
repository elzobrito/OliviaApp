<?php

namespace OliviaApp\Controller;

use OliviaLib\CommandController;
use OliviaPublico\E404;
use OliviaPublico\Login\Index;

class HomeController extends CommandController
{

    public function index()
    {
        echo "<h1>It's Folks</h1>";
        // $parametros['nivel'] = '..';
        // new Index($parametros);
    }

    public function login()
    {
        $parametros['nivel'] = '..';
        new Index($parametros);
    }

    public function error404()
    {
        $parametros['nivel'] = '..';
        new E404($parametros);
    }
}
