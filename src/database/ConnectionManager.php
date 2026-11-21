<?php

namespace OliviaDatabaseLibrary;

/**
 * Pool de conexões por configuração: a mesma configuração reutiliza a mesma
 * Connection (e o mesmo PDO) durante o processo; configurações diferentes
 * ficam isoladas.
 */
final class ConnectionManager
{
    /** @var array<string, ConnectionInterface> */
    private static array $pool = [];

    public static function get(array $options): ConnectionInterface
    {
        $key = self::key($options);
        return self::$pool[$key] ??= new Connection($options);
    }

    /**
     * Fecha e descarta todas as conexões (testes, workers longos, fim de job).
     */
    public static function reset(): void
    {
        foreach (self::$pool as $connection) {
            $connection->close();
        }
        self::$pool = [];
    }

    public static function count(): int
    {
        return count(self::$pool);
    }

    private static function key(array $options): string
    {
        ksort($options);
        return hash('sha256', serialize($options));
    }
}
