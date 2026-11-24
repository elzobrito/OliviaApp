<?php

namespace OliviaLib;

use OliviaPublico\E500;
use OliviaRouter\CsrfException;
use OliviaRouter\MethodNotAllowedException;

/**
 * Tratamento global de erros da requisição (OLIVIA-SEC-322).
 *
 * - CsrfException → 403; MethodNotAllowedException → 405 com header Allow; qualquer outra → 500.
 * - 500 e erros fatais são registrados com error_log (classe, mensagem, arquivo:linha, rota e trace).
 * - A página é genérica; classe, mensagem e trace só aparecem com APP_DEBUG=true.
 * - Saída parcial é descartada (register() abre um buffer de saída) para a página de erro sair inteira.
 * - Cliente que pede JSON (Accept sem text/html ou X-Requested-With) recebe {"status":..,"error":..}.
 * - Em HEAD só status e headers são enviados.
 */
final class ErrorHandler
{
    private const FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    private static bool $registered = false;
    private static bool $handling = false;
    private static int $bufferLevel = 0;

    /**
     * Registra o handler de exceções, o de erro fatal (shutdown) e abre um buffer de saída.
     * Chame antes de new Config() no front controller.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;
        set_exception_handler([self::class, 'handle']);
        register_shutdown_function([self::class, 'handleShutdown']);
        ob_start();
        self::$bufferLevel = ob_get_level();
    }

    public static function handle(\Throwable $exception): void
    {
        if (self::$handling) {
            return;
        }
        self::$handling = true;

        $status = self::statusFor($exception);
        if ($status >= 500) {
            self::log($exception);
        }

        $headers = [];
        if ($exception instanceof MethodNotAllowedException) {
            $headers['Allow'] = implode(', ', $exception->getAllowedMethods());
        }

        self::render($status, $exception, $headers);
    }

    /**
     * Erro fatal (memória, compilação...) não passa pelo handler de exceções: responde 500 no shutdown.
     * O PHP já registrou o erro no log (log_errors), então aqui só a página é enviada.
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if (self::$handling || $error === null || ($error['type'] & self::FATAL) === 0) {
            return;
        }
        self::$handling = true;
        $exception = new \ErrorException((string) $error['message'], 0, (int) $error['type'], (string) $error['file'], (int) $error['line']);
        self::render(500, $exception);
    }

    public static function statusFor(\Throwable $exception): int
    {
        if ($exception instanceof CsrfException) {
            return 403;
        }
        if ($exception instanceof MethodNotAllowedException) {
            return 405;
        }

        return 500;
    }

    /**
     * Título e descrição genéricos de cada status (nunca a mensagem da exceção).
     *
     * @return array{0: string, 1: string}
     */
    public static function publicMessage(int $status): array
    {
        return match ($status) {
            403 => ['Acesso negado', 'A requisição não pôde ser validada. Recarregue a página e tente novamente.'],
            405 => ['Método não permitido', 'Esta rota existe, mas não aceita o método usado na requisição.'],
            default => ['Erro interno', 'Algo deu errado ao processar a requisição. Tente novamente em instantes.'],
        };
    }

    public static function debugEnabled(): bool
    {
        return getenv('APP_DEBUG') === 'true';
    }

    /**
     * Envia a resposta de erro: status, headers, página (ou JSON). Detalhes só com APP_DEBUG=true.
     *
     * @param array<string, string> $headers
     */
    public static function render(int $status, ?\Throwable $exception = null, array $headers = []): void
    {
        // Descarta o que a página já tinha escrito (até o buffer aberto em register()).
        $minimo = self::$registered ? max(0, self::$bufferLevel - 1) : ob_get_level();
        while (ob_get_level() > $minimo) {
            ob_end_clean();
        }

        [$title, $description] = self::publicMessage($status);
        $detail = self::debugEnabled() && $exception !== null ? self::describe($exception) : null;
        $json = self::wantsJson();

        if (!headers_sent()) {
            Config::sendSecurityHeaders();
            http_response_code($status);
            header_remove('Content-Disposition');
            header_remove('Content-Range');
            header_remove('Location');
            foreach ($headers as $name => $value) {
                header($name . ': ' . $value);
            }
            header('Content-Type: ' . ($json ? 'application/json' : 'text/html') . '; charset=UTF-8');
            header('Cache-Control: no-store');
        }

        // HEAD: só status e headers, sem corpo.
        if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'HEAD') {
            return;
        }

        if ($json) {
            $payload = ['status' => $status, 'error' => $title, 'message' => $description];
            if ($detail !== null) {
                $payload['debug'] = $detail;
            }
            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
            return;
        }

        try {
            ob_start();
            new E500([
                'nivel' => '..',
                'status' => $status,
                'title' => $title,
                'description' => $description,
                'detail' => $detail,
            ]);
            ob_end_flush();
        } catch (\Throwable $renderError) {
            while (ob_get_level() > $minimo) {
                ob_end_clean();
            }
            self::log($renderError);
            echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="utf-8"><title>' . $status . '</title></head><body><h1>'
                . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1><p>' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
        }
    }

    private static function wantsJson(): bool
    {
        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            return true;
        }

        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    private static function describe(\Throwable $exception): string
    {
        return get_class($exception) . ': ' . $exception->getMessage()
            . "\n" . $exception->getFile() . ':' . $exception->getLine()
            . "\n" . $exception->getTraceAsString();
    }

    private static function log(\Throwable $exception): void
    {
        $rota = (string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' ' . (string) ($_SERVER['REQUEST_URI'] ?? '');
        $linha = sprintf(
            'OliviaLib: exceção não tratada %s: %s em %s:%d [%s]',
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $rota
        );
        // Sem quebras de linha controladas pelo cliente na linha principal (injeção de log).
        error_log(str_replace(["\r", "\n"], ' ', $linha) . "\n" . $exception->getTraceAsString());
    }
}
