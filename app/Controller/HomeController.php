<?php

namespace OliviaApp\Controller;

use OliviaApp\Models\Configuracoes;
use OliviaLib\CommandController;

use OliviaPublico\E404;
use OliviaPublico\Login\Index;

class HomeController extends CommandController
{

    public function index()
    {
        $c = new Configuracoes();
        print_r($c->all());
        //echo "<h1>It's Folks</h1>";
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
