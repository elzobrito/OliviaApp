<?php

namespace OliviaApp\Middleware;

class RequestPost
{
    public function index()
    {
        if ($this->csrf_token() != $this->requestPost('_token')) {
            header('Location: ' . ".." . DIRECTORY_SEPARATOR);
        }
    }
}
