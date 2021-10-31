<?php

namespace OliviaApp\Middleware;

use OliviaLib\CommandController;

class RequestPost extends CommandController
{
    public function index()
    {
        if ($this->csrf_token() != $this->requestGet('_token')) {
            header('Location: ' . ".." . DIRECTORY_SEPARATOR);
        }
    }
}
