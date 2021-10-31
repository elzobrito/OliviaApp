<?php

namespace OliviaApp\Controller;

use OliviaLib\CommandController;
use OliviaPublico\E404;
use OliviaPublico\Login\Index;
use OliviaPublico\Home\Index as IndexHome;
use OliviaPublico\Login\LostPassword;
use OliviaPublico\Login\ResetPassword;

class HomeController extends CommandController
{
    private function config_controller()
    {
        $parametros['nivel'] = '..';
        $parametros['pre_url'] = 'olivia';
        return $parametros;
    }

    public function index()
    {
        $parametros = $this->config_controller();
        new IndexHome($parametros);
    }

    public function olivia_login()
    {
        $parametros = $this->config_controller();
        new Index($parametros);
    }

    public function olivia_esqueci_senha()
    {

        $parametros = $this->config_controller();
        new LostPassword($parametros);
    }

    public function olivia_recuperar_senha()
    {

        $parametros = $this->config_controller();
        new ResetPassword($parametros);

    }

    public function olivia_salvar_senha()
    {
        $parametros = $this->config_controller();
        new Index($parametros);
    }

    public function error404()
    {
        $parametros = $this->config_controller();
        new E404($parametros);
    }
}
