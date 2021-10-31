<?php

namespace OliviaApp\Middleware;

use OliviaLib\CommandController;

class RequestPost extends CommandController
{
    public function index()
    {
        if ($this->csrf_token() != $this->requestPost('_token')) {
            header('Location: ' . ".." . DIRECTORY_SEPARATOR);
        }
    }
}
