<?php

namespace OliviaDatabaseLibrary;

use PDO;

interface ConnectionInterface
{
    public function connect(): PDO;
    public function executeInsert(string $sql, array $values): ?string;
    public function executeSelect(string $sql, array $values): ?array;
    public function executeUpdate(string $sql, array $values): int;
    public function executeDelete(string $sql, array $values): int;

    /**
     * @template T
     * @param callable(ConnectionInterface): T $callback
     * @return T
     */
    public function transaction(callable $callback);

    public function inTransaction(): bool;

    /** Driver PDO normalizado (ex.: "mysql", "sqlite"). */
    public function driver(): string;

    /** Identidade estável do destino de dados, usada para particionar o cache. */
    public function identity(): string;

    public function queryCacheEnabled(): bool;
    public function close(): void;
}
