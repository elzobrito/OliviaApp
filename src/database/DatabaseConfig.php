<?php

namespace OliviaDatabaseLibrary;

use InvalidArgumentException;
use RuntimeException;

/**
 * Configuração de uma conexão de banco lida do ambiente.
 *
 * A aplicação apenas declara as conexões (ver ADatabase::connections()); as
 * regras de segurança ficam aqui, no framework:
 *   - credenciais vêm só de variáveis de ambiente, nunca do DSN;
 *   - configuração obrigatória ausente falha fechado (sem host, banco ou usuário padrão);
 *   - TLS ligado por padrão para MySQL fora do loopback; desligá-lo para host remoto exige
 *     OLIVIA_DB_ALLOW_INSECURE=true (OLIVIA-SEC-316);
 *   - senha por DB_PASSWORD ou DB_PASSWORD_FILE (secret em arquivo, Docker/Kubernetes), nunca as duas.
 *
 * A leitura é preguiçosa: fromEnvironment() só guarda o prefixo, e o ambiente é
 * lido em toArray(), quando a conexão é de fato pedida. Assim uma conexão
 * secundária sem variáveis não impede o uso da conexão padrão.
 *
 * Com prefixo (ex.: 'REL_'), todos os nomes recebem o prefixo:
 * REL_OLIVIA_DB_DSN, REL_DB_DRIVER, REL_DB_HOST, REL_DB_USERNAME etc.
 */
final class DatabaseConfig
{
    public const DRIVERS = ['mysql', 'sqlite'];

    /** Tamanho máximo do arquivo de senha (DB_PASSWORD_FILE). */
    private const PASSWORD_FILE_MAX_BYTES = 4096;

    /** @var array<string, true> avisos de TLS desligado já registrados neste processo */
    private static array $insecureWarned = [];

    private string $prefix;

    private function __construct(string $prefix)
    {
        if ($prefix !== '' && preg_match('/^[A-Z][A-Z0-9_]*_$/', $prefix) !== 1) {
            throw new InvalidArgumentException(
                'Database environment prefix must be uppercase letters, digits and underscores, ending with "_".'
            );
        }
        $this->prefix = $prefix;
    }

    public static function fromEnvironment(string $prefix = ''): self
    {
        return new self($prefix);
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * Resolve a configuração a partir do ambiente atual.
     *
     * @return array{driver: string, host: string, port: string, database: string, user: string, password: string, ssl: bool, ssl_ca: string}
     * @throws RuntimeException quando a configuração é inválida ou incompleta
     */
    public function toArray(): array
    {
        $dsnVar = $this->name('OLIVIA_DB_DSN');
        $driverVar = $this->name('DB_DRIVER');

        $configuredDsn = trim($this->environment('OLIVIA_DB_DSN'));
        $dsnDriver = '';
        $dsnValues = [];
        if ($configuredDsn !== '') {
            if (preg_match('/^([a-z][a-z0-9_]*):(.*)$/i', $configuredDsn, $matches) !== 1) {
                throw new RuntimeException("{$dsnVar} is invalid.");
            }
            $dsnDriver = strtolower($matches[1]);
            if ($dsnDriver === 'mysql') {
                foreach (explode(';', $matches[2]) as $part) {
                    if ($part === '' || strpos($part, '=') === false) {
                        continue;
                    }
                    [$key, $value] = explode('=', $part, 2);
                    $key = strtolower(trim($key));
                    if (in_array($key, ['user', 'username', 'password'], true)) {
                        throw new RuntimeException("Database credentials must not be embedded in {$dsnVar}.");
                    }
                    if (in_array($key, ['host', 'port', 'dbname'], true) && !array_key_exists($key, $dsnValues)) {
                        $dsnValues[$key] = trim($value);
                    }
                }
            } elseif ($dsnDriver === 'sqlite') {
                $dsnValues['dbname'] = $matches[2];
            } else {
                throw new RuntimeException("Unsupported database driver in {$dsnVar}.");
            }
        }

        $explicitDriver = strtolower(trim($this->environment('DB_DRIVER')));
        if ($explicitDriver !== '' && $dsnDriver !== '' && $explicitDriver !== $dsnDriver) {
            throw new RuntimeException("{$driverVar} conflicts with {$dsnVar}.");
        }
        $driver = $explicitDriver !== '' ? $explicitDriver : $dsnDriver;
        if ($driver === '') {
            throw new RuntimeException("Database driver is not configured: set {$dsnVar} or {$driverVar}.");
        }
        if (!in_array($driver, self::DRIVERS, true)) {
            throw new RuntimeException('Unsupported database driver.');
        }

        $database = trim($this->environment('DB_DATABASE', $dsnValues['dbname'] ?? ''));
        if ($database === '') {
            throw new RuntimeException(
                'Database name is not configured: set ' . $this->name('DB_DATABASE') . " or dbname in {$dsnVar}."
            );
        }

        if ($driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'host' => '',
                'port' => '',
                'database' => $database,
                'user' => '',
                'password' => '',
                'ssl' => false,
                'ssl_ca' => '',
            ];
        }

        $host = trim($this->environment('DB_HOST', $dsnValues['host'] ?? ''));
        if ($host === '') {
            throw new RuntimeException(
                'Database host is not configured: set ' . $this->name('DB_HOST') . " or host in {$dsnVar}."
            );
        }

        $user = $this->environment('DB_USERNAME', $this->environment('DB_USER'));
        if (trim($user) === '') {
            throw new RuntimeException(
                'Database user is not configured: set ' . $this->name('DB_USERNAME') . '.'
            );
        }

        $ssl = $this->booleanEnvironment(
            'OLIVIA_DB_SSL',
            $this->booleanEnvironment('DB_SSL', !self::isLoopbackHost($host))
        );
        if (!$ssl && !self::isLoopbackHost($host)) {
            $allowVar = $this->name('OLIVIA_DB_ALLOW_INSECURE');
            if (!$this->booleanEnvironment('OLIVIA_DB_ALLOW_INSECURE', false)) {
                throw new RuntimeException(
                    "TLS is disabled for a non-loopback database host: set {$allowVar}=true to allow it explicitly."
                );
            }
            if (!isset(self::$insecureWarned[$this->prefix . "\0" . $host])) {
                self::$insecureWarned[$this->prefix . "\0" . $host] = true;
                error_log("OliviaDatabaseLibrary: TLS desligado para host de banco remoto ({$allowVar}=true).");
            }
        }

        return [
            'driver' => 'mysql',
            'host' => $host,
            'port' => trim($this->environment('DB_PORT', $dsnValues['port'] ?? '')),
            'database' => $database,
            'user' => $user,
            'password' => $this->password(),
            'ssl' => $ssl,
            'ssl_ca' => $this->environment('OLIVIA_DB_SSL_CA', $this->environment('DB_SSL_CA')),
        ];
    }

    /**
     * Senha de DB_PASSWORD ou do arquivo em DB_PASSWORD_FILE (uma quebra de linha final é removida).
     * Definir as duas, ou apontar um arquivo ilegível/grande demais, falha fechado. Mensagens nunca
     * incluem a senha nem o caminho do arquivo.
     */
    private function password(): string
    {
        $fileVar = $this->name('DB_PASSWORD_FILE');
        $inline = $this->raw('DB_PASSWORD');
        $file = $this->raw('DB_PASSWORD_FILE');
        $file = $file === null ? '' : trim($file);

        if ($file === '') {
            return $inline ?? '';
        }
        if ($inline !== null) {
            throw new RuntimeException('Set only one of ' . $this->name('DB_PASSWORD') . " or {$fileVar}.");
        }
        if (str_contains($file, "\0")) {
            throw new RuntimeException("{$fileVar} must point to a readable file.");
        }
        // O secret pode ser trocado durante a vida do processo (worker longo): nada de cache de stat.
        clearstatcache(true, $file);
        if (!is_file($file) || !is_readable($file)) {
            throw new RuntimeException("{$fileVar} must point to a readable file.");
        }
        // O limite vale para o que foi lido de fato, não para um filesize() possivelmente defasado.
        $contents = @file_get_contents($file, false, null, 0, self::PASSWORD_FILE_MAX_BYTES + 1);
        if ($contents === false) {
            throw new RuntimeException("{$fileVar} must point to a readable file.");
        }
        if (strlen($contents) > self::PASSWORD_FILE_MAX_BYTES) {
            throw new RuntimeException("{$fileVar} is larger than " . self::PASSWORD_FILE_MAX_BYTES . ' bytes.');
        }

        return (string) preg_replace('/\r?\n\z/', '', $contents);
    }

    public static function isLoopbackHost(string $host): bool
    {
        $host = strtolower(trim($host));
        return $host === '127.0.0.1' || $host === 'localhost' || $host === '::1' || $host === '[::1]';
    }

    private function name(string $name): string
    {
        return $this->prefix . $name;
    }

    private function raw(string $name)
    {
        $name = $this->name($name);
        $value = getenv($name);
        if ($value === false && isset($_ENV[$name])) {
            $value = $_ENV[$name];
        }
        if ($value === false && isset($_SERVER[$name])) {
            $value = $_SERVER[$name];
        }

        return $value === false || $value === null ? null : (string) $value;
    }

    private function environment(string $name, string $default = ''): string
    {
        return $this->raw($name) ?? $default;
    }

    private function booleanEnvironment(string $name, bool $default): bool
    {
        $value = $this->raw($name);
        if ($value === null || trim($value) === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($parsed === null) {
            throw new RuntimeException($this->name($name) . ' must be a boolean (true/false).');
        }

        return $parsed;
    }
}
