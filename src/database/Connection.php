<?php

namespace OliviaDatabaseLibrary;

use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * Conexão PDO preguiçosa (abre no primeiro uso) com suporte a transações.
 *
 * Opções além das de conexão:
 *   - timeout    (int, segundos; padrão OLIVIA_DB_TIMEOUT ou 5) — PDO::ATTR_TIMEOUT
 *   - retries    (int; padrão OLIVIA_DB_CONNECT_RETRIES ou 1)  — novas tentativas de conexão
 *   - persistent (bool; padrão OLIVIA_DB_PERSISTENT ou false)  — PDO::ATTR_PERSISTENT
 *
 * Use ConnectionManager::get($options) para reaproveitar a mesma conexão por configuração.
 *
 * Senha (OLIVIA-SEC-316): fica fora de $options, guardada numa closure; var_dump/print_r mostram
 * "********" (__debugInfo), var_export não a alcança e o construtor marca $options com
 * #[\SensitiveParameter] para stack traces. A conexão não pode ser serializada.
 */
class Connection implements ConnectionInterface
{
    private const RETRY_DELAY_MICROSECONDS = 200000;
    private const GONE_AWAY_CODES = [2006, 2013];

    private ?PDO $pdo = null;
    private ?string $instanceToken = null;
    /** @var array{0: \WeakReference, 1: bool}|null */
    private ?array $classificacaoSqlite = null;
    private array $options = [];
    private int $transactionDepth = 0;
    private \Closure $password;

    public function __construct(#[\SensitiveParameter] array $options)
    {
        // Verifica se as opções obrigatórias estão presentes
        if (!isset($options['host']) || !is_string($options['host'])) {
            throw new InvalidArgumentException('A opção "host" é obrigatória e deve ser uma string');
        }
        if (!isset($options['user']) || !is_string($options['user'])) {
            throw new InvalidArgumentException('A opção "user" é obrigatória e deve ser uma string');
        }
        if (!isset($options['password']) || !is_string($options['password'])) {
            throw new InvalidArgumentException('A opção "password" é obrigatória e deve ser uma string');
        }

        // Verifica se as opções opcionais têm valores válidos
        if (isset($options['ssl']) && !is_bool($options['ssl'])) {
            throw new InvalidArgumentException('A opção "ssl" deve ser um valor booleano');
        }
        if (isset($options['persistent']) && !is_bool($options['persistent'])) {
            throw new InvalidArgumentException('A opção "persistent" deve ser um valor booleano');
        }
        foreach (['timeout', 'retries'] as $intOption) {
            if (isset($options[$intOption]) && (!is_int($options[$intOption]) || $options[$intOption] < 0)) {
                throw new InvalidArgumentException("A opção \"{$intOption}\" deve ser um inteiro não negativo");
            }
        }

        $secret = $options['password'];
        $this->password = static fn (): string => $secret;
        unset($options['password']);
        $this->options = $options;
    }

    /**
     * Dump sem a senha (var_dump, print_r, debug_zval_dump).
     */
    public function __debugInfo(): array
    {
        return [
            'options' => ['password' => '********'] + $this->options,
            'connected' => $this->pdo instanceof PDO,
            'transactionDepth' => $this->transactionDepth,
        ];
    }

    /**
     * A senha não pode sair do processo por serialização (sessão, cache, filas).
     */
    public function __serialize(): array
    {
        throw new \LogicException('Connection não pode ser serializada.');
    }

    /**
     * @return PDO
     * @throws PDOException
     */
    public function connect(): PDO
    {
        if ($this->pdo) {
            return $this->pdo;
        }

        $driver = strtolower((string) ($this->options['driver'] ?? 'mysql'));
        $pdoOptions = [
            PDO::ATTR_ERRMODE => $this->options['errorMode'] ?? PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => $this->intOption('timeout', 'OLIVIA_DB_TIMEOUT', 5),
            PDO::ATTR_PERSISTENT => $this->boolOption('persistent', 'OLIVIA_DB_PERSISTENT', false),
        ];
        if ($driver === 'mysql' || $driver === '') {
            $pdoOptions = $this->mysqlClientOptions() + $pdoOptions;
        }

        $attempts = 1 + $this->intOption('retries', 'OLIVIA_DB_CONNECT_RETRIES', 1);
        $lastException = null;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $this->pdo = new PDO($this->dsn(), $this->options['user'], ($this->password)(), $pdoOptions);
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, $pdoOptions[PDO::ATTR_ERRMODE]);
                return $this->pdo;
            } catch (PDOException $e) {
                $lastException = $e;
                if ($attempt < $attempts) {
                    usleep(self::RETRY_DELAY_MICROSECONDS);
                }
            }
        }

        $detail = $lastException instanceof PDOException ? ' (' . $lastException->getMessage() . ')' : '';
        throw new PDOException("Falha ao tentar conectar ao banco de dados." . $detail, 0, $lastException);
    }

    public function mysqlClientOptions(): array
    {
        $ssl = !empty($this->options['ssl']);

        $attrInitCommand = defined('Pdo\Mysql::ATTR_INIT_COMMAND')
            ? constant('Pdo\Mysql::ATTR_INIT_COMMAND')
            : (defined('PDO::MYSQL_ATTR_INIT_COMMAND') ? PDO::MYSQL_ATTR_INIT_COMMAND : 1002);

        $attrSslVerify = defined('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')
            ? constant('Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')
            : (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') ? PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT : 1014);

        $options = [
            $attrInitCommand => 'SET NAMES "UTF8"',
            $attrSslVerify => $ssl,
        ];

        if ($ssl) {
            $caFile = trim((string) ($this->options['ssl_ca'] ?? ''));
            if ($caFile === '') {
                $caFile = __DIR__ . DIRECTORY_SEPARATOR . 'BaltimoreCyberTrustRoot.crt.pem';
            }
            if (!is_file($caFile)) {
                throw new InvalidArgumentException('TLS CA certificate is required when ssl=true.');
            }
            $attrSslCa = defined('Pdo\Mysql::ATTR_SSL_CA')
                ? constant('Pdo\Mysql::ATTR_SSL_CA')
                : (defined('PDO::MYSQL_ATTR_SSL_CA') ? PDO::MYSQL_ATTR_SSL_CA : 1012);
            $options[$attrSslCa] = $caFile;
        }

        return $options;
    }

    /**
     * @return string
     */
    protected function dsn()
    {
        $driver = strtolower((string) ($this->options['driver'] ?? 'mysql'));
        if ($driver === '') {
            $driver = 'mysql';
        }

        if ($driver === 'sqlite') {
            $database = (string) ($this->options['database'] ?? '');
            if ($database === '') {
                throw new InvalidArgumentException('A opção "database" é obrigatória para sqlite.');
            }
            return 'sqlite:' . $database;
        }

        $dsn = "{$driver}:host={$this->options['host']}";
        if (($this->options['port'] ?? '') !== '') {
            $dsn .= ";port={$this->options['port']}";
        }
        return $dsn . ";dbname=" . ($this->options['database'] ?? '');
    }

    /**
     * Prepara e executa; reconecta uma única vez se o servidor fechou a conexão
     * (MySQL 2006/2013) e não há transação aberta. Erros de SQL não são repetidos.
     */
    /**
     * Prepara e executa. Só LEITURAS são repetidas, uma única vez, e só quando o MySQL fechou a
     * conexão (2006/2013) fora de transação: repetir uma escrita após perda de conexão poderia
     * duplicar um efeito cujo resultado ficou desconhecido.
     */
    private function run($sql, array $values, string $label, callable $result, bool $retryable = false)
    {
        $reconnected = false;
        while (true) {
            $pdo = $this->connect();
            $emTransacao = $this->inTransaction();
            try {
                $statement = $pdo->prepare($sql);
                if (!$statement instanceof PDOStatement || !$statement->execute(array_values($values))) {
                    return null;
                }
                return $result($statement, $pdo);
            } catch (PDOException $e) {
                if (!$emTransacao && $this->isGoneAway($e)) {
                    // Conexão morta fora de transação: descarta o PDO para a próxima chamada reconectar.
                    $this->pdo = null;
                    if ($retryable && !$reconnected) {
                        $reconnected = true;
                        continue;
                    }
                }
                throw new Exception("Falha ao executar a consulta de {$label}: " . $e->getMessage(), 0, $e);
            }
        }
    }

    private function isGoneAway(PDOException $e): bool
    {
        if ($this->driver() !== 'mysql') {
            return false;
        }
        $code = (int) ($e->errorInfo[1] ?? 0);
        return in_array($code, self::GONE_AWAY_CODES, true);
    }

    public function driver(): string
    {
        $driver = strtolower((string) ($this->options['driver'] ?? 'mysql'));
        return $driver === '' ? 'mysql' : $driver;
    }

    /**
     * Identidade estável do destino de dados (driver, host, porta, banco, usuário), sem a senha.
     */
    public function identity(): string
    {
        return substr(hash('sha256', implode("\0", [
            $this->driver(),
            (string) ($this->options['host'] ?? ''),
            (string) ($this->options['port'] ?? ''),
            $this->databaseIdentity(),
            (string) ($this->options['user'] ?? ''),
        ])), 0, 16);
    }

    private function databaseIdentity(): string
    {
        $database = (string) ($this->options['database'] ?? '');
        if ($this->driver() === 'sqlite' && self::sqliteEfemero($database)) {
            // Banco em memória/temporário: cada conexão é um banco distinto, então a identidade é da instância.
            $this->instanceToken ??= bin2hex(random_bytes(8));
            return 'efemero:' . $this->instanceToken;
        }
        if ($this->driver() === 'sqlite') {
            $real = realpath($database);
            return $real !== false ? $real : $database;
        }
        return $database;
    }

    /**
     * Cache de consultas em sessão: opção "query_cache" ou OLIVIA_QUERY_CACHE=session. Desligado por padrão,
     * porque a sessão não enxerga escritas feitas por outros processos/usuários.
     */
    public function queryCacheEnabled(): bool
    {
        if (!$this->cacheConfigurado()) {
            return false;
        }
        // SQLite em memória é privado de cada PDO (e some ao reconectar): nenhuma identidade estável
        // descreve esse destino, então o cache de sessão nunca é usado para ele.
        return !($this->driver() === 'sqlite' && $this->sqliteDestinoEfemero());
    }

    private function cacheConfigurado(): bool
    {
        if (array_key_exists('query_cache', $this->options)) {
            $valor = $this->options['query_cache'];
            return $valor === true || (is_string($valor) && strtolower(trim($valor)) === 'session');
        }
        $env = getenv('OLIVIA_QUERY_CACHE');
        if ($env === false) {
            $env = $_ENV['OLIVIA_QUERY_CACHE'] ?? $_SERVER['OLIVIA_QUERY_CACHE'] ?? '';
        }
        return strtolower(trim((string) $env)) === 'session';
    }

    /**
     * Classifica o destino SQLite pelo que o SQLite realmente abriu (PRAGMA database_list), e não só
     * pelo texto do DSN: URIs aceitam codificação percentual e outras formas equivalentes.
     * Em caso de dúvida (falha ao conectar ou consultar), trata como efêmero — o cache fica desligado.
     */
    private function sqliteDestinoEfemero(): bool
    {
        if (self::sqliteEfemero(rawurldecode((string) ($this->options['database'] ?? '')))) {
            return true;
        }
        try {
            $pdo = $this->connect();
            if ($this->classificacaoSqlite !== null && $this->classificacaoSqlite[0]->get() === $pdo) {
                return $this->classificacaoSqlite[1];
            }
            $efemero = true;
            $stmt = $pdo->query('PRAGMA database_list');
            foreach ($stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $banco) {
                if (($banco['name'] ?? '') === 'main') {
                    $efemero = (string) ($banco['file'] ?? '') === '';
                }
            }
            // WeakReference: a classificação vale só para este PDO e não o mantém vivo após close().
            $this->classificacaoSqlite = [\WeakReference::create($pdo), $efemero];
            return $efemero;
        } catch (Throwable $e) {
            return true;
        }
    }

    public function executeInsert($sql, array $values): ?string
    {
        return $this->run($sql, $values, 'inserção', fn (PDOStatement $s, PDO $pdo) => $pdo->lastInsertId());
    }

    public function executeSelect($sql, array $values): ?array
    {
        return $this->run($sql, $values, 'seleção', fn (PDOStatement $s) => $s->fetchAll(PDO::FETCH_OBJ), true);
    }

    public function executeUpdate($sql, array $values): int
    {
        return (int) $this->run($sql, $values, 'atualização', fn (PDOStatement $s) => $s->rowCount());
    }

    public function executeDelete($sql, array $values): int
    {
        return (int) $this->run($sql, $values, 'exclusão', fn (PDOStatement $s) => $s->rowCount());
    }

    /**
     * Executa $callback dentro de uma transação: commit no sucesso, rollback e relançamento na falha.
     * Chamadas aninhadas participam da transação externa.
     *
     * @template T
     * @param callable(ConnectionInterface): T $callback
     * @return T
     */
    public function transaction(callable $callback)
    {
        // Participa de transação já aberta pelo builder ou diretamente no PDO.
        if ($this->inTransaction()) {
            $this->transactionDepth++;
            try {
                return $callback($this);
            } finally {
                $this->transactionDepth--;
            }
        }

        $pdo = $this->connect();
        try {
            // Com errorMode silencioso o PDO devolve false em vez de lançar: falha nunca vira sucesso.
            if ($pdo->beginTransaction() !== true) {
                throw new \RuntimeException('Não foi possível iniciar a transação: ' . self::pdoErro($pdo));
            }
        } catch (PDOException $e) {
            if ($this->isGoneAway($e)) {
                $this->pdo = null; // próxima chamada reconecta em vez de falhar para sempre
            }
            throw $e;
        }
        $this->transactionDepth = 1;
        try {
            $result = $callback($this);
            if ($pdo->commit() !== true) {
                throw new \RuntimeException('Falha no commit da transação: ' . self::pdoErro($pdo));
            }
            return $result;
        } catch (Throwable $e) {
            $desfeita = false;
            try {
                $desfeita = !$pdo->inTransaction() || $pdo->rollBack() === true;
                if (!$desfeita) {
                    error_log('OliviaDatabaseLibrary: rollBack falhou: ' . self::pdoErro($pdo));
                }
            } catch (Throwable $rollbackError) {
                error_log('OliviaDatabaseLibrary: rollBack falhou: ' . $rollbackError->getMessage());
            }
            if (!$desfeita && $this->pdo === $pdo) {
                // Não mascara a causa original (ex.: conexão perdida durante a transação) e descarta o
                // PDO quebrado, que continuaria reportando inTransaction() = true para sempre.
                $this->pdo = null;
            }
            throw $e;
        } finally {
            $this->transactionDepth = 0;
        }
    }

    private static function pdoErro(PDO $pdo): string
    {
        $info = $pdo->errorInfo();
        $texto = trim(($info[0] ?? '') . ' ' . ($info[2] ?? ''));
        return $texto !== '' ? $texto : 'erro desconhecido';
    }

    private static function sqliteEfemero(string $database): bool
    {
        // ':memory:', URIs como 'file::memory:?cache=shared' e 'file:nome?mode=memory'.
        return $database === '' || stripos($database, ':memory:') !== false || stripos($database, 'mode=memory') !== false;
    }

    public function inTransaction(): bool
    {
        if ($this->transactionDepth > 0) {
            return true;
        }
        try {
            return $this->pdo instanceof PDO && $this->pdo->inTransaction();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function close(): void
    {
        if ($this->pdo instanceof PDO && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $this->transactionDepth = 0;
        $this->pdo = null;
    }

    private function intOption(string $name, string $env, int $default): int
    {
        if (isset($this->options[$name])) {
            return (int) $this->options[$name];
        }
        $value = getenv($env);
        return $value !== false && ctype_digit((string) $value) ? (int) $value : $default;
    }

    private function boolOption(string $name, string $env, bool $default): bool
    {
        if (isset($this->options[$name])) {
            return (bool) $this->options[$name];
        }
        $value = getenv($env);
        return $value === false || $value === '' ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
