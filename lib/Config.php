<?php

namespace OliviaLib;

// ini_set('display_errors', 1);
// ini_set('display_startup_erros', 1);
// error_reporting(E_ALL);

// ini_set('memory_limit', '-1');

// ini_set('session.cache_expire', 60); // tempo de sessão aberta 60 minutos.
// ini_set('session.cookie_httponly', true);
// ini_set('session.cookie_secure', true);
session_start();

setlocale(LC_MONETARY, 'pt_BR');
//Contante de configuração do CSRF ao deixar true todos os post são verificados
define("CSRF", true);

use Ramsey\Uuid\Uuid;

class Config
{
    public function __construct()
    {
        $_SESSION['e404'] = false;
        $_SESSION['BASENAME'] = explode('/', $_SERVER["REQUEST_URI"])[1];

        if (!isset($_SESSION['UUID'])) {
            $uuid = Uuid::uuid4();
            $_SESSION['UUID'] = $uuid->toString();
            $_SESSION['CSRF_FIELD'] = '<input type="hidden" name="_token" value="' . $_SESSION['UUID'] . '">';
        }

        if (!isset($_SESSION['secret_key'])) {
            $uuid = Uuid::uuid4();
            $_SESSION['secret_key'] = $uuid->toString();
        }

        if (!isset($_SESSION['secret_iv'])) {
            $uuid = Uuid::uuid4();
            $_SESSION['secret_iv'] = $uuid->toString();
        }
    }
}
