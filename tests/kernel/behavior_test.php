<?php

/**
 * Testes de comportamento do kernel: CRUD real via SQLite e despacho do roteador.
 * Executar: php tests/kernel/behavior_test.php
 */

namespace {
    require dirname(__DIR__, 2) . '/vendor/autoload.php';
}

namespace OliviaTest\Controller {
    class ProbeController
    {
        public static array $calls = [];

        public function show($params)
        {
            self::$calls[] = ['show', $params['id'] ?? null];
        }

        public function save($params)
        {
            self::$calls[] = ['save', $params['method'] ?? null];
        }
    }
}

namespace OliviaTest\Middleware {
    class SideEffect
    {
        public static int $calls = 0;

        public function handle()
        {
            self::$calls++; // efeito colateral observável (log, sessão, chamada externa...)
            return true;
        }
    }
}

namespace OliviaTest\Models {
    use OliviaDatabaseModel\Model;

    class Item extends Model
    {
        protected $table = 'items';
        protected $drive = 'sqlite';
        protected $fillable = ['nome', 'valor'];
        protected $atributos = ['id', 'nome', 'valor'];
    }
}

namespace {
    $root = dirname(__DIR__, 2);

    $failures = 0;

    function check($condition, string $message): void
    {
        global $failures;
        echo ($condition ? 'OK   ' : 'FAIL ') . $message . "\n";
        if (!$condition) {
            $failures++;
        }
    }

    /**
     * $fn lança exatamente $classe (não subclasse nem Error genérico) e, se informado, a mensagem contém $mensagem.
     */
    function throws(callable $fn, string $classe, ?string $mensagem = null): bool
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            return get_class($e) === ltrim($classe, '\\') && ($mensagem === null || strpos($e->getMessage(), $mensagem) !== false);
        }
        return false;
    }

    /**
     * Fotografia do estado global que os grupos alteram: variáveis de ambiente, $_ENV, sessão e superglobais.
     */
    function capturarAmbiente(): array
    {
        return [
            'env' => getenv(),
            '_ENV' => $_ENV,
            'session' => $_SESSION ?? [],
            'post' => $_POST,
            'get' => $_GET,
            'request' => $_REQUEST,
            'server' => $_SERVER,
            'files' => $_FILES,
            'cookie' => $_COOKIE,
        ];
    }

    /**
     * Restaura a fotografia: variáveis criadas depois são removidas e as alteradas voltam ao valor anterior.
     */
    function restaurarAmbiente(array $foto): void
    {
        $atual = getenv();
        foreach (array_keys($atual) as $nome) {
            if (!array_key_exists($nome, $foto['env'])) {
                putenv((string) $nome);
            }
        }
        foreach ($foto['env'] as $nome => $valor) {
            if (($atual[$nome] ?? null) !== $valor) {
                putenv($nome . '=' . $valor);
            }
        }
        $_ENV = $foto['_ENV'];
        $_SESSION = $foto['session'];
        $_POST = $foto['post'];
        $_GET = $foto['get'];
        $_REQUEST = $foto['request'];
        $_SERVER = $foto['server'];
        $_FILES = $foto['files'];
        $_COOKIE = $foto['cookie'];
    }

    /**
     * Início de grupo: volta ao ambiente-base capturado após o setup, para que nenhum grupo dependa do
     * estado deixado pelo anterior.
     */
    function grupo(string $nome): void
    {
        global $ambienteBase;
        restaurarAmbiente($ambienteBase);
    }

    /**
     * Executa PHP em subprocesso com o ambiente atual (+ $env) e devolve [stdout, stderr, código de saída].
     * stderr é devolvido para que o check exija ausência de warnings em vez de escondê-los.
     */
    function executarPhp(array $args, array $env = []): array
    {
        $proc = proc_open(array_merge([PHP_BINARY], $args), [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv(), $env));
        $saida = stream_get_contents($pipes[1]);
        $erros = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [(string) $saida, (string) $erros, proc_close($proc)];
    }

    /**
     * Sobe o servidor embutido do PHP (php -S) numa porta livre com $roteador; devolve [processo, porta, log].
     */
    function servidorHttp(string $roteador, array $ini, array $env): array
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0');
        $nome = stream_socket_get_name($sock, false);
        fclose($sock);
        $porta = (int) substr((string) strrchr((string) $nome, ':'), 1);
        $log = dirname($roteador) . '/servidor.log';
        $args = [PHP_BINARY];
        foreach ($ini as $chave => $valor) {
            $args[] = '-d';
            $args[] = $chave . '=' . $valor;
        }
        array_push($args, '-S', '127.0.0.1:' . $porta, '-t', dirname($roteador), $roteador);
        $proc = proc_open($args, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, null, array_merge(getenv(), $env));
        for ($i = 0; $i < 100; $i++) {
            $c = @fsockopen('127.0.0.1', $porta, $errno, $errstr, 0.2);
            if ($c) {
                fclose($c);
                break;
            }
            usleep(50000);
        }

        return [$proc, $porta, $log];
    }

    /**
     * Requisição HTTP/1.0 crua; devolve ['status' => int, 'headers' => [nome-minúsculo => [valores]], 'body' => string].
     */
    function requisicaoHttp(int $porta, string $metodo, string $caminho, array $headers = [], string $corpo = ''): array
    {
        $c = @fsockopen('127.0.0.1', $porta, $errno, $errstr, 5);
        if (!$c) {
            return ['status' => 0, 'headers' => [], 'body' => ''];
        }
        stream_set_timeout($c, 15);
        $temHost = false;
        foreach ($headers as $h) {
            $temHost = $temHost || stripos($h, 'host:') === 0;
        }
        $req = $metodo . ' ' . $caminho . " HTTP/1.0\r\n" . ($temHost ? '' : "Host: 127.0.0.1\r\n");
        foreach ($headers as $h) {
            $req .= $h . "\r\n";
        }
        if ($corpo !== '') {
            $req .= 'Content-Length: ' . strlen($corpo) . "\r\n";
        }
        fwrite($c, $req . "Connection: close\r\n\r\n" . $corpo);
        $resposta = (string) stream_get_contents($c);
        fclose($c);
        [$cabeca, $body] = array_pad(explode("\r\n\r\n", $resposta, 2), 2, '');
        $linhas = explode("\r\n", $cabeca);
        $status = (int) (explode(' ', (string) array_shift($linhas))[1] ?? 0);
        $hs = [];
        foreach ($linhas as $linha) {
            [$n, $v] = array_pad(explode(':', $linha, 2), 2, '');
            $hs[strtolower(trim($n))][] = trim($v);
        }

        return ['status' => $status, 'headers' => $hs, 'body' => $body];
    }

    /**
     * Executa $fn e devolve o resultado; exceção vira $padrao, para o check registrar FAIL em vez de abortar.
     */
    function seguro(callable $fn, $padrao = false)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            return $padrao;
        }
    }

    // Ambiente limpo: variáveis do framework herdadas do processo (ex.: OLIVIA_APP_KEY, OLIVIA_QUERY_CACHE)
    // não podem mudar o resultado da suíte. O ambiente original é restaurado ao final.
    $ambienteOriginal = capturarAmbiente();
    $doFramework = fn ($nome) => preg_match('/^(OLIVIA_|DB_|APP_DEBUG$)/', (string) $nome) === 1;
    foreach (array_keys(getenv()) as $nome) {
        if ($doFramework($nome)) {
            putenv((string) $nome);
        }
    }
    foreach (array_keys($_ENV) as $nome) {
        if ($doFramework($nome)) {
            unset($_ENV[$nome]);
        }
    }
    foreach (array_keys($_SERVER) as $nome) {
        if ($doFramework($nome)) {
            unset($_SERVER[$nome]);
        }
    }

    $tmp = sys_get_temp_dir() . '/olivia-behavior-' . bin2hex(random_bytes(4));
    mkdir($tmp, 0700, true);
    ini_set('session.save_path', $tmp);
    ini_set('session.use_cookies', '0');
    session_start();

    // ---------- Database / Model ----------
    $dbFile = $tmp . '/test.sqlite';
    putenv('OLIVIA_DB_DSN');
    putenv('DB_DRIVER=sqlite');
    putenv('DB_DATABASE=' . $dbFile);
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->exec('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, valor TEXT)');
    $ambienteBase = capturarAmbiente();
    check(getenv('OLIVIA_APP_KEY') === false && getenv('OLIVIA_QUERY_CACHE') === false && !isset($_SERVER['OLIVIA_APP_KEY']), 'QA-314 suíte parte de ambiente limpo (variáveis herdadas do framework removidas)');

    $item = new \OliviaTest\Models\Item();
    // Atribuição fora da ordem de $fillable: os valores devem seguir a ordem de fillable.
    $item->valor = 'v1';
    $item->nome = 'primeiro';
    check($item->values() === ['primeiro', 'v1'], 'Model::values segue a ordem de fillable');
    $id1 = $item->save();
    check($id1 === '1', 'Model::save insere e retorna lastInsertId');

    $item2 = new \OliviaTest\Models\Item();
    $item2->nome = 'segundo';
    $item2->valor = 'v2';
    $id2 = $item2->save();

    $found = $item->findForId($id2);
    check(is_array($found) && ($found[0]->nome ?? null) === 'segundo' && ($found[0]->valor ?? null) === 'v2', 'findForId retorna colunas corretas');

    $affected = $item->update(['valor'], ['id = ?'], ['alterado', $id2]);
    check($affected === 1, 'Model::update executa e retorna linhas afetadas');
    $found = $item->findForId($id2);
    check(($found[0]->valor ?? null) === 'alterado', 'update persiste e invalida o cache da tabela');

    // delete sem $id deve usar o id real do model, e nunca isset() (=> id 1)
    $alvo = new \OliviaTest\Models\Item();
    $alvo->id = $id2;
    check($alvo->delete() === 1, 'Model::delete sem argumento usa $this->id');
    check(count($item->findForId($id1)) === 1, 'Model::delete não apaga o registro id=1');
    check(count($item->findForId($id2)) === 0, 'Model::delete removeu o registro alvo');
    $linhasAntes = (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
    check(throws(fn () => (new \OliviaTest\Models\Item())->delete(), \InvalidArgumentException::class, 'requer um identificador') && (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn() === $linhasAntes, 'Model::delete sem identificador lança InvalidArgumentException e não apaga nada');

    // Sem join, Model::deleteFull usa o DELETE simples (QueryBuilder::deleteFull é coberto no grupo DB-310)
    $pdo->exec("INSERT INTO items(id, nome, valor) VALUES (91, 'vizinho', 'v')");
    check($item->deleteFull($id1) === 1 && count($item->findForId($id1)) === 0 && count($item->findForId(91)) === 1, 'Model::deleteFull sem join apaga só o registro alvo');
    $pdo->exec('DELETE FROM items WHERE id = 91');

    // ---------- SEC-301: identificadores validados no QueryBuilder ----------
    grupo('SEC-301');
    $qb = fn () => new \OliviaDatabaseLibrary\QueryBuilder(\OliviaLib\Database::getDB('sqlite'));
    $ataques = [
        'order subselect' => fn () => $item->find(null, null, [], null, null, '(SELECT 1/0)'),
        'order com ;' => fn () => $item->find(null, null, [], null, null, 'nome; DROP TABLE items'),
        'order com CASE' => fn () => $item->find(null, null, [], null, null, 'CASE WHEN 1=1 THEN nome END'),
        'group com subselect' => fn () => $item->find(null, null, [], null, '(SELECT 1)'),
        'limit com UNION' => fn () => $item->find(null, null, [], null, null, null, null, '1 UNION SELECT 1'),
        'limit negativo' => fn () => $item->find(null, null, [], null, null, null, null, '-1'),
        'fields com subselect' => fn () => $item->find(['(SELECT sqlite_version())'], null, []),
        'fields com comentario' => fn () => $item->find(['nome -- x'], null, []),
        'table com injeção' => fn () => $qb()->table('items; DROP TABLE items')->fields('*')->select(),
        'insert com coluna inválida' => fn () => $qb()->table('items')->fields(['nome) VALUES (1); --'])->insert(['x']),
        'update SET com subselect' => fn () => $qb()->table('items')->fields('nome = (SELECT 1)')->where('id = ?')->update([], [1]),
    ];
    foreach ($ataques as $nome => $ataque) {
        $erro = null;
        try {
            $ataque();
        } catch (\Throwable $e) {
            $erro = $e;
        }
        check($erro instanceof \InvalidArgumentException, "SEC-301 rejeita {$nome}");
    }
    check($pdo->query("SELECT name FROM sqlite_master WHERE name = 'items'")->fetchColumn() === 'items', 'SEC-301 tabela intacta após tentativas');

    // regressão: consultas legítimas, sobre um conjunto fixo para conferir linhas e valores exatos
    $pdo->exec('DELETE FROM items');
    $pdo->exec("INSERT INTO items(id, nome, valor) VALUES (11, 'beta', 'x'), (12, 'alfa', 'y'), (13, 'beta', 'x'), (14, 'gamma5', 'z')");
    $idsDe = fn ($linhas, $col = 'id') => array_map(fn ($l) => is_numeric($l->$col ?? null) ? (int) $l->$col : ($l->$col ?? null), (array) $linhas);
    $porNomeDesc = $item->find(['id', 'nome'], null, [], null, null, 'nome DESC, id ASC');
    check($idsDe($porNomeDesc) === [14, 11, 13, 12], 'SEC-301 order "col DESC, col ASC" aceito e aplicado (desempate por id)');
    $paginado = $item->find(['items.id', 'nome AS n'], null, [], null, null, ['id DESC'], null, '0, 1');
    check($idsDe($paginado) === [14] && ($paginado[0]->n ?? null) === 'gamma5', 'SEC-301 limit "offset, count", coluna qualificada e alias aceitos');
    $total = $item->count();
    check((int) ($total[0]->total ?? -1) === 4, 'SEC-301 count(*) as total aceito e exato');
    $agregado = $qb()->table('items i')->fields(['i.valor', 'COUNT(DISTINCT i.id) AS qtd'])->group('i.valor')->order(['i.valor'])->limit([0, 10])->select();
    check(array_map(fn ($l) => [$l->valor, (int) $l->qtd], (array) $agregado) === [['x', 2], ['y', 1], ['z', 1]], 'SEC-301 alias de tabela, agregado DISTINCT, group e limit em array aceitos');
    $raw = $qb()->table('items')->fields(['id', \OliviaDatabaseLibrary\Raw::sql("UPPER(nome) AS up")])->order(\OliviaDatabaseLibrary\Raw::sql('LENGTH(nome) DESC'))->limit(1)->select();
    check(count($raw) === 1 && ($raw[0]->up ?? null) === 'GAMMA5', 'SEC-301 Raw::sql permite expressão confiável');
    $offset = $qb()->table('items')->fields('id')->order('id')->limit('1 OFFSET 1')->select();
    check($idsDe($offset) === [12], 'SEC-301 limit "N OFFSET M" aceito e devolve a segunda linha');
    $atualizadas = $qb()->table('items')->fields('valor = ?')->where('id = ?')->update(['novo'], [13]);
    check($atualizadas === 1 && $pdo->query('SELECT valor FROM items WHERE id = 13')->fetchColumn() === 'novo' && $pdo->query('SELECT valor FROM items WHERE id = 11')->fetchColumn() === 'x', 'SEC-301 SET em string "col = ?" atualiza exatamente a linha alvo');
    $pdo->exec('DELETE FROM items');

    // request_cripto não deve gerar erro fatal e deve decifrar ids
    $cc = new \OliviaLib\CommandController();
    $_SESSION['secret_key'] = 'k-test';
    $_SESSION['secret_iv'] = 'iv-test';
    $model = new class extends \OliviaDatabaseModel\Model {
        protected $fillable = ['id_unidade', 'nome'];
    };
    $_REQUEST = ['id_unidade' => $cc->encryptIt('42'), 'nome' => 'x'];
    $model->request_cripto();
    check(($model->id_unidade ?? null) === '42' && ($model->nome ?? null) === 'x', 'request_cripto decifra ids sem erro fatal');
    $_REQUEST = [];

    // ---------- DB-306: pool de conexões e transações ----------
    grupo('DB-306');
    $cfg = \OliviaLib\Database::getDB('sqlite');
    $qb1 = new \OliviaDatabaseLibrary\QueryBuilder($cfg);
    $qb2 = new \OliviaDatabaseLibrary\QueryBuilder($cfg);
    check($qb1->connection() === $qb2->connection() && $qb1->connect() === $qb2->connect(), 'DB-306 mesma configuração reutiliza a mesma conexão e o mesmo PDO');
    check(!($qb1 instanceof \OliviaDatabaseLibrary\Connection), 'DB-306 QueryBuilder não herda de Connection');
    $outroArquivo = $tmp . '/outro.sqlite';
    $cfg2 = array_merge($cfg, ['database' => $outroArquivo]);
    check((new \OliviaDatabaseLibrary\QueryBuilder($cfg2))->connect() !== $qb1->connect(), 'DB-306 configuração diferente usa outro PDO');
    // Cada consulta do Model passa pelo PDO do pool: um PDO contador é injetado na conexão compartilhada e
    // precisa receber exatamente um prepare() por consulta (sem cache de sessão nem reconexão no meio).
    $poolAntes = \OliviaDatabaseLibrary\ConnectionManager::count();
    $conexaoPool = \OliviaDatabaseLibrary\ConnectionManager::get($cfg);
    $pdoPoolOriginal = $conexaoPool->connect();
    $PdoPool = new class ('sqlite::memory:') extends \PDO {
        public int $prepares = 0;
        public function prepare(string $query, array $options = []): \PDOStatement|false
        {
            $this->prepares++;
            return parent::prepare($query, $options);
        }
    };
    $pdoPool = new ($PdoPool::class)('sqlite:' . $dbFile);
    $pdoPool->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $refPdo = new \ReflectionProperty(\OliviaDatabaseLibrary\Connection::class, 'pdo');
    $refPdo->setValue($conexaoPool, $pdoPool);
    for ($i = 0; $i < 5; $i++) {
        $item->all();
        $item->count();
    }
    check($conexaoPool->queryCacheEnabled() === false && $pdoPool->prepares === 10, 'DB-306 cada consulta repetida executa no PDO do pool (10 consultas, 10 prepare)');
    check(\OliviaDatabaseLibrary\ConnectionManager::count() === $poolAntes && \OliviaDatabaseLibrary\ConnectionManager::get($cfg) === $conexaoPool && $conexaoPool->connect() === $pdoPool, 'DB-306 consultas repetidas não abrem novas conexões nem trocam o PDO');
    $refPdo->setValue($conexaoPool, $pdoPoolOriginal);
    $injetada = new \OliviaDatabaseLibrary\Connection($cfg);
    check((new \OliviaDatabaseLibrary\QueryBuilder($injetada))->connection() === $injetada, 'DB-306 QueryBuilder aceita ConnectionInterface injetada');
    check(throws(fn () => new \OliviaDatabaseLibrary\QueryBuilder('mysql'), \InvalidArgumentException::class, 'array de opções'), 'DB-306 QueryBuilder rejeita opções inválidas com InvalidArgumentException');
    check(!method_exists(\OliviaDatabaseLibrary\Connection::class, 'getInstance'), 'DB-306 Connection::getInstance removido');

    $contar = fn () => (int) $pdo->query('SELECT COUNT(*) FROM items')->fetchColumn();
    $antes = $contar();
    $ret = \OliviaTest\Models\Item::transaction(function () {
        $a = new \OliviaTest\Models\Item();
        $a->nome = 'tx1';
        $a->valor = '1';
        $a->save();
        $b = new \OliviaTest\Models\Item();
        $b->nome = 'tx2';
        $b->valor = '2';
        $b->save();
        return 'ok';
    });
    check($ret === 'ok' && $contar() === $antes + 2, 'DB-306 transaction() faz commit no sucesso');

    $antes = $contar();
    $relancou = false;
    try {
        \OliviaTest\Models\Item::transaction(function () use ($item) {
            $a = new \OliviaTest\Models\Item();
            $a->nome = 'tx3';
            $a->valor = '3';
            $a->save();
            // leitura dentro da transação não pode ir para o cache de sessão
            $dentro = $item->find(['id'], ["nome = ?"], ['tx3']);
            if (count($dentro) !== 1) {
                throw new \LogicException('leitura na transação não enxergou o insert');
            }
            throw new \DomainException('falha proposital');
        });
    } catch (\DomainException $e) {
        $relancou = $e->getMessage() === 'falha proposital';
    }
    check($relancou && $contar() === $antes, 'DB-306 transaction() faz rollback e relança a exceção');
    check(count($item->find(['id'], ["nome = ?"], ['tx3'])) === 0, 'DB-306 leitura feita na transação não ficou no cache após rollback');

    $antes = $contar();
    try {
        \OliviaTest\Models\Item::transaction(function () {
            \OliviaTest\Models\Item::transaction(function () {
                $a = new \OliviaTest\Models\Item();
                $a->nome = 'tx4';
                $a->valor = '4';
                $a->save();
            });
            throw new \RuntimeException('externa falhou');
        });
    } catch (\RuntimeException $e) {
    }
    check($contar() === $antes, 'DB-306 transação aninhada participa do rollback externo');
    check(!$qb1->connection()->inTransaction(), 'DB-306 estado de transação limpo após falha');
    $pdo->exec("DELETE FROM items WHERE nome LIKE 'tx%'");

    // Falha de conexão: conta as tentativas (dsn() é montado uma vez por tentativa), não o tempo
    $ConexaoContada = new class (['driver' => 'sqlite', 'host' => '', 'user' => '', 'password' => '', 'database' => $tmp . '/nao/existe/x.sqlite']) extends \OliviaDatabaseLibrary\Connection {
        public int $tentativas = 0;
        protected function dsn()
        {
            $this->tentativas++;
            return parent::dsn();
        }
    };
    $tentarConectar = function (array $extra) use ($ConexaoContada, $tmp) {
        $c = new ($ConexaoContada::class)(array_merge(['driver' => 'sqlite', 'host' => '', 'user' => '', 'password' => '', 'database' => $tmp . '/nao/existe/x.sqlite'], $extra));
        $erro = null;
        try {
            $c->connect();
        } catch (\Throwable $e) {
            $erro = $e;
        }
        return [$c->tentativas, $erro];
    };
    [$tentativas1, $erroConexao] = $tentarConectar(['retries' => 1]);
    check($tentativas1 === 2 && $erroConexao instanceof \PDOException && get_class($erroConexao) === \PDOException::class && strpos($erroConexao->getMessage(), 'Falha ao tentar conectar') === 0 && $erroConexao->getPrevious() instanceof \PDOException, 'DB-306 falha de conexão com retries=1 faz exatamente 2 tentativas e lança PDOException com a causa');
    [$tentativas0] = $tentarConectar(['retries' => 0]);
    [$tentativas3] = $tentarConectar(['retries' => 3]);
    check($tentativas0 === 1 && $tentativas3 === 4, 'DB-306 número de tentativas segue retries (0 => 1, 3 => 4)');
    putenv('OLIVIA_DB_CONNECT_RETRIES=2');
    [$tentativasEnv] = $tentarConectar([]);
    putenv('OLIVIA_DB_CONNECT_RETRIES');
    [$tentativasPadrao] = $tentarConectar([]);
    check($tentativasEnv === 3 && $tentativasPadrao === 2, 'DB-306 OLIVIA_DB_CONNECT_RETRIES define as tentativas; padrão é 1 nova tentativa');
    check(throws(fn () => new \OliviaDatabaseLibrary\Connection(['host' => '', 'user' => '', 'password' => '', 'retries' => -1]), \InvalidArgumentException::class, '"retries"'), 'DB-306 retries negativo é rejeitado com InvalidArgumentException');
    check(throws(fn () => new \OliviaDatabaseLibrary\Connection(['host' => '', 'user' => '', 'password' => '', 'persistent' => 'sim']), \InvalidArgumentException::class, '"persistent"'), 'DB-306 persistent não booleano é rejeitado com InvalidArgumentException');

    // SQL inválido não é repetido e mantém a mensagem de erro: conta os prepare() no PDO da conexão
    $PdoContado = new class ('sqlite::memory:') extends \PDO {
        public int $prepares = 0;
        public function prepare(string $query, array $options = []): \PDOStatement|false
        {
            $this->prepares++;
            return parent::prepare($query, $options);
        }
    };
    $conexaoSql = new \OliviaDatabaseLibrary\Connection($cfg);
    $pdoContado = new ($PdoContado::class)('sqlite:' . $dbFile);
    $pdoContado->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    (new \ReflectionProperty(\OliviaDatabaseLibrary\Connection::class, 'pdo'))->setValue($conexaoSql, $pdoContado);
    $erroSql = null;
    try {
        $conexaoSql->executeSelect('SELECT * FROM tabela_inexistente', []);
    } catch (\Exception $e) {
        $erroSql = $e;
    }
    check($erroSql !== null && get_class($erroSql) === \Exception::class && strpos($erroSql->getMessage(), 'Falha ao executar a consulta de seleção') === 0 && $erroSql->getPrevious() instanceof \PDOException && strpos($erroSql->getPrevious()->getMessage(), 'tabela_inexistente') !== false, 'DB-306 erro de SQL preserva mensagem e exceção original');
    check($pdoContado->prepares === 1 && $conexaoSql->connect() === $pdoContado, 'DB-306 SQL inválido roda prepare() uma única vez, sem reconectar');

    // Reconexão (DB-310): só leitura, só MySQL 2006/2013, fora de transação.
    $novoGoneAway = function (int $codigo = 2006) {
        return new class ('sqlite::memory:', $codigo) extends \PDO {
            public int $prepares = 0;
            public ?\PDOException $ultima = null;
            public function __construct(string $dsn, private int $codigo)
            {
                parent::__construct($dsn);
            }
            public function prepare(string $query, array $options = []): \PDOStatement|false
            {
                $this->prepares++;
                $e = new \PDOException("SQLSTATE[HY000]: General error: {$this->codigo} MySQL server has gone away");
                $e->errorInfo = ['HY000', $this->codigo, 'MySQL server has gone away'];
                throw $this->ultima = $e;
            }
        };
    };
    // Dublê: conecta de fato no SQLite de teste, mas se identifica como MySQL para a política de reconexão.
    $comoMysql = fn () => new class ($cfg) extends \OliviaDatabaseLibrary\Connection {
        public function driver(): string
        {
            return 'mysql';
        }
    };
    $injetar = fn ($conn, $pdoFalso) => (new \ReflectionProperty(\OliviaDatabaseLibrary\Connection::class, 'pdo'))->setValue($conn, $pdoFalso);

    $leitura = $comoMysql();
    $falso = $novoGoneAway(2006);
    $injetar($leitura, $falso);
    try {
        $linhas = $leitura->executeSelect('SELECT COUNT(*) AS total FROM items', []);
    } catch (\Exception $e) {
        $linhas = null;
    }
    check($falso->prepares === 1 && isset($linhas[0]->total) && $leitura->connect() !== $falso, 'DB-310 leitura MySQL 2006 reconecta uma única vez com PDO novo');

    $leitura2013 = $comoMysql();
    $falso2013 = $novoGoneAway(2013);
    $injetar($leitura2013, $falso2013);
    try {
        $um = $leitura2013->executeSelect('SELECT 1 AS um', []);
    } catch (\Exception $e) {
        $um = null;
    }
    check(is_array($um) && $falso2013->prepares === 1, 'DB-310 leitura MySQL 2013 reconecta');

    $escrita = $comoMysql();
    $falsoEscrita = $novoGoneAway(2006);
    $injetar($escrita, $falsoEscrita);
    $antesEscrita = $contar();
    $erroEscrita = null;
    try {
        $escrita->executeInsert('INSERT INTO items(nome, valor) VALUES (?, ?)', ['replay', 'x']);
    } catch (\Exception $e) {
        $erroEscrita = $e;
    }
    check($erroEscrita !== null && get_class($erroEscrita) === \Exception::class && $erroEscrita->getPrevious() === $falsoEscrita->ultima && $falsoEscrita->prepares === 1 && $contar() === $antesEscrita, 'DB-310 escrita após perda de conexão não é repetida e relança a causa original');

    $sqliteReal = new \OliviaDatabaseLibrary\Connection($cfg);
    $falsoSqlite = $novoGoneAway(2006);
    $injetar($sqliteReal, $falsoSqlite);
    $erroSqlite = null;
    try {
        $sqliteReal->executeSelect('SELECT 1', []);
    } catch (\Exception $e) {
        $erroSqlite = $e;
    }
    check($erroSqlite !== null && $falsoSqlite->prepares === 1, 'DB-310 driver não-MySQL nunca reconecta por código 2006');

    $naTransacao = $comoMysql();
    $falsoTx = $novoGoneAway(2006);
    $erroTx = null;
    try {
        $naTransacao->transaction(function ($c) use ($falsoTx, $injetar) {
            $injetar($c, $falsoTx);
            return $c->executeSelect('SELECT 1', []);
        });
    } catch (\Exception $e) {
        $erroTx = $e;
    }
    check($erroTx !== null && get_class($erroTx) === \Exception::class && strpos($erroTx->getMessage(), 'Falha ao executar a consulta de seleção') === 0 && $falsoTx->prepares === 1, 'DB-310 dentro de transação não reconecta (um único prepare)');
    check($erroTx !== null && $erroTx->getPrevious() === $falsoTx->ultima && ($falsoTx->ultima->errorInfo[1] ?? null) === 2006, 'DB-310 dentro de transação relança a mesma instância da causa (2006)');

    // Transação aberta direto no PDO também é detectada
    $pdoDireto = new \OliviaDatabaseLibrary\Connection($cfg);
    $pdoDireto->connect()->beginTransaction();
    check($pdoDireto->inTransaction() === true, 'DB-310 inTransaction() detecta transação aberta no PDO');
    $participou = $pdoDireto->transaction(fn () => 'dentro');
    check($participou === 'dentro' && $pdoDireto->connect()->inTransaction(), 'DB-310 transaction() participa da transação do PDO sem commit prematuro');
    $pdoDireto->connect()->rollBack();

    // Falha de rollBack não mascara a exceção original
    $rbFalha = new class ($cfg) extends \OliviaDatabaseLibrary\Connection {
        public function connect(): \PDO
        {
            static $pdo = null;
            return $pdo ??= new class ('sqlite::memory:') extends \PDO {
                public function rollBack(): bool
                {
                    throw new \PDOException('rollback quebrou');
                }
            };
        }
    };
    $causa = null;
    $logAnterior = ini_set('error_log', $tmp . '/rollback.log');
    try {
        $rbFalha->transaction(function () {
            throw new \DomainException('causa original');
        });
    } catch (\Throwable $e) {
        $causa = $e;
    }
    ini_set('error_log', (string) $logAnterior);
    check($causa instanceof \DomainException && $causa->getMessage() === 'causa original', 'DB-310 falha no rollBack preserva a exceção original');

    // PDO quebrado após falha de rollBack é descartado (antes: inTransaction() ficava true para sempre)
    $quebrado = $comoMysql();
    $pdoMorto = new class ('sqlite::memory:') extends \PDO {
        private bool $aberta = false;
        public function beginTransaction(): bool
        {
            return $this->aberta = true;
        }
        public function inTransaction(): bool
        {
            return $this->aberta; // conexão morta continua "achando" que a transação está aberta
        }
        public function rollBack(): bool
        {
            throw new \PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
        }
        public function prepare(string $query, array $options = []): \PDOStatement|false
        {
            $e = new \PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
            $e->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
            throw $e;
        }
    };
    $injetar($quebrado, $pdoMorto);
    $logAnterior = ini_set('error_log', $tmp . '/rollback.log');
    $causaTx = null;
    try {
        $quebrado->transaction(fn ($c) => $c->executeSelect('SELECT 1', []));
    } catch (\Exception $e) {
        $causaTx = $e;
    }
    ini_set('error_log', (string) $logAnterior);
    check($causaTx !== null && $causaTx->getPrevious() instanceof \PDOException, 'DB-310 perda de conexão na transação relança a causa');
    check($quebrado->inTransaction() === false && $quebrado->connect() !== $pdoMorto, 'DB-310 PDO quebrado é descartado após falha de rollBack');
    $novaTx = seguro(fn () => $quebrado->transaction(fn ($c) => $c->executeInsert('INSERT INTO items(nome, valor) VALUES (?, ?)', ['pos-falha', '1'])));
    check(is_string($novaTx) && count($item->find(['id'], ['nome = ?'], ['pos-falha'])) === 1, 'DB-310 transação seguinte faz commit normalmente');
    $pdo->exec("DELETE FROM items WHERE nome = 'pos-falha'");

    $pdo->exec("INSERT INTO items(id, nome, valor) VALUES (150, 'pool', 'p')");
    $conexaoAntesReset = \OliviaDatabaseLibrary\ConnectionManager::get($cfg);
    $pdoAntesReset = $conexaoAntesReset->connect();
    \OliviaDatabaseLibrary\ConnectionManager::reset();
    check(\OliviaDatabaseLibrary\ConnectionManager::count() === 0, 'DB-306 ConnectionManager::reset() esvazia o pool');
    $reaberto = $item->findForId(150);
    $conexaoAposReset = \OliviaDatabaseLibrary\ConnectionManager::get($cfg);
    check(count($reaberto) === 1 && ($reaberto[0]->nome ?? null) === 'pool' && \OliviaDatabaseLibrary\ConnectionManager::count() === 1 && $conexaoAposReset !== $conexaoAntesReset && $conexaoAposReset->connect() !== $pdoAntesReset, 'DB-306 pool reabre conexão nova após reset e lê a linha esperada');
    $pdo->exec('DELETE FROM items WHERE id = 150');

    // ---------- DB-310: metadados de cláusula, limit 0, deleteFull, cache e guarded ----------
    grupo('DB-310');
    $pdo->exec('DELETE FROM items');
    $pdo->exec("INSERT INTO items(id, nome, valor) VALUES (101, 'a', '1'), (102, 'b', '2'), (103, 'c', '3')");
    $qbx = fn () => new \OliviaDatabaseLibrary\QueryBuilder($cfg);
    $rejeita = function (callable $fn) {
        try {
            $fn();
        } catch (\InvalidArgumentException $e) {
            return true;
        } catch (\Throwable $e) {
            return false;
        }
        return false;
    };
    $metaAtaques = [
        'instruction UNION em order' => fn () => $qbx()->table('items')->fields('id')->order(['instruction' => 'UNION SELECT 1 --', 'id'])->select(),
        'instruction em group' => fn () => $qbx()->table('items')->fields('id')->group(['instruction' => 'GROUP BY 1; DROP TABLE items --', 'id'])->select(),
        'instruction em limit' => fn () => $qbx()->table('items')->fields('id')->limit(['instruction' => 'LIMIT 1 UNION SELECT 1', 1])->select(),
        'separator em order' => fn () => $qbx()->table('items')->fields('id')->order(['separator' => ' ; ', 'id', 'nome'])->select(),
        'instruction em where' => fn () => $qbx()->table('items')->fields('id')->where(['instruction' => 'UNION SELECT 1 WHERE', '1 = 1'])->select(),
        'separator livre em where' => fn () => $qbx()->table('items')->fields('id')->where(['separator' => ' UNION SELECT 1 -- ', 'id = 1', 'id = 2'])->select(),
    ];
    foreach ($metaAtaques as $caso => $ataque) {
        check($rejeita($ataque), "DB-310 rejeita metadado malicioso: {$caso}");
    }
    $ordenado = $qbx()->table('items')->fields('id')->order(['instruction' => 'order  by', 'separator' => ',', 'id DESC'])->select();
    check(array_column($ordenado, 'id') === [103, 102, 101], 'DB-310 metadados padrão continuam aceitos (instruction ORDER BY, separator ,)');
    $ou = $qbx()->table('items')->fields('id')->where(['separator' => 'OR', 'id = ?', 'id = ?'])->order('id')->select([101, 103]);
    check(array_column($ou, 'id') === [101, 103], 'DB-310 where com separator OR');

    check(count($qbx()->table('items')->fields('id')->limit(0)->select()) === 0, 'DB-310 limit(0) retorna zero linhas');
    check(count($qbx()->table('items')->fields('id')->limit('0')->select()) === 0, 'DB-310 limit("0") retorna zero linhas');
    check(count($qbx()->table('items')->fields('id')->limit([0, 0])->select()) === 0, 'DB-310 limit([0, 0]) retorna zero linhas');
    $segunda = $qbx()->table('items')->fields('id')->order('id')->limit('1, 1')->select();
    check(array_column($segunda, 'id') === [102], 'DB-310 limit "1, 1" devolve exatamente a segunda linha');

    // deleteFull
    $erroLimite = null;
    try {
        (new \OliviaTest\Models\Item())->deleteFull(null, null, ['id > ?'], [0], null, null, null, 1);
    } catch (\InvalidArgumentException $e) {
        $erroLimite = $e;
    }
    check($erroLimite !== null && $contar() === 3, 'DB-310 Model::deleteFull rejeita limit sem apagar nada');
    check($rejeita(fn () => (new \OliviaTest\Models\Item())->deleteFull(null, null, ['id > ?'], [0], null, 'nome')) && $contar() === 3, 'DB-310 Model::deleteFull rejeita group sem apagar nada');
    check($rejeita(fn () => (new \OliviaTest\Models\Item())->deleteFull(null, null, ['id > ?'], [0], null, null, 'count(*) > 0')) && $contar() === 3, 'DB-310 Model::deleteFull rejeita having sem apagar nada');
    check(seguro(fn () => $qbx()->table('items')->where('id = ?')->deleteFull([101])) === 1 && $contar() === 2, 'DB-310 QueryBuilder::deleteFull sem join funciona no SQLite');
    check(seguro(fn () => $qbx()->table('items it')->where('it.id = ?')->deleteFull([102])) === 1 && $contar() === 1, 'DB-310 QueryBuilder::deleteFull com alias funciona no SQLite');
    check($rejeita(fn () => $qbx()->table('items')->join('JOIN items b ON b.id = items.id')->where('items.id = ?')->deleteFull([103])) && $contar() === 1, 'DB-310 deleteFull com join é rejeitado fora do MySQL');
    check($rejeita(fn () => $qbx()->table('items')->where('id = ?')->limit(1)->deleteFull([103])), 'DB-310 QueryBuilder::deleteFull rejeita limit');
    check($rejeita(fn () => $qbx()->table('items')->join('JOIN x ON 1=1')->where('id = ?')->delete([103])), 'DB-310 delete() simples rejeita join');
    $comoMysqlSql = new class ($cfg) extends \OliviaDatabaseLibrary\Connection {
        public string $ultimoSql = '';
        public function driver(): string
        {
            return 'mysql';
        }
        public function executeDelete($sql, array $values): int
        {
            $this->ultimoSql = $sql;
            return 0;
        }
    };
    seguro(fn () => (new \OliviaDatabaseLibrary\QueryBuilder($comoMysqlSql))->table('items i')->join('JOIN outra o ON o.item_id = i.id')->where('o.flag = ?')->deleteFull([1]));
    check($comoMysqlSql->ultimoSql === 'DELETE i FROM items i JOIN outra o ON o.item_id = i.id WHERE o.flag = ?', 'DB-310 deleteFull com join no MySQL usa o alias como alvo');
    $pdo->exec('DELETE FROM items');

    // Cache: desligado por padrão, opt-in por opção/ambiente e particionado pela conexão
    $pdo->exec("INSERT INTO items(id, nome, valor) VALUES (201, 'original', '1')");
    $semCache = new \OliviaDatabaseLibrary\QueryBuilder($cfg);
    check($semCache->connection()->queryCacheEnabled() === false, 'DB-310 cache de consultas desligado por padrão');
    $semCache->table('items')->fields(['nome'])->where('id = ?')->select([201]);
    $pdo->exec("UPDATE items SET nome = 'externo' WHERE id = 201");
    $lido = (new \OliviaDatabaseLibrary\QueryBuilder($cfg))->table('items')->fields(['nome'])->where('id = ?')->select([201]);
    check(($lido[0]->nome ?? null) === 'externo', 'DB-310 sem cache, escrita de outro processo é vista imediatamente');

    $cfgCache = array_merge($cfg, ['query_cache' => 'session']);
    $comCache = fn () => new \OliviaDatabaseLibrary\QueryBuilder($cfgCache);
    $comCache()->table('items')->fields(['nome'])->where('id = ?')->select([201]);
    $pdo->exec("UPDATE items SET nome = 'externo2' WHERE id = 201");
    $doCache = $comCache()->table('items')->fields(['nome'])->where('id = ?')->select([201]);
    check(($doCache[0]->nome ?? null) === 'externo', 'DB-310 com query_cache=session a leitura vem do cache (limitação documentada)');
    $comCache()->table('items')->fields(['nome'])->where('id = ?')->update(['via-builder'], [201]);
    $aposEscrita = $comCache()->table('items')->fields(['nome'])->where('id = ?')->select([201]);
    check(($aposEscrita[0]->nome ?? null) === 'via-builder', 'DB-310 escrita pelo builder invalida o cache da tabela');
    // delete() e deleteFull() também invalidam o cache da tabela.
    $pdo->exec("INSERT INTO items(id, nome, valor) VALUES (211, 'd1', '1'), (212, 'd2', '2')");
    $lerApagado = fn ($id) => $comCache()->table('items')->fields(['nome'])->where('id = ?')->select([$id]);
    $lerApagado(211);
    seguro(fn () => $comCache()->table('items')->where('id = ?')->delete([211]));
    check(count((array) $lerApagado(211)) === 0, 'DB-310 delete() invalida o cache da tabela');
    $lerApagado(212); // aquecido só agora: o delete() acima limpa o cache inteiro da conexão
    seguro(fn () => $comCache()->table('items')->where('id = ?')->deleteFull([212]));
    check(count((array) $lerApagado(212)) === 0, 'DB-310 deleteFull() invalida o cache da tabela');

    $outroBanco = $tmp . '/tenant2.sqlite';
    $pdo2 = new PDO('sqlite:' . $outroBanco);
    $pdo2->exec('CREATE TABLE items (id INTEGER PRIMARY KEY, nome TEXT, valor TEXT)');
    $pdo2->exec("INSERT INTO items(id, nome, valor) VALUES (201, 'tenant2', '9')");
    $tenant2 = (new \OliviaDatabaseLibrary\QueryBuilder(array_merge($cfgCache, ['database' => $outroBanco])))->table('items')->fields(['nome'])->where('id = ?')->select([201]);
    check(($tenant2[0]->nome ?? null) === 'tenant2', 'DB-310 cache particionado: mesmo SQL em outro banco não recebe resultado do primeiro');
    $chaves = array_values(array_filter(array_keys($_SESSION), fn ($k) => strpos((string) $k, 'olivia_qc:') === 0));
    check(count($chaves) === 2 && count(array_unique(array_map(fn ($k) => explode(':', $k)[1], $chaves))) === 2, 'DB-310 chaves de cache incluem a identidade da conexão');
    putenv('OLIVIA_QUERY_CACHE=session');
    check((new \OliviaDatabaseLibrary\Connection($cfg))->queryCacheEnabled() === true, 'DB-310 OLIVIA_QUERY_CACHE=session liga o cache');
    putenv('OLIVIA_QUERY_CACHE');
    check((new \OliviaDatabaseLibrary\Connection(array_merge($cfg, ['password' => 'outra'])))->identity() === (new \OliviaDatabaseLibrary\Connection($cfg))->identity(), 'DB-310 identidade da conexão não depende da senha');
    $pdo->exec('DELETE FROM items');

    // ---------- DB-310 (retrabalho após validação independente) ----------
    grupo('DB-310');
    $pdo->exec('DELETE FROM items');
    $pdo->exec("INSERT INTO items(id, nome, valor) VALUES (301, 'a', '1'), (302, 'b', '2'), (303, 'c', '3')");
    $capturaMysql = fn () => new class ($cfg) extends \OliviaDatabaseLibrary\Connection {
        public array $sqls = [];
        public function driver(): string
        {
            return 'mysql';
        }
        public function executeDelete($sql, array $values): int
        {
            $this->sqls[] = $sql;
            return 0;
        }
        public function executeUpdate($sql, array $values): int
        {
            $this->sqls[] = $sql;
            return 0;
        }
    };

    // Achado 1: where vazio nunca vira DELETE/UPDATE sem WHERE
    $cap = $capturaMysql();
    foreach ([[], '', null] as $vazio) {
        $rotulo = json_encode($vazio);
        check($rejeita(fn () => (new \OliviaDatabaseLibrary\QueryBuilder($cap))->table('t a')->join('JOIN u ON u.id = a.uid')->where($vazio)->deleteFull([])), "DB-310 deleteFull com join e where {$rotulo} é rejeitado");
        check($rejeita(fn () => $qbx()->table('items')->where($vazio)->delete([])) && $contar() === 3, "DB-310 delete com where {$rotulo} é rejeitado sem apagar");
        check($rejeita(fn () => $qbx()->table('items')->fields(['nome'])->where($vazio)->update(['x'])), "DB-310 update com where {$rotulo} é rejeitado");
    }
    check($cap->sqls === [], 'DB-310 nenhum SQL de escrita sem WHERE chegou à conexão');
    check($rejeita(fn () => (new \OliviaTest\Models\Item())->deleteFull(null, null, [], [], 'JOIN u ON u.id = items.uid')) && $contar() === 3, 'DB-310 Model::deleteFull com join e where vazio é rejeitado');
    check($rejeita(fn () => (new \OliviaTest\Models\Item())->deleteFull(null, null, '', [])) && $contar() === 3, 'DB-310 Model::deleteFull com where "" é rejeitado');
    check($qbx()->table('items')->where('1 = 1')->limit(null)->delete([]) === 3 && $contar() === 0, 'DB-310 apagar tudo exige where explícito (1 = 1)');
    $pdo->exec("INSERT INTO items(id, nome, valor) VALUES (301, 'a', '1'), (302, 'b', '2'), (303, 'c', '3')");

    // Achado 8: chave primária qualificada com join
    $modelCaptura = new class extends \OliviaDatabaseModel\Model {
        protected $table = 'items';
        protected $drive = 'sqlite';
    };
    $capPk = $capturaMysql();
    seguro(fn () => (new \OliviaDatabaseLibrary\QueryBuilder($capPk))->table('items')->join('JOIN outra o ON o.item_id = items.id')->where(['items.id = ?'])->deleteFull([1]));
    check(end($capPk->sqls) === 'DELETE items FROM items JOIN outra o ON o.item_id = items.id WHERE items.id = ?', 'DB-310 deleteFull com join monta alvo e where qualificados');
    $fonteModel = (string) file_get_contents($root . '/src/database-model/Model.php');
    check(strpos($fonteModel, "\$pkWhere = (\$temJoin ? end(\$partesTabela) . '.' : '') . \$primaryKey . ' = ?'") !== false, 'DB-310 Model::deleteFull qualifica a PK pelo alias da tabela quando há join');

    // Achado 4: metadados e várias condições em update/delete
    check($rejeita(fn () => $qbx()->table('items')->where(['instruction' => 'UNION', 'id = ?'])->delete([301])) && $contar() === 3, 'DB-310 delete rejeita instruction maliciosa no where');
    check($rejeita(fn () => $qbx()->table('items')->fields(['nome'])->where(['separator' => '; DROP TABLE items; --', 'id = ?', 'id = ?'])->update(['x'], [301, 302])), 'DB-310 update rejeita separator malicioso no where');
    check(seguro(fn () => $qbx()->table('items')->where(['id > ?', 'id < ?'])->delete([301, 303])) === 1 && $contar() === 2, 'DB-310 delete com várias condições usa AND');
    check(seguro(fn () => $qbx()->table('items')->fields(['nome'])->where(['separator' => 'OR', 'id = ?', 'id = ?'])->update(['z'], [301, 303])) === 2, 'DB-310 update com separator OR');

    // Achado 5: delete() normaliza alias
    check(seguro(fn () => $qbx()->table('items it')->where('it.id = ?')->delete([301])) === 1 && $contar() === 1, 'DB-310 delete() com alias sem AS funciona no SQLite');
    // Tabela Raw é SQL do desenvolvedor: não recebe a normalização de alias.
    $capRaw = $capturaMysql();
    seguro(fn () => (new \OliviaDatabaseLibrary\QueryBuilder($capRaw))->table(\OliviaDatabaseLibrary\Raw::sql('pt PARTITION (p1)'))->where('id = ?')->delete([1]));
    seguro(fn () => (new \OliviaDatabaseLibrary\QueryBuilder($capRaw))->table(\OliviaDatabaseLibrary\Raw::sql('pt PARTITION (p1)'))->where('id = ?')->deleteFull([], [1]));
    check($capRaw->sqls === ['DELETE FROM pt PARTITION (p1) WHERE id = ?', 'DELETE FROM pt PARTITION (p1) WHERE id = ?'], 'DB-310 delete()/deleteFull() preservam tabela Raw intacta');
    // Partes do where em escritas: só string/Raw e nunca vazias; deleteFull recusa order.
    $capWhere = $capturaMysql();
    $qbw = fn () => new \OliviaDatabaseLibrary\QueryBuilder($capWhere);
    check($rejeita(fn () => $qbw()->table('items')->where([['id = ?']])->delete([1])), 'DB-310 delete rejeita parte de where que não é string');
    check($rejeita(fn () => $qbw()->table('items')->fields(['nome'])->where([['id = ?']])->update(['x'], [1])), 'DB-310 update rejeita parte de where que não é string');
    check($rejeita(fn () => $qbw()->table('items')->where(['', null])->delete([])), 'DB-310 delete rejeita where só com partes vazias');
    check($rejeita(fn () => $qbw()->table('items')->fields(['nome'])->where(['', null])->update(['x'])), 'DB-310 update rejeita where só com partes vazias');
    check($rejeita(fn () => $qbw()->table('items')->where(['', null])->deleteFull([])), 'DB-310 deleteFull rejeita where só com partes vazias');
    check($rejeita(fn () => $qbw()->table('items')->where('id = ?')->order('id')->deleteFull([1])), 'DB-310 deleteFull rejeita order');
    check($capWhere->sqls === [], 'DB-310 nenhuma escrita com where inválido ou order chegou à conexão');
    check($rejeita(fn () => (new \OliviaDatabaseLibrary\QueryBuilder($capRaw))->table(\OliviaDatabaseLibrary\Raw::sql('pt p'))->join('JOIN x ON x.id = p.x')->where('p.id = ?')->deleteFull([], [1])) && count($capRaw->sqls) === 2, 'DB-310 deleteFull com join e tabela Raw é rejeitado (alvo indefinido)');

    // Achado 7: arrays aninhados e tipos inválidos
    check($rejeita(fn () => $qbx()->table('items')->fields('id')->order([['instruction' => 'UNION'], 'id'])->select()), 'DB-310 array aninhado em order é rejeitado');
    check($rejeita(fn () => $qbx()->table('items')->fields('id')->group([['x'], 'id'])->select()), 'DB-310 array aninhado em group é rejeitado');
    check($rejeita(fn () => $qbx()->table('items')->fields('id')->join([['JOIN x']])->select()), 'DB-310 array aninhado em join é rejeitado');
    check($rejeita(fn () => $qbx()->table('items')->fields('id')->pre(['WITH x AS (SELECT 1)', ['y']])->select()), 'DB-310 array aninhado em pre é rejeitado');
    $pdo->exec('DELETE FROM items');

    // Achado 2: PDO morto é descartado também após escrita (e após falha no beginTransaction)
    $escritor = $comoMysql();
    $mortoEscrita = $novoGoneAway(2006);
    $injetar($escritor, $mortoEscrita);
    try {
        $escritor->executeInsert('INSERT INTO items(nome, valor) VALUES (?, ?)', ['w1', '1']);
    } catch (\Exception $e) {
    }
    try {
        $idNovo = $escritor->executeInsert('INSERT INTO items(nome, valor) VALUES (?, ?)', ['w2', '2']);
    } catch (\Exception $e) {
        $idNovo = null;
    }
    check($escritor->connect() !== $mortoEscrita && is_string($idNovo) && $contar() === 1, 'DB-310 escrita seguinte à perda de conexão reconecta (PDO morto descartado)');
    $mortoBegin = new class ('sqlite::memory:') extends \PDO {
        public function beginTransaction(): bool
        {
            $e = new \PDOException('SQLSTATE[HY000]: General error: 2006 MySQL server has gone away');
            $e->errorInfo = ['HY000', 2006, 'MySQL server has gone away'];
            throw $e;
        }
    };
    $txBegin = $comoMysql();
    $injetar($txBegin, $mortoBegin);
    check(throws(fn () => $txBegin->transaction(fn () => 1), \PDOException::class, '2006'), 'DB-310 beginTransaction com conexão morta lança PDOException');
    check(seguro(fn () => $txBegin->transaction(fn () => 'ok')) === 'ok' && seguro(fn () => $txBegin->connect()) !== $mortoBegin, 'DB-310 transação seguinte reconecta após falha no beginTransaction');
    $pdo->exec('DELETE FROM items');

    // Achado 3: escrita invalida todo o cache da conexão (join e schema)
    $pdo->exec("INSERT INTO items(id, nome, valor) VALUES (401, 'antes', '1')");
    $pdo->exec('CREATE TABLE IF NOT EXISTS outra (item_id INTEGER, flag INTEGER)');
    $pdo->exec('DELETE FROM outra');
    $pdo->exec('INSERT INTO outra VALUES (401, 1)');
    $leituraJoin = fn () => $comCache()->table('items i')->fields(['o.flag'])->join('JOIN outra o ON o.item_id = i.id')->where('i.id = ?')->select([401]);
    $leituraJoin();
    $comCache()->table('outra')->fields(['flag'])->where('item_id = ?')->update([0], [401]);
    check(($leituraJoin()[0]->flag ?? null) === 0, 'DB-310 escrita na tabela do join invalida leitura com join');
    $leituraSimples = fn () => $comCache()->table('items')->fields(['nome'])->where('id = ?')->select([401]);
    $leituraSimples();
    $comCache()->table('main.items')->fields(['nome'])->where('id = ?')->update(['depois'], [401]);
    check(($leituraSimples()[0]->nome ?? null) === 'depois', 'DB-310 escrita em schema.tabela invalida leitura da tabela');
    $pdo->exec('DELETE FROM items');
    $pdo->exec('DELETE FROM outra');

    // Mutação M13: cache nunca é usado dentro de transação
    $pdo->exec("INSERT INTO items(id, nome, valor) VALUES (501, 'fora', '1')");
    $comCache()->table('items')->fields(['nome'])->where('id = ?')->select([501]); // aquece o cache
    $vistoNaTx = \OliviaDatabaseLibrary\ConnectionManager::get($cfgCache)->transaction(function () use ($pdo, $comCache) {
        $pdo->exec("UPDATE items SET nome = 'dentro' WHERE id = 501"); // escrita fora do builder
        return $comCache()->table('items')->fields(['nome'])->where('id = ?')->select([501])[0]->nome ?? null;
    });
    check($vistoNaTx === 'dentro', 'DB-310 leitura dentro de transação ignora o cache');
    $pdo->exec('DELETE FROM items');

    // Mutação M9: escrita com cache desligado não inicia sessão (processo separado, sem sessão)
    $probeSessao = $tmp . '/sessao-probe.php';
    file_put_contents($probeSessao, '<?php
        require ' . var_export($root . '/vendor/autoload.php', true) . ';
        $cfg = ' . var_export($cfg, true) . ';
        $qb = new \OliviaDatabaseLibrary\QueryBuilder($cfg);
        $qb->table("items")->where("id = ?")->delete([999999]);
        (new \OliviaDatabaseLibrary\QueryBuilder($cfg))->table("items")->fields("id")->where("id = ?")->select([1]);
        echo session_status() === PHP_SESSION_NONE ? "sem-sessao" : "sessao-iniciada";
    ');
    [$saidaSessao, $errosSessao] = executarPhp(['-d', 'display_errors=stderr', $probeSessao]);
    check(trim($saidaSessao) === 'sem-sessao' && $errosSessao === '', 'DB-310 escrita e leitura com cache desligado não iniciam sessão PHP (sem warnings)');

    // Reauditoria: falha de begin/commit/rollBack com errorMode silencioso nunca vira sucesso.
    $silencioso = new \OliviaDatabaseLibrary\Connection(array_merge($cfg, ['database' => $tmp . '/silent.sqlite', 'errorMode' => \PDO::ERRMODE_SILENT]));
    $pdoSil = $silencioso->connect();
    $pdoSil->exec('PRAGMA foreign_keys = ON');
    $pdoSil->exec('CREATE TABLE IF NOT EXISTS pai (id INTEGER PRIMARY KEY)');
    $pdoSil->exec('CREATE TABLE IF NOT EXISTS filho (id INTEGER PRIMARY KEY, pai_id INTEGER REFERENCES pai(id) DEFERRABLE INITIALLY DEFERRED)');
    $erroCommit = null;
    $retornoCommit = null;
    try {
        $retornoCommit = $silencioso->transaction(function ($c) {
            $c->connect()->exec('INSERT INTO filho (id, pai_id) VALUES (1, 999)'); // FK diferida: falha só no commit
            return 'callback completou';
        });
    } catch (\RuntimeException $e) {
        $erroCommit = $e;
    }
    check($erroCommit !== null && $retornoCommit === null && strpos($erroCommit->getMessage(), 'commit') !== false, 'DB-310 falha de commit com ERRMODE_SILENT lança em vez de devolver o valor do callback');
    check($silencioso->inTransaction() === false && (int) $silencioso->connect()->query('SELECT COUNT(*) FROM filho')->fetchColumn() === 0, 'DB-310 falha de commit desfaz a transação (nada gravado, nenhuma transação aberta)');
    check(seguro(fn () => $silencioso->transaction(fn ($c) => $c->executeInsert('INSERT INTO pai (id) VALUES (?)', [7])) !== null) === true, 'DB-310 transação seguinte à falha de commit funciona');

    $pdoBeginFalse = new class ('sqlite::memory:') extends \PDO {
        public function beginTransaction(): bool
        {
            return false;
        }
    };
    $txBeginFalse = $comoMysql();
    $injetar($txBeginFalse, $pdoBeginFalse);
    $callbackRodou = false;
    $erroBegin = null;
    try {
        $txBeginFalse->transaction(function () use (&$callbackRodou) {
            $callbackRodou = true;
        });
    } catch (\RuntimeException $e) {
        $erroBegin = $e;
    }
    check($erroBegin !== null && $callbackRodou === false, 'DB-310 beginTransaction() === false lança antes de executar o callback');

    $pdoSemDesfazer = new class ('sqlite::memory:') extends \PDO {
        private bool $aberta = false;
        public function beginTransaction(): bool
        {
            $this->aberta = true;
            return true;
        }
        public function inTransaction(): bool
        {
            return $this->aberta;
        }
        public function commit(): bool
        {
            return false;
        }
        public function rollBack(): bool
        {
            return false;
        }
    };
    $txSemDesfazer = $comoMysql();
    $injetar($txSemDesfazer, $pdoSemDesfazer);
    $logAnteriorSil = ini_set('error_log', $tmp . '/rollback-silent.log');
    $erroSemDesfazer = null;
    try {
        $txSemDesfazer->transaction(fn () => 'ok');
    } catch (\RuntimeException $e) {
        $erroSemDesfazer = $e;
    }
    ini_set('error_log', (string) $logAnteriorSil);
    check($erroSemDesfazer !== null && seguro(fn () => $txSemDesfazer->connect()) !== $pdoSemDesfazer, 'DB-310 commit e rollBack false: lança e descarta o PDO que ficou em transação');

    // Reauditoria: update()/delete() recusam opções de leitura em vez de descartá-las.
    $capOpcoes = $capturaMysql();
    $qbo = fn () => new \OliviaDatabaseLibrary\QueryBuilder($capOpcoes);
    foreach (['limit' => fn ($q) => $q->limit(1), 'order' => fn ($q) => $q->order('id'), 'group' => fn ($q) => $q->group('id'), 'having' => fn ($q) => $q->having('COUNT(*) > 0')] as $opcao => $aplicar) {
        check($rejeita(fn () => $aplicar($qbo()->table('items')->where('id > ?'))->delete([0])), "DB-310 delete() rejeita {$opcao}");
        check($rejeita(fn () => $aplicar($qbo()->table('items')->fields(['nome'])->where('id > ?'))->update(['x'], [0])), "DB-310 update() rejeita {$opcao}");
    }
    check($capOpcoes->sqls === [], 'DB-310 nenhuma escrita com limit/order/group/having chegou à conexão');
    seguro(fn () => $qbo()->table('items')->where('id = ?')->limit(null)->delete([1]));
    seguro(fn () => $qbo()->table('items')->fields(['nome'])->where('id = ?')->order('')->update(['x'], [1]));
    check($capOpcoes->sqls === ['DELETE FROM items WHERE id = ?', 'UPDATE items SET nome = ? WHERE id = ?'], 'DB-310 limit(null) e order vazio continuam aceitos nas escritas');

    // Reauditoria: SQLite em memória tem identidade por instância; arquivo tem identidade estável.
    $mem1 = new \OliviaDatabaseLibrary\Connection(array_merge($cfg, ['database' => ':memory:']));
    $mem2 = new \OliviaDatabaseLibrary\Connection(array_merge($cfg, ['database' => ':memory:']));
    check($mem1->identity() !== $mem2->identity() && $mem1->identity() === $mem1->identity(), 'DB-310 conexões :memory: distintas não compartilham identidade de cache');
    check((new \OliviaDatabaseLibrary\Connection($cfg))->identity() === (new \OliviaDatabaseLibrary\Connection($cfg))->identity(), 'DB-310 mesmo arquivo SQLite mantém a mesma identidade entre instâncias');

    // Reauditoria: parâmetros binários (UTF-8 inválido) não colidem na chave do cache.
    $pdo->exec('DELETE FROM items');
    $insBin = $pdo->prepare('INSERT INTO items(id, nome, valor) VALUES (?, ?, ?)');
    $insBin->execute([601, "\xFF", 'ff']);
    $insBin->execute([602, "\xFE", 'fe']);
    $porNome = fn ($nome) => $comCache()->table('items')->fields(['valor'])->where('nome = ?')->select([$nome]);
    $ff = seguro(fn () => $porNome("\xFF"), []);
    $fe = seguro(fn () => $porNome("\xFE"), []);
    check(($ff[0]->valor ?? null) === 'ff' && ($fe[0]->valor ?? null) === 'fe', 'DB-310 parâmetros binários distintos têm chaves de cache distintas');
    $pdo->exec('DELETE FROM items');

    // Review codex (302): parâmetro não escalar não passa pela chave do cache.
    $pdo->exec("INSERT INTO items(id, nome, valor) VALUES (701, 'stringable', '1')");
    $stringable = new class {
        public function __toString(): string
        {
            return 'stringable';
        }
    };
    $semCacheStr = seguro(fn () => (new \OliviaDatabaseLibrary\QueryBuilder(array_merge($cfg, ['query_cache' => false])))->table('items')->fields(['id'])->where('nome = ?')->select([$stringable]), 'erro');
    $comCacheStr = seguro(fn () => $comCache()->table('items')->fields(['id'])->where('nome = ?')->select([$stringable]), 'erro');
    check(is_array($semCacheStr) && (int) ($semCacheStr[0]->id ?? 0) === 701, 'DB-310 parâmetro Stringable funciona com o cache desligado');
    check(is_array($comCacheStr) && (int) ($comCacheStr[0]->id ?? 0) === 701, 'DB-310 parâmetro Stringable com cache ligado vai direto ao banco');
    $pdo->exec('DELETE FROM items');

    // Review codex (302): SQLite em memória nunca usa o cache de sessão.
    foreach ([':memory:', 'file::memory:?cache=shared', 'file:privado?mode=memory'] as $efemero) {
        check((new \OliviaDatabaseLibrary\Connection(array_merge($cfg, ['database' => $efemero, 'query_cache' => 'session'])))->queryCacheEnabled() === false, "DB-310 cache desligado para SQLite efêmero {$efemero}");
    }
    check((new \OliviaDatabaseLibrary\Connection(array_merge($cfg, ['query_cache' => 'session'])))->queryCacheEnabled() === true, 'DB-310 cache continua disponível para SQLite em arquivo');
    // Review codex (305): a classificação segue o destino que o SQLite abriu, não o texto do DSN.
    foreach (['file:%3Amemory%3A', 'file::memory%3A?cache=shared', 'file:privado?mode=%6Demory', 'file:'] as $efemeroUri) {
        check((new \OliviaDatabaseLibrary\Connection(array_merge($cfg, ['database' => $efemeroUri, 'query_cache' => 'session'])))->queryCacheEnabled() === false, "DB-310 cache desligado para URI SQLite efêmera {$efemeroUri}");
    }
    check((new \OliviaDatabaseLibrary\Connection(array_merge($cfg, ['database' => 'file:' . $tmp . '/uri.sqlite', 'query_cache' => 'session'])))->queryCacheEnabled() === true, 'DB-310 cache continua disponível para URI SQLite de arquivo');
    $connCls = new \OliviaDatabaseLibrary\Connection(array_merge($cfg, ['query_cache' => 'session']));
    $pdoCls = $connCls->connect();
    $connCls->queryCacheEnabled();
    $refCls = \WeakReference::create($pdoCls);
    unset($pdoCls);
    $connCls->close();
    check($refCls->get() === null && $connCls->queryCacheEnabled() === true, 'DB-310 classificação do SQLite não mantém o PDO vivo após close() e é refeita na reconexão');
    $cfgMem = array_merge($cfg, ['database' => ':memory:', 'query_cache' => 'session']);
    $connMem = \OliviaDatabaseLibrary\ConnectionManager::get($cfgMem);
    $connMem->connect()->exec('CREATE TABLE m (id INTEGER)');
    $contaMem = fn () => (int) ((new \OliviaDatabaseLibrary\QueryBuilder($connMem))->table('m')->fields(['COUNT(*) AS n'])->select()[0]->n ?? -1);
    $antesMem = seguro($contaMem, -1);
    $connMem->connect()->exec('INSERT INTO m VALUES (1)');
    $depoisMem = seguro($contaMem, -1);
    $connMem->close();
    $connMem->connect()->exec('CREATE TABLE m (id INTEGER)'); // novo PDO = banco novo e vazio
    $aposReabrir = seguro($contaMem, -1);
    check($antesMem === 0 && $depoisMem === 1 && $aposReabrir === 0, 'DB-310 SQLite em memória lê sempre do banco, inclusive após close() e reconexão');

    // Achado 6: opção query_cache sem distinção de caixa e env via $_ENV
    check((new \OliviaDatabaseLibrary\Connection(array_merge($cfg, ['query_cache' => 'SESSION'])))->queryCacheEnabled() === true, 'DB-310 query_cache "SESSION" liga o cache');
    check((new \OliviaDatabaseLibrary\Connection(array_merge($cfg, ['query_cache' => 'off'])))->queryCacheEnabled() === false, 'DB-310 query_cache "off" desliga o cache');
    $_ENV['OLIVIA_QUERY_CACHE'] = 'session';
    check((new \OliviaDatabaseLibrary\Connection($cfg))->queryCacheEnabled() === true, 'DB-310 OLIVIA_QUERY_CACHE lido também de $_ENV');
    unset($_ENV['OLIVIA_QUERY_CACHE']);

    // Achado 8: identidade SQLite usa caminho real
    $relativo = new \OliviaDatabaseLibrary\Connection(array_merge($cfg, ['database' => dirname($cfg['database']) . '/./' . basename($cfg['database'])]));
    check($relativo->identity() === (new \OliviaDatabaseLibrary\Connection($cfg))->identity(), 'DB-310 identidade SQLite normaliza o caminho');

    // guarded ampliado
    $_REQUEST = ['nome' => 'x', 'is_superuser' => '1', 'superuser' => '1', 'permissoes' => 'all', 'permissions' => 'all', 'nivel' => '9', 'perfil' => 'admin', 'privilegios' => 'all'];
    $g = new class extends \OliviaDatabaseModel\Model {
        protected $fillable = ['nome', 'is_superuser', 'superuser', 'permissoes', 'permissions', 'nivel', 'perfil', 'privilegios'];
    };
    $g->request();
    $vazou = array_filter(['is_superuser', 'superuser', 'permissoes', 'permissions', 'nivel', 'perfil', 'privilegios'], fn ($c) => isset($g->$c));
    check(($g->nome ?? null) === 'x' && $vazou === [], 'DB-310 guarded padrão bloqueia is_superuser, superuser, permissoes, permissions, nivel, perfil e privilegios');
    $_REQUEST = [];

    // ---------- SEC-302: atribuição em massa e XSS no msgbox ----------
    grupo('SEC-302');
    $usuarioModel = fn () => new class extends \OliviaDatabaseModel\Model {
        protected $fillable = ['nome', 'email', 'tipo', 'status', 'password', 'id_unidade'];
    };
    $_REQUEST = ['nome' => 'Ana', 'email' => 'a@x', 'tipo' => 'admin', 'status' => 'ativo', 'password' => 'p', 'id' => '1', 'id_unidade' => '3'];
    $u = $usuarioModel();
    $u->request();
    check(($u->nome ?? null) === 'Ana' && ($u->email ?? null) === 'a@x' && ($u->id_unidade ?? null) === '3', 'SEC-302 request() preenche campos liberados');
    check(!isset($u->tipo) && !isset($u->status) && !isset($u->password) && !isset($u->id), 'SEC-302 request() ignora campos em $guarded');
    $u2 = $usuarioModel();
    $u2->request_cripto();
    check(($u2->nome ?? null) === 'Ana' && !isset($u2->tipo) && !isset($u2->password), 'SEC-302 request_cripto() respeita $guarded');
    $u3 = $usuarioModel();
    $u3->request(['nome', 'password']);
    check(($u3->password ?? null) === 'p' && !isset($u3->tipo), 'SEC-302 lista explícita do desenvolvedor é respeitada');
    $semGuard = new class extends \OliviaDatabaseModel\Model {
        protected $fillable = ['nome', 'tipo'];
        protected $guarded = [];
    };
    $semGuard->request();
    check(($semGuard->tipo ?? null) === 'admin', 'SEC-302 model pode sobrescrever $guarded');
    $_REQUEST = [];

    // msgbox foi removido na CLEAN-307 (JavaScript inline, bloqueado pela CSP); flash() o substitui
    check(!method_exists($cc, 'msgbox'), 'CLEAN-307 msgbox removido');

    // ---------- SEC-303: criptografia autenticada ----------
    grupo('SEC-303');
    $_SESSION['secret_key'] = 'k-test';
    $_SESSION['secret_iv'] = 'iv-test';
    $a = $cc->encryptIt('42');
    $b = $cc->encryptIt('42');
    check(is_string($a) && strpos($a, 'v2.') === 0 && preg_match('/^v2\.[A-Za-z0-9_-]+$/', $a) === 1, 'SEC-303 formato v2 URL-safe');
    check($a !== $b, 'SEC-303 mesma entrada gera textos cifrados diferentes');
    check($cc->decryptIt($a) === '42' && $cc->decryptIt($b) === '42', 'SEC-303 decifra v2');
    check($cc->decryptIt($cc->encryptIt(42)) === '42', 'SEC-303 aceita inteiro como entrada');

    $raw = base64_decode(strtr(substr($a, 3), '-_', '+/') . str_repeat('=', (4 - strlen(substr($a, 3)) % 4) % 4));
    $tamper = function (string $bin, int $pos) {
        $bin[$pos] = chr(ord($bin[$pos]) ^ 0x01);
        return 'v2.' . rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    };
    check($cc->decryptIt($tamper($raw, strlen($raw) - 1)) === false, 'SEC-303 ciphertext adulterado retorna false');
    check($cc->decryptIt($tamper($raw, 0)) === false, 'SEC-303 IV adulterado retorna false');
    check($cc->decryptIt($tamper($raw, 14)) === false, 'SEC-303 tag adulterada retorna false');
    check($cc->decryptIt('v2.' . rtrim(strtr(base64_encode(substr($raw, 0, 20)), '+/', '-_'), '=')) === false, 'SEC-303 payload truncado retorna false');
    check($cc->decryptIt('v2.@@@') === false, 'SEC-303 base64url inválido retorna false');

    // ---------- SEC-313: decrypt só v2, legado explícito, chave explícita válida, AAD por contexto ----------
    grupo('SEC-313');
    $_SESSION['secret_key'] = 'k-test';
    $_SESSION['secret_iv'] = 'iv-test';
    $C = \OliviaLib\Support\Crypto::class;
    putenv('OLIVIA_CRYPTO_LEGACY');
    $legacy = base64_encode(openssl_encrypt('77', 'AES-256-CBC', hash('sha256', 'k-test'), 0, substr(hash('sha256', 'iv-test'), 0, 16)));
    $legacyExplicit = base64_encode(openssl_encrypt('88', 'AES-256-CBC', hash('sha256', 'outra'), 0, substr(hash('sha256', 'iv2'), 0, 16)));
    check($C::decrypt($legacy) === false && $cc->decryptIt($legacy) === false && $C::decrypt($legacyExplicit, 'outra-chave-longa') === false, 'SEC-313 decrypt recusa CBC legado por padrão');
    check($C::decryptLegacy($legacy) === '77' && $C::decryptLegacy($legacyExplicit, 'outra', 'iv2') === '88', 'SEC-313 decryptLegacy lê CBC (sessão e chave/IV explícitos)');
    check($C::decryptLegacy($C::encrypt('v')) === false && $C::decryptLegacy($legacy, ['k-test'], 'iv-test') === false && $C::decryptLegacy($legacy, 'k-test', 123) === false, 'SEC-313 decryptLegacy só aceita CBC e chave/IV string');
    putenv('OLIVIA_CRYPTO_LEGACY=true');
    $chaveLegadaLonga = 'chave-legada-com-16+';
    $legacyLonga = base64_encode(openssl_encrypt('89', 'AES-256-CBC', hash('sha256', $chaveLegadaLonga), 0, substr(hash('sha256', 'iv3'), 0, 16)));
    check($C::legacyEnabled() && $C::decrypt($legacy) === '77' && $cc->decryptIt($legacyLonga, $chaveLegadaLonga, 'iv3') === '89', 'SEC-313 OLIVIA_CRYPTO_LEGACY=true lê CBC em decrypt/decryptIt');
    $legacy8 = base64_encode(openssl_encrypt('90', 'AES-256-CBC', hash('sha256', 'oito1234'), 0, substr(hash('sha256', 'iv-test'), 0, 16)));
    check(@$C::decrypt($legacy8, 'oito1234') === false && @$cc->decryptIt($legacy8, 'oito1234', 'iv-test') === false && @$cc->decryptIt($legacyExplicit, 'outra', 'iv2') === false, 'SEC-313 com legado ligado, chave explícita curta continua recusada em decrypt/decryptIt');
    check($C::decryptLegacy($legacy8, 'oito1234') === '90', 'SEC-313 chave curta antiga só pelo caminho explícito decryptLegacy');
    $ivSessao = $_SESSION['secret_iv'];
    unset($_SESSION['secret_iv']);
    check($cc->decryptIt([$legacyLonga], $chaveLegadaLonga, 'iv3') === '89' && $C::decryptLegacy([$legacyLonga], $chaveLegadaLonga, 'iv3') === '89', 'SEC-313 decryptIt com array usa o IV explícito (sem IV na sessão)');
    $_SESSION['secret_iv'] = $ivSessao;
    check($C::decrypt($legacy, null, 'ctx') === false && $C::decrypt($C::encrypt('w')) === 'w', 'SEC-313 com legado ligado, contexto não cai no CBC e v2 continua normal');
    putenv('OLIVIA_CRYPTO_LEGACY=talvez');
    check(!@$C::legacyEnabled() && @$C::decrypt($legacy) === false, 'SEC-313 OLIVIA_CRYPTO_LEGACY inválido mantém a leitura legada desligada');
    putenv('OLIVIA_CRYPTO_LEGACY=false');
    check(!$C::legacyEnabled() && $C::decrypt($legacy) === false, 'SEC-313 OLIVIA_CRYPTO_LEGACY=false desliga a leitura legada');
    putenv('OLIVIA_CRYPTO_LEGACY');

    // Chave explícita inválida falha fechado (sem cair em OLIVIA_APP_KEY/sessão)
    $chave16 = str_repeat('k', 16);
    $chave15 = str_repeat('k', 15);
    $comChave16 = $C::encrypt('s', $chave16);
    check(is_string($comChave16) && $C::decrypt($comChave16, $chave16) === 's', 'SEC-313 chave explícita de 16 caracteres é aceita');
    $invalidas = ['15 caracteres' => $chave15, '1 caractere' => 'k', 'vazia' => '', 'inteiro' => 1234567890123456789, 'array' => [$chave16], 'objeto' => new class { public function __toString(): string { return 'kkkkkkkkkkkkkkkkkkkk'; } }, 'false' => false];
    $sessaoValida = $C::encrypt('s');
    foreach ($invalidas as $nome => $chave) {
        check(@$C::encrypt('s', $chave) === false && @$C::decrypt($sessaoValida, $chave) === false, "SEC-313 chave explícita inválida ({$nome}) falha fechado");
    }
    // Comprimento em caracteres (UTF-8) e em bytes (chave binária)
    $mb8 = str_repeat('ç', 8);   // 8 caracteres, 16 bytes
    $mb16 = str_repeat('ç', 16);
    $bin16 = "\xFF" . random_bytes(15);
    $bin15 = "\xFF" . random_bytes(14);
    check(strlen($mb8) === 16 && @$C::encrypt('s', $mb8) === false, 'SEC-313 chave UTF-8 de 8 caracteres (16 bytes) é recusada');
    check($C::decrypt($C::encrypt('s', $mb16), $mb16) === 's', 'SEC-313 chave UTF-8 de 16 caracteres é aceita');
    check($C::decrypt($C::encrypt('s', $bin16), $bin16) === 's' && @$C::encrypt('s', $bin15) === false, 'SEC-313 chave binária é medida em bytes (16 aceita, 15 recusada)');
    putenv('OLIVIA_APP_KEY=base64:' . base64_encode(str_repeat('Z', 32)));
    check(@$C::encrypt('s', 'k') === false && @$cc->encryptIt('s', $chave15) === false, 'SEC-313 chave explícita curta não cai em OLIVIA_APP_KEY');
    putenv('OLIVIA_APP_KEY');

    // Contexto no AAD
    $ctxA = $C::encrypt('42', null, 'usuario.id');
    check($C::decrypt($ctxA, null, 'usuario.id') === '42', 'SEC-313 valor com contexto decifra no mesmo contexto');
    check($C::decrypt($ctxA, null, 'pedido.id') === false && $C::decrypt($ctxA) === false && $C::decrypt($ctxA, null, 'usuario.ID') === false, 'SEC-313 valor de um contexto não decifra em outro nem sem contexto');
    $semCtx = $C::encrypt('43');
    check($C::decrypt($semCtx) === '43' && $C::decrypt($semCtx, null, '') === '43' && $C::decrypt($semCtx, null, 'usuario.id') === false, 'SEC-313 valor sem contexto não decifra com contexto; contexto vazio equivale a nenhum');
    $iv2 = random_bytes(12);
    $tag2 = '';
    $c2 = openssl_encrypt('44', 'aes-256-gcm', hash('sha256', 'k-test', true), OPENSSL_RAW_DATA, $iv2, $tag2, 'olivia:v2', 16);
    check($C::decrypt('v2.' . rtrim(strtr(base64_encode($iv2 . $tag2 . $c2), '+/', '-_'), '=')) === '44', 'SEC-313 valores v2 emitidos antes do contexto (AAD olivia:v2) continuam válidos');
    check($C::encrypt('x', null, 123) === false && $C::decrypt($semCtx, null, ['a']) === false, 'SEC-313 contexto não-string falha fechado');
    check($C::decrypt($C::encrypt('x', $chave16, 'c'), $chave16, 'c') === 'x' && $C::decrypt($C::encrypt('x', $chave16, 'c'), null, 'c') === false, 'SEC-313 contexto combina com chave explícita');

    // Chave explícita do chamador
    $explicit = $cc->encryptIt('segredo', 'chave-do-chamador');
    check($cc->decryptIt($explicit, 'chave-do-chamador') === 'segredo' && $cc->decryptIt($explicit) === false, 'SEC-303 chave explícita isola o valor');

    // OLIVIA_APP_KEY sobrevive à troca de sessão
    putenv('OLIVIA_APP_KEY=base64:' . base64_encode(str_repeat('K', 32)));
    $comApp = $cc->encryptIt('99');
    $_SESSION['secret_key'] = 'outra-sessao';
    check($cc->decryptIt($comApp) === '99', 'SEC-303 OLIVIA_APP_KEY decifra após troca de sessão');
    putenv('OLIVIA_APP_KEY=curta');
    check(@$cc->encryptIt('x') === false, 'SEC-303 OLIVIA_APP_KEY curta falha fechado');
    putenv('OLIVIA_APP_KEY=base64:' . base64_encode('16-bytes-apenas!'));
    check(@$cc->encryptIt('x') === false, 'SEC-303 OLIVIA_APP_KEY base64 com tamanho errado falha fechado');
    putenv('OLIVIA_APP_KEY');
    unset($_SESSION['secret_key']);
    check($cc->encryptIt('x') === false, 'SEC-303 sem chave alguma falha fechado');
    $_SESSION['secret_key'] = 'k-test';

    // ---------- Router ----------
    grupo('Router');
    $config = new \OliviaRouter\RouterConfig('OliviaTest', 'Controller', 'Middleware', '', true, 'tok-123');
    $router = new \OliviaRouter\Router($config);
    $router->get('/item/{id}', 'ProbeController#show');
    $router->post('/item', 'ProbeController#save');

    $router->execute(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/item/7?x=1', 'POST' => [], 'GET' => []]);
    check(\OliviaTest\Controller\ProbeController::$calls === [['show', '7']], 'rota com {id} despacha e extrai parâmetro');

    $chamadasAntes = \OliviaTest\Controller\ProbeController::$calls;
    check(throws(fn () => $router->execute(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/item', 'POST' => ['_token' => 'errado']]), \RuntimeException::class, 'CSRF') && \OliviaTest\Controller\ProbeController::$calls === $chamadasAntes, 'POST com CSRF inválido lança RuntimeException e não chama o handler');
    $router->execute(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/item', 'POST' => ['_token' => 'tok-123']]);
    check(end(\OliviaTest\Controller\ProbeController::$calls) === ['save', 'POST'], 'POST com CSRF válido despacha');

    $router->execute(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/nao-existe']);
    check(($_SESSION['e404'] ?? null) === true, 'rota inexistente sinaliza 404');

    // Rotas do kernel apontam apenas para métodos existentes
    $source = '';
    foreach (token_get_all((string) file_get_contents($root . '/lib/Route.php')) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $source .= is_array($token) ? $token[1] : $token;
    }
    preg_match_all("/->(?:get|post|put|patch|delete)\\('[^']*',\\s*'([A-Za-z\\\\]+)#([A-Za-z0-9_]+)'\\)/", $source, $m, PREG_SET_ORDER);
    check($m !== [], 'lib/Route.php registra rotas');
    foreach ($m as [, $class, $method]) {
        check(method_exists('OliviaApp\\Controller\\' . $class, $method), "rota {$class}#{$method} aponta para método existente");
    }

    // Middlewares de CSRF
    check(class_exists('OliviaApp\\Middleware\\RequestPost') && method_exists('OliviaApp\\Middleware\\RequestPost', 'handle'), 'RequestPost existe e expõe handle()');
    check(!class_exists('OliviaApp\\Middleware\\RequestGet'), 'CLEAN-307 middleware RequestGet removido');
    $_SESSION['UUID'] = 'uuid-abc';
    $_POST = ['_token' => 'outro'];
    check(throws(fn () => @(new \OliviaApp\Middleware\RequestPost())->handle(), \OliviaRouter\MiddlewareHaltException::class, 'CSRF'), 'RequestPost interrompe com MiddlewareHaltException em token inválido');
    $_SESSION['UUID'] = '';
    $_POST = ['_token' => ''];
    check(throws(fn () => @(new \OliviaApp\Middleware\RequestPost())->handle(), \OliviaRouter\MiddlewareHaltException::class, 'CSRF'), 'RequestPost rejeita token vazio com MiddlewareHaltException');

    // ---------- CLEAN-307: superfícies removidas e helpers movidos ----------
    grupo('CLEAN-307');
    $removidos = ['msgbox', 'view', 'find_object', 'buscar_objeto_in_array', 'getArrays', 'setArrays', 'getAllParametros',
        'getParametros', 'setParametros', 'getDateTime', 'requestFile', 'requestArrayGet', 'requestArrayPost'];
    foreach ($removidos as $metodo) {
        check(!method_exists(\OliviaLib\CommandController::class, $metodo), "CLEAN-307 CommandController::{$metodo} removido");
    }
    check(!class_exists('OliviaApp\\Controller\\AuthController'), 'CLEAN-307 AuthController removido');
    check(!method_exists(\OliviaRouter\RouterConfig::class, 'fromRequest') && !method_exists(\OliviaRouter\RouterConfig::class, 'fromRequestData'), 'CLEAN-307 RouterConfig::fromRequest/fromRequestData removidos');
    check(!is_subclass_of(\OliviaLib\Route::class, \OliviaLib\CommandController::class), 'CLEAN-307 lib/Route não herda CommandController');

    $erroModel = null;
    try {
        (new \OliviaTest\Models\Item())->sav('x');
    } catch (\Error $e) {
        $erroModel = $e;
    }
    check($erroModel instanceof \Error && strpos($erroModel->getMessage(), 'sav') !== false, 'CLEAN-307 método inexistente em Model lança erro');
    check(throws(fn () => (new \OliviaRouter\Router($config))->midleware('X'), \BadMethodCallException::class, 'midleware'), 'CLEAN-307 Router rejeita método desconhecido com BadMethodCallException');
    $rMw = new \OliviaRouter\Router($config);
    $rMw->middleware('A', 'B')->get('/a', 'X#y');
    $rMw->middleware(null)->get('/b', 'X#y');
    $rotasMw = $rMw->getRoutes();
    check($rotasMw[0]->getMiddleware() === ['A', 'B'] && $rotasMw[1]->getMiddleware() === null, 'CLEAN-307 Router::middleware() mantém escopo e reset');

    // redirect() e flash()
    check($cc->route('usuarios', ['id' => 1]) === './usuarios?id=1' && $cc->route('/x') === './x', 'CLEAN-307 route() monta URL relativa com "/"');
    $cc->flash('Salvo com sucesso', 'sucesso');
    $cc->flash('<b>atenção</b>');
    $flash = $cc->pullFlash();
    check(count($flash) === 2 && $flash[0] === ['tipo' => 'sucesso', 'mensagem' => 'Salvo com sucesso'] && $flash[1]['tipo'] === 'info', 'CLEAN-307 flash() guarda mensagens com tipo');
    check($cc->pullFlash() === [], 'CLEAN-307 pullFlash() lê uma única vez');
    $_SESSION['UUID'] = 'a"b<c';
    check($cc->csrf_field() === '<input type="hidden" name="_token" value="a&quot;b&lt;c">', 'CLEAN-307 csrf_field() escapa o token');
    unset($_SESSION['UUID']);
    check($cc->csrf_token() === '', 'CLEAN-307 csrf_token() sem sessão não gera warning');

    // Formatos corrigidos e wrappers deprecated equivalentes
    $F = \OliviaLib\Support\Formatos::class;
    check($F::mesPorExtenso(1) === 'Janeiro' && $F::mesPorExtenso('12') === 'Dezembro' && $F::mesPorExtenso(13) === null && $F::mesPorExtenso(0) === null, 'CLEAN-307 mesPorExtenso 1..12 correto');
    check($F::mask('###.###.###-##', '123.456.789-09') === '123.456.789-09', 'CLEAN-307 mask completa');
    check($F::mask('##.##', '123456') === '12.34', 'CLEAN-307 mask ignora dígitos excedentes');
    check($F::mask('###.###.###-##', '1234') === '123.4', 'CLEAN-307 mask com dígitos faltando não deixa lixo');
    check($F::mask('(##) #####-####', '') === '', 'CLEAN-307 mask vazia');
    check($F::formatString('##/##', 'ab') === 'ab' && $F::formatString('**-**', 'abcd', '*') === 'ab-cd', 'CLEAN-307 formatString sem warning');
    check($F::formataData('2026-11-20', 1) === '20/11/2026 ' && $F::formataData('2026-11-20', null, 1) === '' && $F::formataData('2026-11-20 13:45:10', 1, 1) === '20/11/2026 13:45 ' && $F::formataData(null, 1) === null, 'CLEAN-307 formataData sem hora e com hora');
    check($F::validaCpf('529.982.247-25') && !$F::validaCpf('111.111.111-11') && !$F::validaCpf('123'), 'CLEAN-307 validaCpf');
    check($F::letraMaiuscula('ÉRICA DA SILVA ÂNGELO') === 'Érica da Silva Ângelo', 'CLEAN-307 letraMaiuscula multibyte');
    check($F::tirarAcentos('Ação Índio Über') === 'Acao Indio Uber', 'CLEAN-307 tirarAcentos');

    $avisos = [];
    set_error_handler(function ($no, $msg) use (&$avisos) { $avisos[] = $msg; return true; });
    $equivalentes = [
        [$cc->mes_do_ano_por_extenso(3), $F::mesPorExtenso(3)],
        [$cc->mask('##.##', '123456'), $F::mask('##.##', '123456')],
        [$cc->format_string('##-##', 'ab'), $F::formatString('##-##', 'ab')],
        [$cc->formataData('2026-11-20', 1, 1), $F::formataData('2026-11-20', 1, 1)],
        [$cc->validaCPF('529.982.247-25'), true],
        [$cc->letra_maiuscula('JOSÉ DOS SANTOS'), 'José dos Santos'],
        [$cc->tirarAcentos('ção'), 'cao'],
    ];
    restore_error_handler();
    $iguais = array_reduce($equivalentes, fn ($ok, $par) => $ok && $par[0] === $par[1], true);
    check($iguais && $avisos === [], 'CLEAN-307 wrappers deprecated do CommandController equivalem a Formatos, sem warnings');

    $_SESSION['secret_key'] = 'k-test';
    $viaWrapper = $cc->encryptIt('55');
    check(\OliviaLib\Support\Crypto::decrypt($viaWrapper) === '55' && $cc->decryptIt(\OliviaLib\Support\Crypto::encrypt('66')) === '66', 'CLEAN-307 encryptIt/decryptIt delegam para Support\Crypto');

    // ---------- SEC-304: upload endurecido ----------
    grupo('SEC-304');
    $uploadBase = $tmp . '/uploads';
    putenv('OLIVIA_UPLOAD_DIR=' . $uploadBase);
    $fakeUpload = function (string $nome, string $conteudo, int $erro = UPLOAD_ERR_OK) use ($tmp) {
        $tmpFile = $tmp . '/up-' . bin2hex(random_bytes(4));
        file_put_contents($tmpFile, $conteudo);
        return ['name' => $nome, 'type' => 'image/png', 'size' => strlen($conteudo), 'tmp_name' => $tmpFile, 'error' => $erro];
    };
    $UploadTeste = new class ('', 1, [], []) extends \OliviaUpload\Upload {
        protected function moverArquivo(string $origem, string $destino): bool
        {
            return rename($origem, $destino);
        }
    };
    $up = fn ($pasta, $mb, $ext, $file) => new ($UploadTeste::class)($pasta, $mb, $ext, $file, false);
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');

    $ok = $up('docs/2026', 1, ['png', 'pdf'], $fakeUpload('Foto Perfil.PNG', $png));
    check($ok->isValid() && $ok->getErro() === 0, 'SEC-304 PNG válido é aceito');
    check(preg_match('/^[0-9a-f-]{36}\.png$/', $ok->getArqNameCripto()) === 1 && $ok->getArqName() === 'Foto Perfil.PNG', 'SEC-304 arquivo sempre renomeado para UUID (renomear=false ignorado)');
    check(strpos($ok->getPath(), realpath($uploadBase) . '/docs/2026/') === 0 && is_file($ok->getPath()), 'SEC-304 destino resolve dentro do diretório base');
    check((fileperms($ok->getPath()) & 0777) === 0640 && (fileperms($uploadBase . '/docs') & 0777) === 0750, 'SEC-304 permissões 0640 (arquivo) e 0750 (pasta)');
    check($ok->getMime() === 'image/png', 'SEC-304 MIME real detectado');
    $_SERVER['REQUEST_URI'] = '/../../etc';
    $ok2 = $up('', 1, ['png'], $fakeUpload('a.png', $png));
    check($ok2->isValid() && dirname($ok2->getPath()) === realpath($uploadBase), 'SEC-304 destino não depende de REQUEST_URI');
    unset($_SERVER['REQUEST_URI']);
    $ok->delete_file();
    check(!is_file($ok->getPath()), 'SEC-304 delete_file remove o arquivo gravado');

    $rejeicoes = [
        '.php' => $up('', 1, ['png', 'php'], $fakeUpload('shell.php', '<?php system($_GET["c"]);')),
        '.phtml' => $up('', 1, ['phtml'], $fakeUpload('x.phtml', 'conteudo')),
        'dupla extensão .php.png' => $up('', 1, ['png'], $fakeUpload('x.php.png', $png)),
        'polyglot PNG + <?php' => $up('', 1, ['png'], $fakeUpload('x.png', $png . '<?php echo 1; ?>')),
        'MIME divergente (texto como .pdf)' => $up('', 1, ['pdf'], $fakeUpload('x.pdf', 'apenas texto')),
        'HTML como .txt' => $up('', 1, ['txt'], $fakeUpload('x.txt', '<!DOCTYPE html><html><script>alert(1)</script></html>')),
        'svg' => $up('', 1, ['svg'], $fakeUpload('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')),
        'extensão fora da allowlist' => $up('', 1, ['pdf'], $fakeUpload('x.png', $png)),
        'sem extensão' => $up('', 1, ['png'], $fakeUpload('arquivo', $png)),
        'pasta com ..' => $up('../fora', 1, ['png'], $fakeUpload('a.png', $png)),
        'pasta absoluta escapando' => $up('a/../../fora', 1, ['png'], $fakeUpload('a.png', $png)),
        'tamanho acima do limite' => $up('', 0.00001, ['png'], $fakeUpload('a.png', $png)),
        'erro de upload do PHP' => $up('', 1, ['png'], $fakeUpload('a.png', $png, UPLOAD_ERR_PARTIAL)),
        'arquivo vazio' => $up('', 1, ['txt'], $fakeUpload('a.txt', '')),
    ];
    foreach ($rejeicoes as $caso => $u) {
        check(!$u->isValid() && $u->getErro() === 1 && $u->getError() !== [] && $u->getPath() === '', "SEC-304 rejeita {$caso}");
    }
    $foraDoBase = glob($tmp . '/fora*') ?: [];
    $phpGravado = array_filter(glob($uploadBase . '/*') ?: [], fn ($f) => preg_match('/\.(php|phtml|svg)$/', $f));
    check($foraDoBase === [] && $phpGravado === [], 'SEC-304 nada gravado fora do base nem com extensão executável');

    $real = new \OliviaUpload\Upload('', 1, ['png'], $fakeUpload('a.png', $png));
    check(!$real->isValid(), 'SEC-304 arquivo que não veio de upload HTTP é recusado (is_uploaded_file)');
    // Com umask 000, mkdir/move herdariam 0777/0666: os modos finais vêm do chmod explícito
    $umaskSalvo = umask(0);
    $semUmask = $up('umask-zero/sub', 1, ['png'], $fakeUpload('u.png', $png));
    umask($umaskSalvo);
    check($semUmask->isValid() && (fileperms($semUmask->getPath()) & 0777) === 0640 && (fileperms($uploadBase . '/umask-zero') & 0777) === 0750 && (fileperms($uploadBase . '/umask-zero/sub') & 0777) === 0750, 'SEC-304 com umask 000 o arquivo fica 0640 e as pastas 0750 (nada world-writable)');
    // O mover real recusa arquivo local que não veio de upload HTTP (is_uploaded_file)
    $localForjado = $fakeUpload('forjado.png', $png);
    $moverReal = new \OliviaUpload\Upload('', 1, ['png'], $localForjado);
    check(!$moverReal->isValid() && in_array('Não foi possível gravar o arquivo enviado.', $moverReal->getError(), true) && is_file($localForjado['tmp_name']), 'SEC-304 mover real recusa arquivo local que não veio de upload HTTP');
    check(\OliviaLib\Email::uploadDirectory() === \OliviaUpload\Upload::baseDirectory(), 'SEC-304 Email lê anexos do mesmo diretório base');

    // ---------- SEC-312: contenção antes do mkdir, pacotes Office/ODF, MIME declarado, chmod, Apache ----------
    grupo('SEC-312');
    putenv('OLIVIA_UPLOAD_DIR=' . $uploadBase);
    // Contenção: link simbólico dentro da base apontando para fora não pode receber pastas nem arquivos.
    $externo = $tmp . '/externo-' . bin2hex(random_bytes(3));
    mkdir($externo, 0700);
    @symlink($externo, $uploadBase . '/atalho');
    $viaLink = $up('atalho/novo/sub', 1, ['png'], $fakeUpload('a.png', $png));
    check(is_link($uploadBase . '/atalho') && !$viaLink->isValid() && !is_dir($externo . '/novo') && (glob($externo . '/*') ?: []) === [], 'SEC-312 link simbólico para fora da base é recusado antes de criar pastas');
    $viaLinkDireto = $up('atalho', 1, ['png'], $fakeUpload('a.png', $png));
    check(!$viaLinkDireto->isValid() && (glob($externo . '/*') ?: []) === [], 'SEC-312 link simbólico como pasta final é recusado');
    @mkdir($uploadBase . '/interno-real', 0750);
    @symlink($uploadBase . '/interno-real', $uploadBase . '/interno-link');
    $linkInterno = $up('interno-link/x', 1, ['png'], $fakeUpload('a.png', $png));
    check($linkInterno->isValid() && strpos($linkInterno->getPath(), realpath($uploadBase . '/interno-real') . '/x/') === 0, 'SEC-312 link simbólico que permanece dentro da base continua aceito');
    $novaArvore = $up('n1/n2/n3', 1, ['png'], $fakeUpload('a.png', $png));
    check($novaArvore->isValid() && (fileperms($uploadBase . '/n1') & 0777) === 0750 && (fileperms($uploadBase . '/n1/n2/n3') & 0777) === 0750, 'SEC-312 pastas novas recebem 0750 explicitamente (independe do umask)');

    // Pacotes: ZIP mínimo (stored) montado sem depender da extensão zip
    $zipMinimo = function (array $arquivos): string {
        $local = '';
        $central = '';
        foreach ($arquivos as $nome => $dados) {
            $crc = crc32($dados);
            $offset = strlen($local);
            $cabecalho = pack('vvvvvVVVvv', 20, 0, 0, 0, 0, $crc, strlen($dados), strlen($dados), strlen($nome), 0);
            $local .= "PK\x03\x04" . $cabecalho . $nome . $dados;
            $central .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0, 0, 0, 0, $crc, strlen($dados), strlen($dados), strlen($nome), 0, 0, 0, 0, 0, $offset) . $nome;
        }
        return $local . $central . "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($arquivos), count($arquivos), strlen($central), strlen($local), 0);
    };
    $ct = '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"></Types>';
    $docx = $zipMinimo(['[Content_Types].xml' => $ct, '_rels/.rels' => '<Relationships/>', 'word/document.xml' => '<w:document/>']);
    $xlsx = $zipMinimo(['[Content_Types].xml' => $ct, 'xl/workbook.xml' => '<workbook/>']);
    $odt = $zipMinimo(['mimetype' => 'application/vnd.oasis.opendocument.text', 'content.xml' => '<office:document-content/>']);
    $zipGenerico = $zipMinimo(['leia.txt' => 'apenas um zip', 'dados.csv' => 'a,b']);
    $docxSemParte = $zipMinimo(['[Content_Types].xml' => $ct, 'outro/x.xml' => '<x/>']); // finfo: application/zip
    $docxSemCt = $zipMinimo(['word/document.xml' => '<w:document/>', '_rels/.rels' => '<r/>']); // finfo: MIME de docx
    $odtErrado = $zipMinimo(['content.xml' => '<x/>', 'mimetype' => 'application/vnd.oasis.opendocument.spreadsheet']); // finfo: application/zip
    $odtLongo = $zipMinimo(['mimetype' => 'application/vnd.oasis.opendocument.text' . str_repeat('A', 65536), 'content.xml' => '<x/>']); // finfo: MIME de odt
    $temZip = class_exists(\ZipArchive::class);
    $docxOk = $up('', 1, ['docx'], $fakeUpload('a.docx', $docx));
    $xlsxOk = $up('', 1, ['xlsx'], $fakeUpload('a.xlsx', $xlsx));
    $odtOk = $up('', 1, ['odt'], $fakeUpload('a.odt', $odt));
    if ($temZip) {
        check($docxOk->isValid() && $xlsxOk->isValid() && $odtOk->isValid(), 'SEC-312 DOCX, XLSX e ODT estruturalmente válidos são aceitos');
    } else {
        check(!$docxOk->isValid() && in_array('Não foi possível verificar a estrutura do documento (extensão zip ausente).', $docxOk->getError(), true), 'SEC-312 sem ext-zip, formatos de escritório são recusados (falha fechada)');
    }
    $rejeicoesPacote = [
        'ZIP genérico como .docx' => $up('', 1, ['docx'], $fakeUpload('a.docx', $zipGenerico)),
        'ZIP genérico como .xlsx' => $up('', 1, ['xlsx'], $fakeUpload('a.xlsx', $zipGenerico)),
        'ZIP genérico como .pptx' => $up('', 1, ['pptx'], $fakeUpload('a.pptx', $zipGenerico)),
        'ZIP genérico como .odt' => $up('', 1, ['odt'], $fakeUpload('a.odt', $zipGenerico)),
        'DOCX sem a parte principal' => $up('', 1, ['docx'], $fakeUpload('a.docx', $docxSemParte)),
        'DOCX sem [Content_Types].xml' => $up('', 1, ['docx'], $fakeUpload('a.docx', $docxSemCt)),
        'ODF com mimetype de outro formato' => $up('', 1, ['odt'], $fakeUpload('a.odt', $odtErrado)),
        'ODF com mimetype maior que o esperado' => $up('', 1, ['odt'], $fakeUpload('a.odt', $odtLongo)),
    ];
    foreach ($rejeicoesPacote as $caso => $u) {
        check(!$u->isValid() && $u->getPath() === '', "SEC-312 rejeita {$caso}");
    }
    $zipPuro = $up('', 1, ['zip'], $fakeUpload('a.zip', $zipGenerico));
    check($zipPuro->isValid(), 'SEC-312 ZIP genérico com extensão .zip continua aceito');
    // Leitura do mimetype é limitada: uma entrada comprimida que expande para 32 MB não é carregada inteira
    if ($temZip && function_exists('memory_reset_peak_usage')) {
        $odfBomba = $tmp . '/bomba.odt';
        $zipBomba = new \ZipArchive();
        $zipBomba->open($odfBomba, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zipBomba->addFromString('mimetype', 'application/vnd.oasis.opendocument.text' . str_repeat("\0", 32 << 20));
        $zipBomba->addFromString('content.xml', '<x/>');
        $zipBomba->close();
        $inspecao = new ($UploadTeste::class)('', 1, ['odt'], []);
        foreach (['arqTemp' => $odfBomba, 'arqType' => 'odt'] as $prop => $valor) {
            (new \ReflectionProperty(\OliviaUpload\Upload::class, $prop))->setValue($inspecao, $valor);
        }
        gc_collect_cycles();
        memory_reset_peak_usage();
        $antesPico = memory_get_usage();
        $resultadoBomba = (new \ReflectionMethod(\OliviaUpload\Upload::class, 'valida_pacote'))->invoke($inspecao);
        check($resultadoBomba === false && memory_get_peak_usage() - $antesPico < (4 << 20) && filesize($odfBomba) < (1 << 20), 'SEC-312 mimetype ODF é lido com limite (entrada de 32 MB não é carregada)');
    }

    // Extensões sem MIME conhecido exigem MIME declarado pelo chamador
    $semMapa = $up('', 1, ['log'], $fakeUpload('a.log', "linha 1\nlinha 2\n"));
    check(!$semMapa->isValid() && strpos(implode(' ', $semMapa->getError()), 'declare o MIME') !== false, 'SEC-312 extensão sem MIME mapeado e sem declaração é recusada');
    $declarado = $up('', 1, ['log' => 'text/plain'], $fakeUpload('a.log', "linha 1\nlinha 2\n"));
    check($declarado->isValid(), 'SEC-312 extensão sem mapa aceita com MIME declarado compatível');
    $declaradoLista = $up('', 1, ['png', 'log' => ['application/json', 'TEXT/PLAIN']], $fakeUpload('b.log', "linha de log 1\nlinha de log 2\n"));
    check($declaradoLista->isValid(), 'SEC-312 MIME declarado em lista (sem distinção de caixa) é aceito');
    $declaradoErrado = $up('', 1, ['log' => 'application/pdf'], $fakeUpload('a.log', "texto\n"));
    check(!$declaradoErrado->isValid(), 'SEC-312 conteúdo diferente do MIME declarado é recusado');
    $declaradoBloqueado = $up('', 1, ['dat' => 'text/html'], $fakeUpload('a.dat', '<!DOCTYPE html><html><body>x</body></html>'));
    check(!$declaradoBloqueado->isValid(), 'SEC-312 MIME declarado não libera tipos bloqueados (HTML)');
    $mapaContinua = $up('', 1, ['png'], $fakeUpload('c.png', $png));
    check($mapaContinua->isValid(), 'SEC-312 extensões mapeadas continuam funcionando sem declaração');

    // Falha de chmod é reportada e o upload é desfeito
    $UploadSemChmod = new class ('', 1, [], []) extends \OliviaUpload\Upload {
        protected function moverArquivo(string $origem, string $destino): bool
        {
            return rename($origem, $destino);
        }
        protected function aplicarPermissao(string $caminho, int $modo): bool
        {
            return false;
        }
    };
    $antesChmod = glob($uploadBase . '/*.png') ?: [];
    $semChmod = new ($UploadSemChmod::class)('', 1, ['png'], $fakeUpload('d.png', $png));
    check(!$semChmod->isValid() && strpos(implode(' ', $semChmod->getError()), 'permissões') !== false && (glob($uploadBase . '/*.png') ?: []) === $antesChmod, 'SEC-312 falha de chmod no arquivo é reportada e o arquivo é removido');
    $semChmodPasta = new ($UploadSemChmod::class)('pasta-nova-chmod', 1, ['png'], $fakeUpload('e.png', $png));
    check(!$semChmodPasta->isValid() && strpos(implode(' ', $semChmodPasta->getError()), 'permissões da pasta') !== false, 'SEC-312 falha de chmod na pasta é reportada');
    check(!file_exists($uploadBase . '/pasta-nova-chmod'), 'SEC-312 pasta cujo chmod falhou é removida');
    $refeita = $up('pasta-nova-chmod', 1, ['png'], $fakeUpload('e2.png', $png));
    check($refeita->isValid() && (fileperms($uploadBase . '/pasta-nova-chmod') & 0777) === 0750, 'SEC-312 nova tentativa recria a pasta com 0750');

    // Base criada pelo framework recebe 0750 em cada nível, mesmo sob umask 077; base do operador não é alterada
    $umaskAnterior = umask(077);
    $baseNova = $tmp . '/base-nova/n1/n2';
    putenv('OLIVIA_UPLOAD_DIR=' . $baseNova);
    $naBaseNova = $up('', 1, ['png'], $fakeUpload('f.png', $png));
    $modosBase = array_map(fn ($d) => is_dir($d) ? fileperms($d) & 0777 : null, [$tmp . '/base-nova', $tmp . '/base-nova/n1', $baseNova]);
    check($naBaseNova->isValid() && $modosBase === [0750, 0750, 0750], 'SEC-312 base criada pelo framework recebe 0750 em cada nível (umask 077)');
    putenv('OLIVIA_UPLOAD_DIR=' . $tmp . '/base-sem-chmod/x');
    $semChmodBase = new ($UploadSemChmod::class)('', 1, ['png'], $fakeUpload('g.png', $png));
    check(!$semChmodBase->isValid() && strpos(implode(' ', $semChmodBase->getError()), 'permissões da pasta') !== false && !file_exists($tmp . '/base-sem-chmod'), 'SEC-312 base cujo chmod falhou não fica no disco');
    @mkdir($tmp . '/base-operador');
    chmod($tmp . '/base-operador', 0710);
    putenv('OLIVIA_UPLOAD_DIR=' . $tmp . '/base-operador');
    $naBaseOperador = $up('sub', 1, ['png'], $fakeUpload('h.png', $png));
    check($naBaseOperador->isValid() && (fileperms($tmp . '/base-operador') & 0777) === 0710 && (fileperms($tmp . '/base-operador/sub') & 0777) === 0750, 'SEC-312 base preexistente do operador mantém o modo; subpasta nova recebe 0750');
    umask($umaskAnterior);
    putenv('OLIVIA_UPLOAD_DIR=' . $uploadBase);
    check(!property_exists(\OliviaUpload\Upload::class, 'permissao') && (new \ReflectionMethod(\OliviaUpload\Upload::class, '__construct'))->getNumberOfParameters() === 6, 'SEC-312 propriedade $permissao removida, assinatura do construtor mantida');

    // Apache nega storage/ (raiz) e storage/.htaccess nega tudo mesmo sem mod_rewrite
    $htRaiz = (string) @file_get_contents($root . '/.htaccess');
    $htStorage = (string) @file_get_contents($root . '/storage/.htaccess');
    check(preg_match('/RewriteRule \^storage\(\?:\/\|\$\) - \[[^\]]*R=404[^\]]*\]/', $htRaiz) === 1 && strpos($htRaiz, 'RewriteRule ^storage') < strpos($htRaiz, 'RewriteRule ^ index.php'), 'SEC-312 .htaccess da raiz bloqueia storage/ antes do front controller (reforço do storage/.htaccess)');
    check(strpos($htStorage, 'Require all denied') !== false && strpos($htStorage, 'Deny from all') !== false && strpos($htStorage, 'php_flag engine off') !== false, 'SEC-312 storage/.htaccess nega acesso (2.4 e 2.2) e desliga PHP');

    putenv('OLIVIA_UPLOAD_DIR');

    // ---------- SEC-305: sessão, CSRF e redirects ----------
    grupo('SEC-305');
    $probe = $tmp . '/config-probe.php';
    file_put_contents($probe, '<?php
        require ' . var_export($root . '/vendor/autoload.php', true) . ';
        ini_set("session.save_path", ' . var_export($tmp, true) . ');
        $_SERVER = array_merge($_SERVER, json_decode(getenv("PROBE_SERVER") ?: "{}", true));
        $_SERVER["SCRIPT_NAME"] = "/app/index.php";
        new \OliviaLib\Config();
        $antesToken = $_SESSION["UUID"];
        $antesId = session_id();
        \OliviaLib\Config::regenerateSession();
        echo json_encode([
            "secure" => ini_get("session.cookie_secure"),
            "samesite" => ini_get("session.cookie_samesite"),
            "httponly" => ini_get("session.cookie_httponly"),
            "strict" => ini_get("session.use_strict_mode"),
            "only_cookies" => ini_get("session.use_only_cookies"),
            "token" => $antesToken,
            "rotated" => $_SESSION["UUID"] !== $antesToken && preg_match("/^[0-9a-f]{64}$/", $_SESSION["UUID"]) === 1,
            "id_changed" => session_id() !== $antesId,
            "secret_key_len" => strlen($_SESSION["secret_key"]),
            "base_path" => $_SESSION["BASE_PATH"],
        ]);
    ');
    $errosProbe = '';
    $rodar = function (array $server, array $env = []) use ($probe, &$errosProbe) {
        [$saida, $erros] = executarPhp(['-d', 'display_errors=stderr', '-d', 'log_errors=0', $probe], array_merge(['PROBE_SERVER' => json_encode($server)], $env));
        $errosProbe .= $erros;
        return json_decode($saida, true);
    };
    $http = $rodar([]);
    check(is_array($http) && $http['samesite'] === 'Lax' && $http['httponly'] === '1' && $http['strict'] === '1' && $http['only_cookies'] === '1', 'SEC-305 cookie de sessão com SameSite=Lax, HttpOnly, strict mode e only_cookies');
    check(is_array($http) && $http['secure'] === '0', 'SEC-305 cookie sem Secure em HTTP (não quebra desenvolvimento local)');
    $https = $rodar(['HTTPS' => 'on']);
    check(is_array($https) && $https['secure'] === '1', 'SEC-305 cookie Secure em HTTPS');
    $proxy = $rodar([], ['OLIVIA_FORCE_SECURE' => 'true']);
    check(is_array($proxy) && $proxy['secure'] === '1', 'SEC-305 OLIVIA_FORCE_SECURE força Secure atrás de proxy TLS');
    check(is_array($http) && preg_match('/^[0-9a-f]{64}$/', $http['token']) === 1 && $http['secret_key_len'] === 64, 'SEC-305 token CSRF e chave de sessão gerados com random_bytes');
    check(is_array($http) && $http['rotated'] === true && $http['id_changed'] === true, 'SEC-305 regenerateSession() renova ID de sessão e token CSRF');
    check(is_array($http) && $http['base_path'] === '/app', 'SEC-305 BASE_PATH continua derivado de SCRIPT_NAME');
    check($errosProbe === '', 'SEC-305 subprocessos de sessão não emitem warnings nem erros em stderr');

    // CSRF no dispatcher: hash_equals, header X-CSRF-Token, token vazio
    $csrfToken = str_repeat('ab', 32);
    $csrfRouter = new \OliviaRouter\Router(new \OliviaRouter\RouterConfig('OliviaTest', 'Controller', 'Middleware', '', true, $csrfToken));
    $csrfRouter->post('/item', 'ProbeController#save');
    \OliviaTest\Controller\ProbeController::$calls = [];
    $csrfRouter->execute(new \OliviaRouter\Request('POST', '/item', [], [], [], ['HTTP_X_CSRF_TOKEN' => $csrfToken]));
    check(\OliviaTest\Controller\ProbeController::$calls === [['save', 'POST']], 'SEC-305 token aceito via header X-CSRF-Token');
    \OliviaTest\Controller\ProbeController::$calls = [];
    check(throws(fn () => $csrfRouter->execute(new \OliviaRouter\Request('POST', '/item', [], [], [], ['HTTP_X_CSRF_TOKEN' => 'errado'])), \RuntimeException::class, 'CSRF') && \OliviaTest\Controller\ProbeController::$calls === [], 'SEC-305 header X-CSRF-Token inválido é rejeitado sem chamar o handler');
    check(throws(fn () => $csrfRouter->execute(new \OliviaRouter\Request('POST', '/item', ['_token' => ['array']], [], [], [])), \RuntimeException::class, 'CSRF') && \OliviaTest\Controller\ProbeController::$calls === [], 'SEC-305 token não-string é rejeitado com RuntimeException (sem TypeError) e sem chamar o handler');
    $semToken = new \OliviaRouter\Router(new \OliviaRouter\RouterConfig('OliviaTest', 'Controller', 'Middleware', '', true, ''));
    $semToken->post('/item', 'ProbeController#save');
    check(throws(fn () => $semToken->execute(new \OliviaRouter\Request('POST', '/item', ['_token' => ''], [], [], [])), \RuntimeException::class, 'CSRF') && \OliviaTest\Controller\ProbeController::$calls === [], 'SEC-305 token esperado vazio nunca valida');

    // ---------- SEC-311: CSRF antes dos middlewares, token não-string, Secure estrito, proxy confiável ----------
    grupo('SEC-311');
    $mwRouter = new \OliviaRouter\Router(new \OliviaRouter\RouterConfig('OliviaTest', 'Controller', 'Middleware', '', true, $csrfToken));
    $mwRouter->middleware('SideEffect')->post('/mw', 'ProbeController#save');
    \OliviaTest\Middleware\SideEffect::$calls = 0;
    \OliviaTest\Controller\ProbeController::$calls = [];
    $forjada = throws(fn () => $mwRouter->execute(new \OliviaRouter\Request('POST', '/mw', ['_token' => 'forjado'], [], [], [])), \RuntimeException::class, 'CSRF');
    check($forjada && \OliviaTest\Middleware\SideEffect::$calls === 0 && \OliviaTest\Controller\ProbeController::$calls === [], 'SEC-311 requisição forjada é rejeitada antes de qualquer middleware');
    seguro(fn () => $mwRouter->execute(new \OliviaRouter\Request('POST', '/mw', ['_token' => $csrfToken], [], [], [])));
    check(\OliviaTest\Middleware\SideEffect::$calls === 1 && \OliviaTest\Controller\ProbeController::$calls === [['save', 'POST']], 'SEC-311 requisição válida executa middleware e handler');
    \OliviaTest\Controller\ProbeController::$calls = [];
    $arrayComHeader = throws(fn () => $csrfRouter->execute(new \OliviaRouter\Request('POST', '/item', ['_token' => ['x']], [], [], ['HTTP_X_CSRF_TOKEN' => $csrfToken])), \RuntimeException::class, 'CSRF');
    check($arrayComHeader && \OliviaTest\Controller\ProbeController::$calls === [], 'SEC-311 _token não-string é rejeitado mesmo com header válido (sem fallback)');
    seguro(fn () => $csrfRouter->execute(new \OliviaRouter\Request('POST', '/item', ['_token' => ''], [], [], ['HTTP_X_CSRF_TOKEN' => $csrfToken])));
    check(\OliviaTest\Controller\ProbeController::$calls === [['save', 'POST']], 'SEC-311 _token vazio continua usando o header X-CSRF-Token');
    \OliviaTest\Controller\ProbeController::$calls = [];
    foreach (['null' => null, 'inteiro' => 123, 'booleano' => true] as $rotuloTipo => $valorTipo) {
        $rejeitadoTipo = throws(fn () => $csrfRouter->execute(new \OliviaRouter\Request('POST', '/item', ['_token' => $valorTipo], [], [], ['HTTP_X_CSRF_TOKEN' => $csrfToken])), \RuntimeException::class, 'CSRF');
        check($rejeitadoTipo && \OliviaTest\Controller\ProbeController::$calls === [], "SEC-311 _token presente {$rotuloTipo} é rejeitado mesmo com header válido");
    }
    seguro(fn () => $csrfRouter->execute(new \OliviaRouter\Request('POST', '/item', ['outro' => 'x'], [], [], ['HTTP_X_CSRF_TOKEN' => $csrfToken])));
    check(\OliviaTest\Controller\ProbeController::$calls === [['save', 'POST']], 'SEC-311 _token ausente usa o header X-CSRF-Token');

    // RequestPost: header X-CSRF-Token e _token não-string
    $postAntes = $_POST;
    $serverAntes = $_SERVER;
    $sessaoUuid = $_SESSION['UUID'] ?? null;
    $_SESSION['UUID'] = $csrfToken;
    $_POST = [];
    $_SERVER['HTTP_X_CSRF_TOKEN'] = $csrfToken;
    check(seguro(fn () => @(new \OliviaApp\Middleware\RequestPost())->handle()) === true, 'SEC-311 RequestPost aceita o header X-CSRF-Token');
    $_POST = ['_token' => ['x']];
    check(throws(fn () => @(new \OliviaApp\Middleware\RequestPost())->handle(), \OliviaRouter\MiddlewareHaltException::class, 'CSRF'), 'SEC-311 RequestPost rejeita _token não-string mesmo com header válido');
    $_POST = ['_token' => null];
    check(throws(fn () => @(new \OliviaApp\Middleware\RequestPost())->handle(), \OliviaRouter\MiddlewareHaltException::class, 'CSRF'), 'SEC-311 RequestPost rejeita _token null presente mesmo com header válido');
    $_POST = ['_token' => ''];
    check(seguro(fn () => @(new \OliviaApp\Middleware\RequestPost())->handle()) === true, 'SEC-311 RequestPost com _token vazio usa o header');
    $_POST = ['_token' => $csrfToken];
    unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    check(seguro(fn () => @(new \OliviaApp\Middleware\RequestPost())->handle()) === true, 'SEC-311 RequestPost aceita _token válido');
    $_SERVER['HTTP_X_CSRF_TOKEN'] = 'errado';
    $_POST = [];
    check(throws(fn () => @(new \OliviaApp\Middleware\RequestPost())->handle(), \OliviaRouter\MiddlewareHaltException::class, 'CSRF'), 'SEC-311 RequestPost rejeita header inválido');
    $_POST = $postAntes;
    $_SERVER = $serverAntes;
    $_SESSION['UUID'] = $sessaoUuid;

    // Config::isSecureRequest: override estrito e X-Forwarded-Proto só de proxy confiável
    $serverAntes = $_SERVER;
    $envAntes = ['OLIVIA_FORCE_SECURE' => getenv('OLIVIA_FORCE_SECURE'), 'OLIVIA_TRUSTED_PROXIES' => getenv('OLIVIA_TRUSTED_PROXIES')];
    $secure = function (array $server, array $env) {
        foreach (['OLIVIA_FORCE_SECURE', 'OLIVIA_TRUSTED_PROXIES'] as $k) {
            array_key_exists($k, $env) ? putenv($k . '=' . $env[$k]) : putenv($k);
        }
        $_SERVER = $server;
        return \OliviaLib\Config::isSecureRequest();
    };
    $logSecure = $tmp . '/force-secure.log';
    $logAnteriorSec = ini_set('error_log', $logSecure);
    check($secure(['HTTPS' => 'on'], ['OLIVIA_FORCE_SECURE' => 'talvez']) === true, 'SEC-311 OLIVIA_FORCE_SECURE inválido não desliga Secure em HTTPS');
    check($secure([], ['OLIVIA_FORCE_SECURE' => 'talvez']) === false, 'SEC-311 OLIVIA_FORCE_SECURE inválido mantém a detecção automática em HTTP');
    ini_set('error_log', (string) $logAnteriorSec);
    check(strpos((string) @file_get_contents($logSecure), 'OLIVIA_FORCE_SECURE inválido') !== false, 'SEC-311 OLIVIA_FORCE_SECURE inválido é registrado em log');
    check($secure([], ['OLIVIA_FORCE_SECURE' => 'true']) === true && $secure(['HTTPS' => 'on'], ['OLIVIA_FORCE_SECURE' => 'false']) === false && $secure([], ['OLIVIA_FORCE_SECURE' => ' 1 ']) === true, 'SEC-311 OLIVIA_FORCE_SECURE válido continua sendo respeitado');
    $xfp = ['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https'];
    check($secure($xfp, []) === false, 'SEC-311 X-Forwarded-Proto ignorado sem OLIVIA_TRUSTED_PROXIES');
    check($secure($xfp, ['OLIVIA_TRUSTED_PROXIES' => '10.0.0.1, 192.168.0.0/16']) === false, 'SEC-311 X-Forwarded-Proto ignorado de origem não confiável');
    check($secure($xfp, ['OLIVIA_TRUSTED_PROXIES' => '10.0.0.1, 203.0.113.9']) === true, 'SEC-311 X-Forwarded-Proto aceito de proxy confiável (IP exato)');
    check($secure(['REMOTE_ADDR' => '10.20.30.40', 'HTTP_X_FORWARDED_PROTO' => 'https, http'], ['OLIVIA_TRUSTED_PROXIES' => '10.0.0.0/8']) === true, 'SEC-311 X-Forwarded-Proto aceito de proxy em CIDR (primeiro valor da lista)');
    check($secure(['REMOTE_ADDR' => '10.20.30.40', 'HTTP_X_FORWARDED_PROTO' => 'https'], ['OLIVIA_TRUSTED_PROXIES' => '10.16.0.0/12']) === true && $secure(['REMOTE_ADDR' => '10.48.0.1', 'HTTP_X_FORWARDED_PROTO' => 'https'], ['OLIVIA_TRUSTED_PROXIES' => '10.16.0.0/12']) === false, 'SEC-311 CIDR com prefixo não múltiplo de 8 compara os bits corretos');
    check($secure(['REMOTE_ADDR' => '::1', 'HTTP_X_FORWARDED_PROTO' => 'https'], ['OLIVIA_TRUSTED_PROXIES' => '::1/128']) === true && $secure(['REMOTE_ADDR' => '::2', 'HTTP_X_FORWARDED_PROTO' => 'https'], ['OLIVIA_TRUSTED_PROXIES' => '::1/128']) === false, 'SEC-311 proxy confiável IPv6');
    check($secure(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'http'], ['OLIVIA_TRUSTED_PROXIES' => '203.0.113.9']) === false && $secure(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https'], ['OLIVIA_TRUSTED_PROXIES' => '203.0.113.0/33, lixo']) === false, 'SEC-311 proto http e entradas inválidas da lista não ativam Secure');
    foreach ($envAntes as $k => $v) {
        $v === false ? putenv($k) : putenv($k . '=' . $v);
    }
    $_SERVER = $serverAntes;

    // Página de erro 404 responde HTTP 404
    $probe404 = $tmp . '/error404-probe.php';
    file_put_contents($probe404, '<?php
        require ' . var_export($root . '/vendor/autoload.php', true) . ';
        session_save_path(' . var_export($tmp, true) . ');
        session_start();
        $_SESSION["BASE_PATH"] = "";
        ob_start();
        (new \OliviaApp\Controller\HomeController())->error404();
        $html = ob_get_clean();
        echo json_encode(["status" => http_response_code(), "html" => strlen($html) > 0]);
    ');
    [$saida404, $erros404] = executarPhp(['-d', 'display_errors=stderr', '-d', 'log_errors=0', $probe404]);
    $r404 = json_decode($saida404, true);
    check(is_array($r404) && $r404['status'] === 404 && $r404['html'] === true && $erros404 === '', 'SEC-311 /error404 responde HTTP 404 com a página de erro (sem warnings)');

    // Comparação estrita do token: com ==, "0e1234" e "0e5678" seriam iguais (ambos 0 em notação científica)
    $juggling = new \OliviaRouter\Router(new \OliviaRouter\RouterConfig('OliviaTest', 'Controller', 'Middleware', '', true, '0e1234'));
    $juggling->post('/item', 'ProbeController#save');
    \OliviaTest\Controller\ProbeController::$calls = [];
    check(throws(fn () => $juggling->execute(new \OliviaRouter\Request('POST', '/item', ['_token' => '0e5678'], [], [], [])), \RuntimeException::class, 'CSRF') && \OliviaTest\Controller\ProbeController::$calls === [], 'SEC-305 token CSRF comparado de forma estrita (sem type juggling "0e...")');
    seguro(fn () => $juggling->execute(new \OliviaRouter\Request('POST', '/item', ['_token' => '0e1234'], [], [], [])));
    check(\OliviaTest\Controller\ProbeController::$calls === [['save', 'POST']], 'SEC-305 token idêntico continua aceito');

    // Redirect de 404 sem HTTP_HOST
    $R = \OliviaLib\Route::class;
    check($R::notFoundLocation('') === '/error404' && $R::notFoundLocation('/app') === '/app/error404' && $R::notFoundLocation('/a/b/') === '/a/b/error404', 'SEC-305 redirect 404 relativo à raiz do host');
    $maliciosos = ['//evil.com', '///evil.com', '/http://evil', 'https://evil.com', "/x\r\nSet-Cookie: a=b", '/\\evil.com', '/a/../../b'];
    $seguros = array_map(fn ($b) => $R::notFoundLocation($b), $maliciosos);
    $todosSeguros = array_reduce($seguros, fn ($ok, $loc) => $ok && $loc[0] === '/' && substr($loc, 0, 2) !== '//' && strpbrk($loc, ":\r\n\\") === false, true);
    check($todosSeguros, 'SEC-305 base path malicioso não gera redirect para outro host nem injeção de header');

    // ---------- QA-314: provas HTTP reais (php -S) ----------
    grupo('QA-314 HTTP');
    $www = $tmp . '/www';
    mkdir($www, 0700);
    $tokenHttp = str_repeat('cd', 32);
    $uploadHttp = $tmp . '/http-uploads';
    file_put_contents($www . '/roteador.php', '<?php
        $raiz = ' . var_export($root, true) . ';
        require $raiz . "/vendor/autoload.php";
        $caminho = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
        if ($caminho === "/__redirect") {
            (new \OliviaLib\CommandController())->redirect("destino", ["id" => 1]);
            echo "DEPOIS-DO-REDIRECT";
            return true;
        }
        if ($caminho === "/__upload") {
            $u = new \OliviaUpload\Upload("http", 1, ["png"], $_FILES["f"] ?? []);
            header("Content-Type: application/json");
            echo json_encode([
                "valido" => $u->isValid(),
                "erros" => $u->getError(),
                "dentro" => $u->isValid() && strpos($u->getPath(), realpath(getenv("OLIVIA_UPLOAD_DIR")) . "/http/") === 0,
                "arquivo" => $u->isValid() ? fileperms($u->getPath()) & 0777 : null,
                "pasta" => $u->isValid() ? fileperms(dirname($u->getPath())) & 0777 : null,
                "temp_restante" => is_file((string) ($_FILES["f"]["tmp_name"] ?? "")),
            ]);
            return true;
        }
        if ($caminho === "/__request_post") {
            session_start();
            $_SESSION["UUID"] = getenv("PROBE_TOKEN");
            try {
                echo (new \OliviaApp\Middleware\RequestPost())->handle() === true ? "ACEITO" : "OUTRO";
            } catch (\OliviaRouter\MiddlewareHaltException $e) {
                echo "REJEITADO";
            }
            return true;
        }
        $_SERVER["SCRIPT_NAME"] = "/index.php";
        chdir($raiz);
        require $raiz . "/index.php";
        return true;
    ');
    [$srv, $porta, $logHttp] = servidorHttp($www . '/roteador.php', ['session.save_path' => $tmp, 'display_errors' => 'stderr', 'log_errors' => '0'], [
        'OLIVIA_UPLOAD_DIR' => $uploadHttp,
        'OLIVIA_TRUSTED_PROXIES' => '127.0.0.1',
        'PROBE_TOKEN' => $tokenHttp,
    ]);

    // redirect() encerra a requisição: 302 + Location e nada do que vem depois chega ao corpo
    $r = requisicaoHttp($porta, 'GET', '/__redirect');
    check($r['status'] === 302 && ($r['headers']['location'][0] ?? null) === './destino?id=1' && strpos($r['body'], 'DEPOIS-DO-REDIRECT') === false, 'CLEAN-307 redirect() responde 302 com Location e encerra a requisição (HTTP real)');

    // Rota inexistente: 302 para /error404 relativo, sem usar o Host enviado pelo cliente
    $r = requisicaoHttp($porta, 'GET', '/nao-existe', ['Host: evil.example']);
    check($r['status'] === 302 && ($r['headers']['location'] ?? []) === ['/error404'] && stripos(implode(' ', $r['headers']['location'] ?? []), 'evil') === false, 'SEC-305 rota inexistente redireciona para /error404 sem usar o Host do cliente (HTTP real)');
    check(trim($r['body']) === '', 'SEC-305 lib/Route encerra após o redirect de 404 (corpo vazio)');

    // /error404: status 404 (o HTML do skeleton é só o corpo) e /livez 200
    $r = requisicaoHttp($porta, 'GET', '/error404');
    check($r['status'] === 404 && stripos($r['body'], '<html') !== false, 'SEC-311 /error404 responde status HTTP 404 com o HTML da página (HTTP real)');
    $r = requisicaoHttp($porta, 'GET', '/livez');
    check($r['status'] === 200 && $r['body'] === 'ok', 'livez responde 200 "ok" (HTTP real)');

    // Cookie de sessão emitido de fato: HttpOnly e SameSite=Lax; Secure só com proto https de proxy confiável
    $cookieDe = fn (array $resp) => implode(' | ', array_filter($resp['headers']['set-cookie'] ?? [], fn ($c) => stripos($c, session_name() . '=') === 0));
    $semTls = $cookieDe($r);
    check($semTls !== '' && stripos($semTls, 'HttpOnly') !== false && stripos($semTls, 'SameSite=Lax') !== false && stripos($semTls, 'secure') === false, 'SEC-305 Set-Cookie real com HttpOnly e SameSite=Lax, sem Secure em HTTP');
    $comTls = $cookieDe(requisicaoHttp($porta, 'GET', '/livez', ['X-Forwarded-Proto: https']));
    check(stripos($comTls, '; secure') !== false, 'SEC-311 Set-Cookie real com Secure quando proxy confiável envia X-Forwarded-Proto: https');

    // RequestPost com formulário HTTP real (application/x-www-form-urlencoded)
    $form = ['Content-Type: application/x-www-form-urlencoded'];
    $r = requisicaoHttp($porta, 'POST', '/__request_post', $form, http_build_query(['_token' => $tokenHttp]));
    check($r['status'] === 200 && $r['body'] === 'ACEITO', 'SEC-311 RequestPost aceita _token válido vindo de formulário HTTP real');
    $r = requisicaoHttp($porta, 'POST', '/__request_post', $form, http_build_query(['_token' => 'forjado']));
    check($r['status'] === 302 && $r['body'] === 'REJEITADO', 'SEC-311 RequestPost rejeita _token forjado vindo de formulário HTTP real');
    $r = requisicaoHttp($porta, 'POST', '/__request_post', array_merge($form, ['X-CSRF-Token: ' . $tokenHttp]), http_build_query(['_token' => ['x']]));
    check($r['body'] === 'REJEITADO', 'SEC-311 RequestPost rejeita _token array mesmo com header válido (HTTP real)');

    // Upload multipart real: mover real (is_uploaded_file/move_uploaded_file) e modos finais
    $limite = 'olivia' . bin2hex(random_bytes(6));
    $multipart = "--{$limite}\r\nContent-Disposition: form-data; name=\"f\"; filename=\"foto.png\"\r\nContent-Type: image/png\r\n\r\n" . $png . "\r\n--{$limite}--\r\n";
    $r = requisicaoHttp($porta, 'POST', '/__upload', ['Content-Type: multipart/form-data; boundary=' . $limite], $multipart);
    $up200 = json_decode($r['body'], true);
    check(is_array($up200) && $up200['valido'] === true && $up200['dentro'] === true && $up200['arquivo'] === 0640 && $up200['pasta'] === 0750 && $up200['temp_restante'] === false, 'SEC-304 upload multipart real é movido para dentro da base com 0640/0750 (HTTP real)');
    $multipartHtml = "--{$limite}\r\nContent-Disposition: form-data; name=\"f\"; filename=\"x.png\"\r\nContent-Type: image/png\r\n\r\n<?php echo 1; ?>\r\n--{$limite}--\r\n";
    $upHtml = json_decode(requisicaoHttp($porta, 'POST', '/__upload', ['Content-Type: multipart/form-data; boundary=' . $limite], $multipartHtml)['body'], true);
    check(is_array($upHtml) && $upHtml['valido'] === false && count(glob($uploadHttp . '/http/*') ?: []) === 1, 'SEC-304 upload multipart real com PHP disfarçado de PNG é recusado e nada é gravado');

    proc_terminate($srv);
    proc_close($srv);
    $logServidor = (string) @file_get_contents($logHttp);
    check(preg_match('/(Warning|Notice|Deprecated|Fatal error|Uncaught)/i', $logServidor) !== 1, 'QA-314 servidor HTTP sem warnings, depreciações ou erros no log');

    // ---------- Email ----------
    grupo('Email');
    $baseEmail = $tmp . '/email-uploads';
    mkdir($baseEmail, 0700);
    file_put_contents($baseEmail . '/anexo.txt', 'anexo');
    file_put_contents($tmp . '/fora.txt', 'fora');
    putenv('OLIVIA_UPLOAD_DIR=' . $baseEmail);
    $temPhpMailer = class_exists('PHPMailer\\PHPMailer\\PHPMailer');
    $email = new \OliviaLib\Email('a@b.c', 'msg', 'assunto');
    if ($temPhpMailer) {
        check($email->enviar() === 'Configuração SMTP ausente (OLIVIA_SMTP_HOST / OLIVIA_SMTP_USER).', 'Email::enviar sem OLIVIA_SMTP_* falha com mensagem exata');
    } else {
        check($email->enviar() === 'PHPMailer não está instalado (composer require phpmailer/phpmailer).', 'Email::enviar sem PHPMailer falha com mensagem exata (dependência opcional)');
    }
    check((new \OliviaLib\Email('', 'msg', 'assunto'))->enviar() === 'Destinatário não informado.', 'Email::enviar sem destinatário falha com mensagem exata');
    // Base existe: a recusa vem da contenção, não de base ausente
    check(is_dir($baseEmail) && \OliviaLib\Email::resolveAttachment((object) ['src' => 'anexo.txt']) === realpath($baseEmail . '/anexo.txt'), 'Email resolve anexo dentro do diretório de upload');
    check(\OliviaLib\Email::resolveAttachment((object) ['src' => '../fora.txt']) === null && is_file($tmp . '/fora.txt'), 'Email rejeita anexo existente fora do diretório de upload');
    check(\OliviaLib\Email::resolveAttachment(['src' => 'nao-existe.txt']) === null && \OliviaLib\Email::resolveAttachment((object) ['src' => ['x']]) === null, 'Email rejeita anexo inexistente ou com src não-string');

    // cleanup
    session_write_close();
    restaurarAmbiente($ambienteOriginal);
    $limpar = function (string $dir) use (&$limpar) {
        // Links simbólicos são removidos sem seguir o alvo (os testes de upload criam links para dentro e fora da base)
        foreach (array_merge(glob($dir . '/*') ?: [], glob($dir . '/.[!.]*') ?: []) as $f) {
            if (is_link($f) || !is_dir($f)) {
                @unlink($f);
            } else {
                $limpar($f);
            }
        }
        @rmdir($dir);
    };
    $limpar($tmp);
    check(!file_exists($tmp), 'QA-314 diretório temporário da suíte removido por completo');

    if ($failures > 0) {
        fwrite(STDERR, "behavior test failed: {$failures}\n");
        exit(1);
    }
    echo "behavior test passed\n";
}
