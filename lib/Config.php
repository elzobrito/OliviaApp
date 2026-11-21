<?php

namespace OliviaLib;

/**
 * Bootstrap da requisição: erros, sessão endurecida, CSRF e contexto de roteamento.
 *
 * Sessão (OLIVIA-SEC-305): cookie HttpOnly + SameSite=Lax, use_strict_mode, use_only_cookies e
 * Secure quando a requisição é HTTPS, quando um proxy listado em OLIVIA_TRUSTED_PROXIES envia
 * X-Forwarded-Proto: https, ou com OLIVIA_FORCE_SECURE=true (valor inválido é ignorado e registrado em log).
 * Após login/troca de privilégio chame Config::regenerateSession().
 */
class Config
{
    public function __construct()
    {
        ini_set('display_errors', getenv('APP_DEBUG') === 'true' ? '1' : '0');
        ini_set('display_startup_errors', getenv('APP_DEBUG') === 'true' ? '1' : '0');
        error_reporting(E_ALL);
        ini_set('memory_limit', '-1');
        setlocale(LC_MONETARY, 'pt_BR');

        self::startSession();

        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
        $scriptPath = parse_url($scriptName, PHP_URL_PATH);
        if (is_string($scriptPath) && $scriptPath !== '') {
            $scriptName = $scriptPath;
        }
        $basePath = str_replace('\\', '/', dirname($scriptName));
        $basePath = $basePath === '/' || $basePath === '.' ? '' : rtrim($basePath, '/');
        if ($basePath !== '' && (strpos($basePath, '://') !== false || strpos($basePath, '//') === 0)) {
            $basePath = '';
        }
        if ($basePath !== '' && $basePath[0] !== '/') {
            $basePath = '/' . $basePath;
        }
        $basename = $basePath === '' ? '' : basename($basePath);

        $_SESSION['e404'] = false;
        $_SESSION['CSRF'] = true;
        $_SESSION['App_folder'] = 'OliviaApp';
        $_SESSION['Middleware_folder'] = 'Middleware';
        $_SESSION['Controller_folder'] = 'Controller';
        $_SESSION['BASE_PATH'] = $basePath;
        $_SESSION['BASENAME'] = $basename;

        if (!self::isValidToken($_SESSION['UUID'] ?? null)) {
            self::rotateCsrfToken();
        }

        if (empty($_SESSION['secret_key'])) {
            $_SESSION['secret_key'] = bin2hex(random_bytes(32));
        }

        if (empty($_SESSION['secret_iv'])) {
            $_SESSION['secret_iv'] = bin2hex(random_bytes(16));
        }
    }

    /**
     * Aplica as configurações de cookie/sessão e inicia a sessão (se ainda não estiver ativa).
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        foreach (self::sessionSettings() as $key => $value) {
            ini_set($key, $value);
        }

        if (!headers_sent()) {
            session_start();
        }
    }

    /**
     * @return array<string, string>
     */
    public static function sessionSettings(): array
    {
        return [
            'session.cache_expire' => '60',
            'session.use_strict_mode' => '1',
            'session.use_only_cookies' => '1',
            'session.use_trans_sid' => '0',
            'session.cookie_httponly' => '1',
            'session.cookie_samesite' => 'Lax',
            'session.cookie_secure' => self::isSecureRequest() ? '1' : '0',
        ];
    }

    public static function isSecureRequest(): bool
    {
        $force = getenv('OLIVIA_FORCE_SECURE');
        if ($force !== false && trim($force) !== '') {
            $forced = filter_var(trim($force), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($forced !== null) {
                return $forced;
            }
            // Valor inválido não pode desligar Secure em HTTPS: é ignorado e a detecção automática segue.
            error_log('OliviaLib: OLIVIA_FORCE_SECURE inválido ("' . substr($force, 0, 32) . '"); use true ou false. Valor ignorado.');
        }

        $https = $_SERVER['HTTPS'] ?? '';
        if ($https !== '' && strtolower((string) $https) !== 'off') {
            return true;
        }

        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        // X-Forwarded-Proto é controlado pelo cliente: só vale quando a conexão vem de um proxy confiável.
        if (!self::isTrustedProxy((string) ($_SERVER['REMOTE_ADDR'] ?? ''))) {
            return false;
        }
        $proto = explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0];

        return strtolower(trim($proto)) === 'https';
    }

    /**
     * REMOTE_ADDR está em OLIVIA_TRUSTED_PROXIES (lista separada por vírgula de IPs ou CIDRs, IPv4/IPv6)?
     */
    public static function isTrustedProxy(string $remoteAddr): bool
    {
        $lista = getenv('OLIVIA_TRUSTED_PROXIES');
        if ($lista === false || trim($lista) === '' || $remoteAddr === '') {
            return false;
        }
        $ip = @inet_pton($remoteAddr);
        if ($ip === false) {
            return false;
        }

        foreach (explode(',', $lista) as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            [$rede, $prefixo] = array_pad(explode('/', $item, 2), 2, null);
            $redeBin = @inet_pton($rede);
            if ($redeBin === false || strlen($redeBin) !== strlen($ip)) {
                continue;
            }
            $bits = strlen($ip) * 8;
            if ($prefixo === null) {
                $prefixo = $bits;
            } elseif (!ctype_digit($prefixo) || (int) $prefixo > $bits) {
                continue;
            }
            $prefixo = (int) $prefixo;
            $bytes = intdiv($prefixo, 8);
            $resto = $prefixo % 8;
            if (substr($ip, 0, $bytes) !== substr($redeBin, 0, $bytes)) {
                continue;
            }
            if ($resto === 0) {
                return true;
            }
            $mascara = (0xFF << (8 - $resto)) & 0xFF;
            if ((ord($ip[$bytes]) & $mascara) === (ord($redeBin[$bytes]) & $mascara)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gera um novo token CSRF (64 hex, random_bytes) em $_SESSION['UUID'] e atualiza CSRF_FIELD.
     */
    public static function rotateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $_SESSION['UUID'] = $token;
        $_SESSION['CSRF_FIELD'] = '<input type="hidden" name="_token" value="' . $token . '">';

        return $token;
    }

    /**
     * Renova o ID de sessão (contra fixação de sessão) e o token CSRF.
     * Chame após login, logout ou mudança de privilégio.
     */
    public static function regenerateSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
        self::rotateCsrfToken();
    }

    private static function isValidToken($token): bool
    {
        return is_string($token) && preg_match('/^[0-9a-f]{64}$/', $token) === 1;
    }
}
