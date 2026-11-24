<?php

namespace OliviaRouter;

/**
 * Token CSRF ausente ou inválido numa rota POST/PUT/PATCH/DELETE (OLIVIA-SEC-322).
 * O ErrorHandler responde 403. Estende RuntimeException por compatibilidade com quem já a capturava.
 */
class CsrfException extends \RuntimeException
{
    public function __construct(string $message = 'CSRF token inválido ou ausente.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }

    public function getStatusCode(): int
    {
        return 403;
    }
}
