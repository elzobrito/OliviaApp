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

    public function execute_action_login($router, $middleware, $controller, $name)
    {
        $url_nome = $name . DIRECTORY_SEPARATOR . $name;
        $url_value_post[] = ($name != null ? $url_nome : '') . 'logar-usuario';
        $url_value_get[] = ($name != null ? $url_nome : '') . 'esqueci-senha';
        $this->execute_action_get($router, $middleware, $controller, $url_value_get);
        $this->execute_action_post($router, $middleware, $controller, $url_value_post);
    }

    private function execute_action_get($router, $middleware, $controller, $url_value_get)
    {
        foreach ($url_value_get as $key => $url) {
            $router->middleware($middleware)->get('/' . $url . '{:param}', $controller . '#' . preg_replace('/-/', '_', $url));
        }
    }

    private function execute_action_post($router, $middleware, $controller, $url_value_post)
    {
        foreach ($url_value_post as $key => $url) {
            $router->middleware($middleware)->post('/' . $url, $controller . '#' . preg_replace('/-/', '_', $url));
        }
    }
}
