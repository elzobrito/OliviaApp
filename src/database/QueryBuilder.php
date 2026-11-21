<?php

namespace OliviaDatabaseLibrary;

use InvalidArgumentException;
use OliviaCache\SessionCache;

/**
 * Montador de SQL.
 *
 * Segurança: table, fields, group, order e limit são validados como identificadores
 * (ou inteiros, no caso de limit); entrada fora do padrão lança InvalidArgumentException.
 * where, join, having e pre são SQL do desenvolvedor e DEVEM usar placeholders (?) para
 * qualquer valor vindo do usuário. Para expressões confiáveis fora do padrão use Raw::sql().
 */
class QueryBuilder
{
    private const IDENT = '(?:`[A-Za-z_][A-Za-z0-9_]*`|[A-Za-z_][A-Za-z0-9_]*)';
    private const AGGREGATES = 'COUNT|SUM|AVG|MIN|MAX';

    /**
     * As cláusulas armazenadas.
     *
     * @var array
     */
    private $clauses = [];

    private ConnectionInterface $connection;

    /**
     * Chama um método para definir uma cláusula.
     *
     * @param string $name      O nome do método chamado.
     * @param array  $arguments Os argumentos passados para o método.
     *
     * @throws InvalidArgumentException Se o nome da cláusula for inválido.
     *
     * @return $this
     */
    public function __call($name, $arguments)
    {
        $validClauses = ['table', 'fields', 'join', 'pre', 'where', 'group', 'order', 'having', 'limit'];
        if (!in_array(strtolower($name), $validClauses)) {
            throw new InvalidArgumentException('Nome de cláusula inválido: ' . $name);
        }

        $clause = $arguments[0];
        if (count($arguments) > 1) {
            $clause = $arguments;
        }
        $this->clauses[strtolower($name)] = $clause;

        return $this;
    }

    /**
     * Construtor da classe QueryBuilder.
     *
     * @param array $options As opções de configuração do banco de dados.
     */
    public function __construct($options)
    {
        if ($options instanceof ConnectionInterface) {
            $this->connection = $options;
        } elseif (is_array($options)) {
            $this->connection = ConnectionManager::get($options);
        } else {
            throw new InvalidArgumentException('QueryBuilder espera um array de opções ou ConnectionInterface.');
        }
    }

    public function connection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Compatibilidade: antes o QueryBuilder herdava de Connection.
     * Prefira connection()->connect().
     */
    public function connect(): \PDO
    {
        return $this->connection->connect();
    }

    /** @deprecated Prefira connection()->executeInsert(). */
    public function executeInsert($sql, array $values): ?string
    {
        return $this->connection->executeInsert($sql, $values);
    }

    /** @deprecated Prefira connection()->executeSelect(). */
    public function executeSelect($sql, array $values): ?array
    {
        return $this->connection->executeSelect($sql, $values);
    }

    /** @deprecated Prefira connection()->executeUpdate(). */
    public function executeUpdate($sql, array $values): int
    {
        return $this->connection->executeUpdate($sql, $values);
    }

    /** @deprecated Prefira connection()->executeDelete(). */
    public function executeDelete($sql, array $values): int
    {
        return $this->connection->executeDelete($sql, $values);
    }

    public function transaction(callable $callback)
    {
        return $this->connection->transaction($callback);
    }

    /**
     * Fecha a conexão compartilhada do pool para esta configuração.
     */
    public function close(): void
    {
        $this->connection->close();
    }

    /**
     * Executa uma instrução INSERT.
     *
     * @param array $values Os valores a serem inseridos.
     *
     * @throws InvalidArgumentException Se as cláusulas 'table' e 'fields' não estiverem definidas.
     *
     * @return mixed O resultado da execução da instrução INSERT.
     */
    public function insert($values)
    {
        $this->validateClause('table');
        $this->validateClause('fields');

        $table = $this->safeTable($this->clauses['table']);
        $fieldsList = array_map(
            fn ($field) => $this->safeColumn($field, 'fields'),
            is_array($this->clauses['fields']) ? array_values($this->clauses['fields']) : $this->splitList($this->clauses['fields'])
        );
        $fields = implode(', ', $fieldsList);
        $placeholders = implode(', ', array_fill(0, count($fieldsList), '?'));

        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";

        $this->invalidateCache($table);

        return $this->connection->executeInsert($sql, $values);
    }

    /**
     * Executa uma instrução SELECT.
     *
     * @param array $values Os valores a serem usados nos placeholders da consulta.
     *
     * @throws InvalidArgumentException Se as cláusulas 'table' e 'fields' não estiverem definidas.
     *
     * @return mixed O resultado da execução da instrução SELECT.
     */
    public function select($values = [])
    {
        $this->validateClause('table');
        $this->validateClause('fields');

        $table = $this->safeTable($this->clauses['table']);
        $fields = $this->safeSelectFields($this->clauses['fields']);
        $join = $this->joinSql();
        $preSelect = $this->rawClauseString('pre');

        $sql = $preSelect . ' SELECT ' . $fields . ' FROM ' . $table;

        if ($join) {
            $sql .= ' ' . $join;
        }

        $clauseConfigs = [
            'where' => ['instruction' => 'WHERE', 'separator' => ' AND '],
            'group' => ['instruction' => 'GROUP BY', 'separator' => ', '],
            'order' => ['instruction' => 'ORDER BY', 'separator' => ', '],
            'having' => ['instruction' => 'HAVING', 'separator' => ' AND '],
            'limit' => ['instruction' => 'LIMIT', 'separator' => ', '],
        ];

        foreach ($clauseConfigs as $key => $defaultCfg) {
            if (!$this->hasClause($key)) {
                continue;
            }

            // Metadados instruction/separator não podem introduzir SQL: só os valores padrão são aceitos.
            [$clauseData, $separator] = $this->extractClauseMeta($key, $this->clauses[$key], $defaultCfg);
            $clauseData = $this->sanitizeSelectClause($key, $clauseData);
            $parts = is_array($clauseData) ? $this->nonEmpty($clauseData) : [(string) $clauseData];
            $clauseStr = trim(implode($separator, array_map('strval', $parts)));
            if ($clauseStr !== '') {
                $sql .= ' ' . $defaultCfg['instruction'] . ' ' . $clauseStr;
            }
        }

        // Cache desligado, transação aberta (pelo builder ou direto no PDO) ou parâmetros que não são
        // escalares: lê sempre do banco. Assim um rollback não deixa leituras não confirmadas no cache, e
        // parâmetros como objetos Stringable seguem o bind do PDO sem passar pela chave do cache.
        if (!$this->connection->queryCacheEnabled() || $this->connection->inTransaction() || !self::parametrosCacheaveis($values)) {
            return $this->connection->executeSelect($sql, $values);
        }

        // serialize() (e não json_encode) para que parâmetros binários não colapsem em false.
        $cacheKey = md5($sql . "\0" . serialize($values) . "\0" . $table);

        $cache = new SessionCache($this->cacheKeyFor($table));
        $result = $cache->get($cacheKey);

        if ($result === null) {
            $result = $this->connection->executeSelect($sql, $values);
            $cache->set($cacheKey, $result);
        }

        return $result;
    }

    /**
     * Executa uma instrução UPDATE.
     *
     * @param array $values  Os valores a serem atualizados.
     * @param array $filters Os filtros para a cláusula WHERE.
     *
     * @throws InvalidArgumentException Se as cláusulas 'table' e 'fields' não estiverem definidas.
     *
     * @return mixed O resultado da execução da instrução UPDATE.
     */
    public function update($values, $filters = [])
    {
        $this->validateClause('table');
        $this->validateClause('fields');

        $table = $this->safeTable($this->clauses['table']);
        $this->rejeitarOpcoesDeLeitura('update');
        $join = $this->joinSql();
        $sets = $this->safeSets($this->clauses['fields']);
        $where = $this->requiredWhere('update');

        $sql = "UPDATE {$table} ";
        if ($join) {
            $sql .= "{$join} ";
        }
        $sql .= "SET {$sets}{$where}";

        $this->invalidateCache($table);

        return $this->connection->executeUpdate($sql, array_merge($values, $filters));
    }

    /**
     * Executa uma instrução DELETE.
     *
     * @param array $filters Os filtros para a cláusula WHERE.
     *
     * @throws InvalidArgumentException Se a cláusula 'table' não estiver definida.
     *
     * @return mixed O resultado da execução da instrução DELETE.
     */
    public function delete($filters = [])
    {
        $this->validateClause('table');

        $table = $this->safeTable($this->clauses['table']);
        if ($this->hasClause('join')) {
            throw new InvalidArgumentException('delete() não aceita join; use deleteFull() (somente MySQL).');
        }
        $this->rejeitarOpcoesDeLeitura('delete');

        $sql = 'DELETE FROM ' . $this->deleteTarget($table, $this->clauses['table'] instanceof Raw) . $this->requiredWhere('delete');

        $this->invalidateCache($table);

        return $this->connection->executeDelete($sql, $filters);
    }

    /**
     * Executa uma instrução DELETE completa.
     *
     * @param array $values  Os valores a serem utilizados na instrução DELETE.
     * @param array $filters Os filtros para a cláusula WHERE.
     *
     * @throws InvalidArgumentException Se a cláusula 'table' não estiver definida.
     *
     * @return mixed O resultado da execução da instrução DELETE completa.
     */
    public function deleteFull($values, $filters = [])
    {
        $this->validateClause('table');

        $table = $this->safeTable($this->clauses['table']);
        $this->rejeitarOpcoesDeLeitura('deleteFull');

        // where obrigatório: um where vazio com join apagaria todas as linhas alcançadas pelo join.
        $where = $this->requiredWhere('deleteFull');

        if ($this->hasClause('join')) {
            // Exclusão multi-tabela só existe no MySQL; o alvo é o alias (ou o nome) da tabela base.
            if ($this->connection->driver() !== 'mysql') {
                throw new InvalidArgumentException('deleteFull() com join só é suportado no MySQL.');
            }
            if ($this->clauses['table'] instanceof Raw) {
                throw new InvalidArgumentException('deleteFull() com join exige a tabela base como nome (não Raw) para definir o alvo da exclusão.');
            }
            $partes = preg_split('/\s+/', $table);
            $alvo = end($partes);
            $sql = "DELETE {$alvo} FROM {$table} " . $this->joinSql() . $where;
        } else {
            $sql = 'DELETE FROM ' . $this->deleteTarget($table, $this->clauses['table'] instanceof Raw) . $where;
        }

        $this->invalidateCache($table);

        return $this->connection->executeDelete($sql, array_merge($values, $filters));
    }

    /**
     * Só parâmetros escalares (ou null) entram na chave do cache: a representação deles preserva a
     * semântica do bind. Qualquer outro tipo (ex.: objeto Stringable) faz a leitura ir direto ao banco.
     */
    private static function parametrosCacheaveis(array $values): bool
    {
        foreach ($values as $valor) {
            if ($valor !== null && !is_scalar($valor)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Chave de cache por tabela base (sem alias), para que escritas invalidem leituras com alias.
     */
    private function cacheKeyFor(string $table): string
    {
        $base = str_replace('`', '', preg_split('/\s+/', trim($table))[0]);
        // Particiona pela identidade da conexão: bancos/tenants diferentes nunca compartilham resultados.
        return 'olivia_qc:' . $this->connection->identity() . ':' . $base;
    }

    /**
     * Invalida o cache da tabela sem iniciar sessão quando o cache está desligado
     * (CLI, workers e APIs sem sessão não ganham uma sessão PHP por causa de uma escrita).
     */
    private function invalidateCache(string $table): void
    {
        if (!$this->connection->queryCacheEnabled() && session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            new SessionCache($this->cacheKeyFor($table)); // inicia a sessão
        }

        // Invalida TODAS as tabelas desta conexão: leituras com join, schema qualificado ou views
        // dependem de tabelas que a chave da leitura não nomeia.
        $prefixo = 'olivia_qc:' . $this->connection->identity() . ':';
        foreach (array_keys($_SESSION ?? []) as $chave) {
            if (is_string($chave) && strpos($chave, $prefixo) === 0) {
                unset($_SESSION[$chave]);
            }
        }
    }

    /**
     * WHERE obrigatório para escrita: vazio lança exceção (para afetar tudo, use where('1 = 1') explicitamente).
     * Arrays seguem as mesmas regras do select: condições unidas por AND (ou separator OR) e metadados validados.
     */
    /**
     * group/having/order/limit não têm semântica portável em escritas: antes eram descartados em silêncio
     * e a escrita atingia todo o conjunto filtrado. Agora a intenção é recusada explicitamente.
     */
    private function rejeitarOpcoesDeLeitura(string $operacao): void
    {
        foreach (['group', 'having', 'limit', 'order'] as $unsupported) {
            if ($this->hasClause($unsupported)) {
                throw new InvalidArgumentException("{$operacao}() não suporta {$unsupported}; restrinja pelo where.");
            }
        }
    }

    private function requiredWhere(string $operacao): string
    {
        if (!$this->hasClause('where')) {
            throw new InvalidArgumentException("{$operacao}() exige where; para afetar todas as linhas use where('1 = 1').");
        }

        [$dados, $separador] = $this->extractClauseMeta('where', $this->clauses['where'], ['instruction' => 'WHERE', 'separator' => ' AND ']);
        $partes = is_array($dados) ? $this->nonEmpty($dados) : [$dados];
        foreach ($partes as $parte) {
            if (!is_string($parte) && !$parte instanceof Raw) {
                throw new InvalidArgumentException("Condição inválida em where de {$operacao}().");
            }
        }
        $sql = trim(implode($separador, array_map('strval', $partes)));
        if ($sql === '') {
            throw new InvalidArgumentException("{$operacao}() exige where; para afetar todas as linhas use where('1 = 1').");
        }

        return ' WHERE ' . $sql;
    }

    /**
     * Tabela alvo de DELETE simples, com alias normalizado para "tabela AS alias"
     * (aceito por SQLite e MySQL >= 8.0.16). Tabela Raw é SQL do desenvolvedor e passa intacta.
     */
    private function deleteTarget(string $table, bool $raw = false): string
    {
        if ($raw) {
            return $table;
        }
        $partes = preg_split('/\s+/', $table);
        return count($partes) > 1 ? $partes[0] . ' AS ' . end($partes) : $table;
    }

    private function joinSql(): string
    {
        return $this->rawClauseString('join');
    }

    /**
     * join/pre são SQL do desenvolvedor: aceitam string, Raw ou lista de strings — nunca arrays aninhados.
     */
    private function rawClauseString(string $nome): string
    {
        if (!$this->hasClause($nome)) {
            return '';
        }
        $valor = $this->clauses[$nome];
        $itens = is_array($valor) ? $valor : [$valor];
        foreach ($itens as $item) {
            if (!is_string($item) && !$item instanceof Raw) {
                throw new InvalidArgumentException("Valor inválido em {$nome}: use string ou Raw::sql().");
            }
        }
        return implode(' ', array_map('strval', $itens));
    }

    /**
     * Cláusula presente: 0 e "0" contam como valor (limit(0)); null, '' e [] não.
     */
    private function hasClause(string $name): bool
    {
        if (!array_key_exists($name, $this->clauses)) {
            return false;
        }
        $value = $this->clauses[$name];
        return $value !== null && $value !== '' && $value !== [];
    }

    private function nonEmpty(array $items): array
    {
        return array_values(array_filter($items, fn ($item) => $item !== null && $item !== ''));
    }

    /**
     * Remove e valida os metadados instruction/separator de cláusulas em array.
     * Só a instrução padrão da cláusula e separadores padrão são aceitos.
     *
     * @return array{0: mixed, 1: string}
     */
    private function extractClauseMeta(string $key, $clauseData, array $defaultCfg): array
    {
        $separator = $defaultCfg['separator'];
        if (!is_array($clauseData)) {
            return [$clauseData, $separator];
        }

        if (array_key_exists('instruction', $clauseData)) {
            $instruction = strtoupper(preg_replace('/\s+/', ' ', trim((string) $clauseData['instruction'])));
            if ($instruction !== $defaultCfg['instruction']) {
                throw new InvalidArgumentException("Instrução inválida em {$key}: use Raw::sql() para SQL livre.");
            }
            unset($clauseData['instruction']);
        }

        if (array_key_exists('separator', $clauseData)) {
            $requested = strtoupper(trim((string) $clauseData['separator']));
            $allowed = in_array($key, ['where', 'having'], true) ? ['AND', 'OR'] : [','];
            if (!in_array($requested, $allowed, true)) {
                throw new InvalidArgumentException("Separador inválido em {$key}.");
            }
            $separator = $requested === ',' ? ', ' : ' ' . $requested . ' ';
            unset($clauseData['separator']);
        }

        return [$clauseData, $separator];
    }

    private function qualified(): string
    {
        return self::IDENT . '(?:\.' . self::IDENT . ')?';
    }

    private function alias(): string
    {
        return '(?:\s+(?:AS\s+)?' . self::IDENT . ')?';
    }

    private function safeTable($table): string
    {
        if ($table instanceof Raw) {
            return (string) $table;
        }
        $table = trim((string) $table);
        if (preg_match('/^' . $this->qualified() . $this->alias() . '$/i', $table) !== 1) {
            throw new InvalidArgumentException('Nome de tabela inválido: ' . $table);
        }
        return $table;
    }

    private function safeColumn($column, string $clause): string
    {
        if ($column instanceof Raw) {
            return (string) $column;
        }
        $column = trim((string) $column);
        if (preg_match('/^' . $this->qualified() . '$/', $column) !== 1) {
            throw new InvalidArgumentException("Identificador inválido em {$clause}: {$column}");
        }
        return $column;
    }

    private function safeSelectFields($fields): string
    {
        $items = is_array($fields) ? array_values($fields) : $this->splitList($fields);
        if ($items === []) {
            throw new InvalidArgumentException('A cláusula "fields" não pode ser vazia');
        }

        $q = $this->qualified();
        $column = '(?:\*|' . self::IDENT . '\.\*|' . $q . '|(?:' . self::AGGREGATES . ')\s*\(\s*(?:DISTINCT\s+)?(?:\*|' . $q . ')\s*\))';
        $pattern = '/^(?:DISTINCT\s+)?' . $column . $this->alias() . '$/i';

        $safe = [];
        foreach ($items as $index => $item) {
            if ($item instanceof Raw) {
                $safe[] = (string) $item;
                continue;
            }
            $item = trim((string) $item);
            if (preg_match($pattern, $item) !== 1 || ($index > 0 && preg_match('/^DISTINCT\s/i', $item) === 1)) {
                throw new InvalidArgumentException('Campo inválido em fields: ' . $item . ' (use Raw::sql() para expressões confiáveis)');
            }
            $safe[] = $item;
        }

        return implode(', ', $safe);
    }

    private function safeSets($fields): string
    {
        if ($fields instanceof Raw) {
            return (string) $fields;
        }
        if (is_array($fields)) {
            return implode(', ', array_map(fn ($field) => $this->safeColumn($field, 'fields') . ' = ?', array_values($fields)));
        }

        $sets = trim((string) $fields);
        $assignment = $this->qualified() . '\s*=\s*\?';
        if (preg_match('/^' . $assignment . '(?:\s*,\s*' . $assignment . ')*$/', $sets) === 1) {
            return $sets;
        }
        if (preg_match('/^' . $this->qualified() . '$/', $sets) === 1) {
            return $sets . ' = ?';
        }
        throw new InvalidArgumentException('SET inválido em update: ' . $sets);
    }

    /**
     * Valida group/order/limit; where/having seguem como SQL do desenvolvedor.
     */
    private function sanitizeSelectClause(string $key, $clauseData)
    {
        if (!in_array($key, ['group', 'order', 'limit'], true)) {
            return $clauseData;
        }

        if ($key === 'limit') {
            $safe = [$this->safeLimit($clauseData)];
        } else {
            $items = [];
            foreach ((array) (is_array($clauseData) ? $clauseData : [$clauseData]) as $item) {
                if ($item instanceof Raw) {
                    $items[] = $item;
                    continue;
                }
                if (!is_string($item) && !is_int($item)) {
                    throw new InvalidArgumentException("Item inválido em {$key}: use string, inteiro ou Raw::sql().");
                }
                foreach ($this->splitList($item) as $part) {
                    $items[] = $part;
                }
            }
            $safe = array_map(fn ($item) => $key === 'order' ? $this->safeOrderItem($item) : $this->safeColumn($item, 'group'), $items);
        }

        return $safe;
    }

    private function safeOrderItem($item): string
    {
        if ($item instanceof Raw) {
            return (string) $item;
        }
        $item = trim((string) $item);
        if (preg_match('/^' . $this->qualified() . '(?:\s+(?:ASC|DESC))?$/i', $item) !== 1) {
            throw new InvalidArgumentException('Item inválido em order: ' . $item);
        }
        return $item;
    }

    private function safeLimit($limit): string
    {
        if ($limit instanceof Raw) {
            return (string) $limit;
        }
        if (is_array($limit)) {
            $limit = array_values(array_filter($limit, fn ($v) => $v !== null && $v !== ''));
            if (count($limit) === 1) {
                $limit = $limit[0];
            } elseif (count($limit) === 2 && $this->isNonNegativeInt($limit[0]) && $this->isNonNegativeInt($limit[1])) {
                return (int) $limit[0] . ', ' . (int) $limit[1];
            } else {
                throw new InvalidArgumentException('Limit inválido.');
            }
        }

        $limit = trim((string) $limit);
        if (preg_match('/^(\d+)(?:\s*,\s*(\d+)|\s+OFFSET\s+(\d+))?$/i', $limit, $m) !== 1) {
            throw new InvalidArgumentException('Limit inválido: ' . $limit);
        }
        if (isset($m[3]) && $m[3] !== '') {
            return (int) $m[1] . ' OFFSET ' . (int) $m[3];
        }
        return isset($m[2]) && $m[2] !== '' ? (int) $m[1] . ', ' . (int) $m[2] : (string) (int) $m[1];
    }

    private function isNonNegativeInt($value): bool
    {
        return is_int($value) ? $value >= 0 : (is_string($value) && ctype_digit($value));
    }

    /**
     * Divide uma lista separada por vírgulas respeitando parênteses.
     */
    private function splitList($value): array
    {
        if ($value instanceof Raw) {
            return [$value];
        }
        $value = (string) $value;
        $parts = [];
        $depth = 0;
        $current = '';
        $length = strlen($value);
        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }
            if ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $parts[] = trim($current);

        return array_values(array_filter($parts, fn ($part) => $part !== ''));
    }

    /**
     * Valida se uma cláusula está definida.
     *
     * @param string $name O nome da cláusula a ser validada.
     *
     * @throws InvalidArgumentException Se a cláusula não estiver definida.
     */
    private function validateClause($name)
    {
        if (!isset($this->clauses[$name])) {
            throw new InvalidArgumentException('A cláusula "' . $name . '" é obrigatória');
        }
    }
}