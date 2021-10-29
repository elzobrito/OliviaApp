<?php

namespace OliviaLib;

use OliviaLib\Router;
use OliviaLib\CommandController;

class Route extends CommandController
{
    public function execute()
    {
        $router = new Router();

        //GET
        $router->get('/', 'HomeController#index');
        $router->get('/login{:param}', 'HomeController#login');
        $router->get('/error404', 'HomeController#error404');

        /**
         * LOGIN 
         */
        $this->execute_action_login($router, null, 'AuthController', null);

        $router->execute($_SERVER);
        if ($_SESSION['e404'])
            header('Location: http://' . $_SERVER['HTTP_HOST'] . DIRECTORY_SEPARATOR . $_SESSION['BASENAME'] . DIRECTORY_SEPARATOR . 'error404');
    }


    private function execute_action_login($router, $middleware, $controller, $name)
    {
        $url_nome = '/dashboard/' . $name;
        $url_value_get[] = $url_nome . '/' . $name . '-listar-comunicacoes-regional{:param}';
        $url_value_post[] = $url_nome . '/' . $name . '-execute_action_login';

        $this->execute_action_post($router, $middleware, $controller, $url_value_post);
    }

    private function execute_action_get($router, $middleware, $controller, $url_value_get)
    {
        foreach ($url_value_get as $key => $url) {
            $url_explode = explode('/', $url);
            $router->middleware($middleware)->get($url, $controller . '#' . substr(implode('_', explode('-', $url_explode[count($url_explode) - 1])), 0, -8));
        }
    }

    private function execute_action_post($router, $middleware, $controller, $url_value_post)
    {
        foreach ($url_value_post as $key => $url) {
            $url_explode = explode('/', $url);
            $router->middleware($middleware)->post($url, $controller . '#' . implode('_', explode('-', $url_explode[count($url_explode) - 1])));
        }
    }
    
}
