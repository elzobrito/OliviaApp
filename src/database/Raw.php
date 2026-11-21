<?php

namespace OliviaDatabaseLibrary;

/**
 * Expressão SQL confiável que o QueryBuilder insere sem validação.
 *
 * Use somente com SQL escrito pelo desenvolvedor — nunca com entrada do usuário.
 * Exemplo: $qb->fields(['id', Raw::sql("DATE_FORMAT(created_at, '%Y') AS ano")]);
 */
final class Raw
{
    private string $sql;

    private function __construct(string $sql)
    {
        $this->sql = $sql;
    }

    public static function sql(string $sql): self
    {
        return new self($sql);
    }

    public function __toString(): string
    {
        return $this->sql;
    }
}
