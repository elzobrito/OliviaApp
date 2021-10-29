<?php

namespace OliviaLib;

use elzobrito\ADatabase;

class Database extends ADatabase
{
    //put your code here

    public static function getDB($name = null)
    {
        $db = null;
        switch ($name) {
            default:
                $db = [
                    'host' => '',
                    'port' => '',
                    'database' => '',
                    'user' => '',
                    'password' => '',
                ];
                break;
        }
        return $db;
    }
}
