<?php

namespace OliviaDatabaseConfig;

use OliviaDatabaseLibrary\ADatabase;

/**
 * Ponto de compatibilidade dos antigos pacotes Olivia: delega para as conexões
 * declaradas pela aplicação em OliviaLib\Database. Não existe configuração
 * embutida: sem a classe da aplicação, falha fechado.
 */
class Database extends ADatabase
{
    public static function getDB($name = null)
    {
        if (!class_exists(\OliviaLib\Database::class)) {
            throw new \RuntimeException('No database connections are declared: OliviaLib\\Database is missing.');
        }

        return \OliviaLib\Database::getDB($name);
    }
}
