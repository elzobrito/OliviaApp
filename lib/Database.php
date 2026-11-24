<?php

namespace OliviaLib;

use OliviaDatabaseLibrary\ADatabase;
use OliviaDatabaseLibrary\DatabaseConfig;

/**
 * Conexões de banco da aplicação.
 *
 * Declare aqui apenas as conexões; host, banco e credenciais vêm do ambiente
 * (OLIVIA_DB_DSN ou DB_*). As regras de segurança (credenciais fora do DSN,
 * falha sem configuração, TLS fora do loopback) ficam no framework, em
 * OliviaDatabaseLibrary\DatabaseConfig.
 *
 * Para uma conexão adicional, use um prefixo de ambiente:
 *   'relatorios' => DatabaseConfig::fromEnvironment('REL_'),  // REL_DB_HOST, REL_DB_USERNAME...
 */
class Database extends ADatabase
{
    protected static function connections(): array
    {
        return [
            'default' => DatabaseConfig::fromEnvironment(),
        ];
    }
}
