<?php

namespace OliviaRouter;

/**
 * O caminho existe, mas não para o método da requisição (OLIVIA-SEC-322).
 * O ErrorHandler responde 405 com o header Allow montado a partir de getAllowedMethods().
 */
class MethodNotAllowedException extends \RuntimeException
{
    /** @var string[] */
    private array $allowedMethods;

    /**
     * @param string[] $allowedMethods
     */
    public function __construct(string $method, array $allowedMethods, ?\Throwable $previous = null)
    {
        $allowed = array_values(array_unique(array_map('strtoupper', $allowedMethods)));
        sort($allowed);
        $this->allowedMethods = $allowed;
        $method = preg_replace('/[^A-Z]/', '', strtoupper($method)) ?: '?';

        parent::__construct("Método {$method} não permitido; use " . implode(', ', $allowed) . '.', 405, $previous);
    }

    /**
     * @return string[] métodos aceitos pelo caminho, em maiúsculas e ordenados
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    public function getStatusCode(): int
    {
        return 405;
    }
}
