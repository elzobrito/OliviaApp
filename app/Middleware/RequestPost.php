<?php

namespace OliviaApp\Middleware;

use OliviaLib\CommandController;
use OliviaRouter\MiddlewareHaltException;

class RequestPost extends CommandController
{
    public function handle()
    {
        $expected = (string) ($_SESSION['UUID'] ?? '');
        $presente = is_array($_POST) && array_key_exists('_token', $_POST);
        $token = $presente ? $_POST['_token'] : null;
        if (!$presente || $token === '') {
            // Requisições AJAX/JSON enviam o token no header X-CSRF-Token.
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        }
        // _token presente com tipo inválido (null, array, número...) é rejeitado, sem cair no header.
        if ($expected === '' || !is_string($token) || !hash_equals($expected, $token)) {
            if (!headers_sent()) {
                header('Location: ../');
            }
            throw new MiddlewareHaltException('CSRF token inválido ou ausente.');
        }

        return true;
    }

    /**
     * @deprecated Use handle(); mantido por compatibilidade com rotas 'RequestPost#index'.
     */
    public function index()
    {
        return $this->handle();
    }
}
