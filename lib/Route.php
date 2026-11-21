<?php

namespace OliviaLib;

use OliviaRouter\Router;

class Route
{
    public function execute()
    {
        $router = new Router();

        $router->get('/', 'HomeController#index');
        $router->get('/livez', 'HomeController#livez');
        $router->get('/error404', 'HomeController#error404');

        /*
         * Rotas de aplicação com middleware de CSRF, por exemplo:
         *
         * $router->middleware('RequestPost')->post('/minha-rota', 'MeuController#salvar');
         * $router->middleware(null); // encerra o escopo do middleware
         *
         * Parâmetros de rota usam a sintaxe {nome}: '/usuario/{id}'.
         */

        $router->execute($_SERVER);

        if (!empty($_SESSION['e404'])) {
            header('Location: ' . self::notFoundLocation($_SESSION['BASE_PATH'] ?? ''), true, 302);
            exit;
        }
    }

    /**
     * Destino do redirect de 404: caminho relativo à raiz do host, sem usar HTTP_HOST
     * (que é controlado pelo cliente e permitiria redirect aberto / envenenamento de cache).
     */
    public static function notFoundLocation($basePath): string
    {
        $basePath = '/' . trim(str_replace('\\', '/', (string) $basePath), '/');
        if ($basePath === '/' || preg_match('#^/[A-Za-z0-9._~\-/]*$#', $basePath) !== 1 || strpos($basePath, '//') !== false || preg_match('#(^|/)\.\.?(/|$)#', $basePath) === 1) {
            $basePath = '';
        }

        return $basePath . '/error404';
    }
}
