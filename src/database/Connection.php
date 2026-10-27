<?php

namespace OliviaDatabaseLibrary;

use Exception;
use InvalidArgumentException;
use PDO;
use PDOException;
class Connection implements ConnectionInterface
{
    private static $instance = null;
    private $pdo = null;
    private $options = [];

    public function __construct(array $options)
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

        $this->options = $options;
        if ($this->isRemoteMysql() && empty($this->options['ssl'])) {
            throw new InvalidArgumentException('Remote MySQL requires ssl=true with certificate verification.');
        }
    }

    /**
     * @return instance
     */
    public static function getInstance(array $options)
    {
        if (self::$instance === null) {
            self::$instance = new Connection($options);
        }
        return self::$instance;
    }

    /**
     * @return PDO
     * @throws PDOException
     */
    public function connect(): PDO
    {
        if (!$this->pdo) {
            $attempts = 0;
            while ($attempts < 3) {
                try {

                    $driver = strtolower((string) ($this->options['driver'] ?? 'mysql'));
                    $options = $driver === 'mysql' ? $this->mysqlClientOptions() : [];

                    $this->pdo = new PDO($this->dsn(), $this->options['user'], $this->options['password'], $options);
                    // set the PDO error mode
                    $errorMode = isset($this->options['errorMode']) ? $this->options['errorMode'] : PDO::ERRMODE_EXCEPTION;
                    $this->pdo->setAttribute(PDO::ATTR_ERRMODE, $errorMode);

                    return $this->pdo; // Conexão estabelecida, retornar o objeto PDO
                } catch (PDOException $e) {
                    $attempts++;
                    sleep(1);
                }
            }
            throw new PDOException("Falha ao tentar conectar ao banco de dados.");
        }
        return $this->pdo;
    }

    public function mysqlClientOptions(): array
    {
        $ssl = !empty($this->options['ssl']);
        if ($this->isRemoteMysql() && !$ssl) {
            throw new InvalidArgumentException('Remote MySQL requires ssl=true with certificate verification.');
        }

        $options = [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES "UTF8"',
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => $ssl,
        ];

        if ($ssl) {
            $caFile = trim((string) ($this->options['ssl_ca'] ?? ''));
            if ($caFile === '') {
                $caFile = __DIR__ . DIRECTORY_SEPARATOR . 'BaltimoreCyberTrustRoot.crt.pem';
            }
            if (!is_file($caFile)) {
                throw new InvalidArgumentException('TLS CA certificate is required when ssl=true.');
            }
            $options[PDO::MYSQL_ATTR_SSL_CA] = $caFile;
        }

        return $options;
    }

    private function isRemoteMysql(): bool
    {
        $driver = strtolower((string) ($this->options['driver'] ?? 'mysql'));
        if ($driver !== 'mysql') {
            return false;
        }

        $host = strtolower(trim((string) ($this->options['host'] ?? '')));
        return $host !== '' && $host !== '127.0.0.1' && $host !== 'localhost' && $host !== '::1';
    }

    /**
     * @return string
     */
    protected function dsn()
    {
        try {
            return "{$this->options['driver']}:host={$this->options['host']};port={$this->options['port']};dbname={$this->options['database']}";
        } catch (PDOException $e) {
            throw new Exception("Falha ao aplicar o dns: " . $e->getMessage());
        }
    }

    private final function statement($sql)
    {
        $attempts = 0;
        while ($attempts < 3) {
            try {
                return $this->connect()->prepare($sql);
            } catch (PDOException $e) {
                $attempts++;
                if ($attempts >= 3) {
                    throw new Exception("Falha ao preparar a declaração SQL: " . $e->getMessage());
                }
                sleep(1);
            }
        }
    }

    public function executeInsert($sql, array $values): ?string
    {
        $statement = $this->statement($sql);

        try {
            if ($statement && $statement->execute(array_values($values))) {
                return $this->connect()->lastInsertId();
            }
        } catch (PDOException $e) {
            throw new Exception("Falha ao executar a consulta de inserção: " . $e->getMessage());
        }

        return null;
    }

    public function executeSelect($sql, array $values): ?array
    {
        $statement = $this->statement($sql);

        try {
            if ($statement && $statement->execute(array_values($values))) {
                return $statement->fetchAll(PDO::FETCH_OBJ);
            }
        } catch (PDOException $e) {
            throw new Exception("Falha ao executar a consulta de seleção: " . $e->getMessage());
        }

        return null;
    }

    public function close(): void
    {
        $this->pdo = null;
    }
}