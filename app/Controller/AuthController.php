<?php

namespace OliviaApp\Controller;

use OliviaLib\CommandController;

class AuthController extends CommandController
{
    public function logar_usuario()
    {
        print_r($_REQUEST);
    }

    public function esqueci_senha()
    {
        print_r($_REQUEST);
    }
}
