<?php

namespace OliviaApp\Controller;

use OliviaLib\CommandController;
use OliviaPublico\E404;
use OliviaPublico\Login\Index;
use OliviaPublico\Login\LostPassword;
use OliviaPublico\Login\ResetPassword;
use OliviaPublico\Home\Index as IndexHome;

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

    public function livez()
    {
        http_response_code(200);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'ok';
    }

    public function error404()
    {
        $parametros = $this->config_controller();
        new E404($parametros);
    }
}
