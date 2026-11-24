<?php
namespace OliviaDatabaseLibrary;

use RuntimeException;

/**
 * Registro das conexões de banco da aplicação.
 *
 * A aplicação só declara as conexões; a leitura do ambiente e as regras de
 * segurança ficam em DatabaseConfig:
 *
 *   class Database extends ADatabase
 *   {
 *       protected static function connections(): array
 *       {
 *           return [
 *               'default'    => DatabaseConfig::fromEnvironment(),        // DB_*, OLIVIA_DB_*
 *               'relatorios' => DatabaseConfig::fromEnvironment('REL_'),  // REL_DB_*, REL_OLIVIA_DB_*
 *           ];
 *       }
 *   }
 *
 * getDB(null) devolve a conexão 'default'. Por compatibilidade com models que
 * usam o nome do driver em $drive ('mysql', 'sqlite'), um nome não declarado
 * igual ao driver da conexão 'default' resolve para ela. Qualquer outro nome
 * desconhecido lança RuntimeException; não existe configuração padrão embutida.
 *
 * Subclasses antigas que sobrescrevem getDB() continuam funcionando.
 */
abstract class ADatabase
{
    public const DEFAULT_CONNECTION = 'default';

    /**
     * @return array<string, DatabaseConfig>
     */
    protected static function connections(): array
    {
        return [];
    }

    /**
     * O parâmetro se chama $name, como na API pública anterior (OliviaLib\Database::getDB($name)),
     * para que chamadas com argumento nomeado (getDB(name: 'relatorios')) continuem funcionando.
     */
    public static function getDB($name = null)
    {
        $connections = static::connections();
        if ($connections === []) {
            throw new RuntimeException('No database connections are declared.');
        }

        $name = $name === null || $name === '' ? self::DEFAULT_CONNECTION : $name;
        if (!is_string($name)) {
            throw new RuntimeException('Database connection name must be a string.');
        }

        if (array_key_exists($name, $connections)) {
            return self::resolve($name, $connections[$name]);
        }

        if (array_key_exists(self::DEFAULT_CONNECTION, $connections) && in_array($name, DatabaseConfig::DRIVERS, true)) {
            $default = self::resolve(self::DEFAULT_CONNECTION, $connections[self::DEFAULT_CONNECTION]);
            if ($default['driver'] === $name) {
                return $default;
            }
        }

        throw new RuntimeException("Unknown database connection \"{$name}\".");
    }

    private static function resolve(string $name, $config): array
    {
        if (!$config instanceof DatabaseConfig) {
            throw new RuntimeException(
                "Database connection \"{$name}\" must be declared with DatabaseConfig::fromEnvironment()."
            );
        }

        return $config->toArray();
    }
}
