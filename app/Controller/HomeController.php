<?php

namespace OliviaApp\Controller;

use OliviaLib\CommandController;
use OliviaPublico\E404;
use OliviaPublico\Login\Index;

class HomeController extends CommandController
{
    private function config_controller()
    {
        $parametros['nivel'] = '..';
        $parametros['pre_url'] = 'pasta';
        return $parametros;
    }


    public function index()
    {
        $parametros = $this->config_controller();
        new Index($parametros);
    }

    public function login()
    {
        // $parametros['nivel'] = '..';
        // new Index($parametros);
    }

    public function error404()
    {
        $parametros = $this->config_controller();
        new E404($parametros);
    }
}
