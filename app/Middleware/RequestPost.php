<?php

namespace OliviaApp\Middleware;

use OliviaLib\CommandController;
use OliviaRouter\MiddlewareHaltException;

class RequestPost extends CommandController
{
    public function index()
    {
        if ($this->csrf_token() != $this->requestPost('_token')) {
            header('Location: ' . ".." . DIRECTORY_SEPARATOR);
            throw new MiddlewareHaltException('CSRF token inválido ou ausente.');
        }
    }
}
