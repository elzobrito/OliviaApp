<?php

namespace OliviaDatabaseModel;

use OliviaDatabaseLibrary\ConnectionManager;
use OliviaDatabaseLibrary\QueryBuilder;
use OliviaDatabaseLibrary\Model as iModel;
use OliviaLib\Support\Crypto;
use OliviaLib\Database;

#[\AllowDynamicProperties]
class Model implements iModel
{
    protected $table;
    protected $drive;
    protected $fillable = array();
    protected $atributos = array();

    /**
     * Campos que request()/request_cripto() nunca preenchem a partir de $_REQUEST
     * quando a lista de campos não é informada explicitamente (proteção contra
     * atribuição em massa). Sobrescreva no model para ajustar; atribua esses campos
     * manualmente ou passe uma lista explícita em request(['campo', ...]).
     */
    protected $guarded = [
        'id',
        'password',
        'senha',
        'remember_token',
        'tipo',
        'subTipo',
        'status',
        'role',
        'roles',
        'is_admin',
        'admin',
        'is_superuser',
        'superuser',
        'permissoes',
        'permissions',
        'nivel',
        'perfil',
        'privilegios',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    /**
     * Executa $callback em uma transação na conexão deste model:
     * commit no sucesso; rollback e relançamento da exceção na falha.
     *
     *   Pedido::transaction(function () use ($pedido, $itens) { ... });
     */
    public static function transaction(callable $callback)
    {
        $model = new static();
        return ConnectionManager::get(Database::getDB($model->drive))->transaction($callback);
    }

    public function getTable()
    {
        return $this->table;
    }

    public function update($fields, $wheres, $values)
    {
        return (new QueryBuilder(Database::getDB($this->drive)))
            ->table($this->table)
            ->fields($fields)
            ->where($wheres)
            ->update($values);
    }
    public function save($fields = [], $valores = [])
    {
        return (new QueryBuilder(Database::getDB($this->drive)))
            ->table($this->table)
            ->fields(($fields ?: $this->fill()))
            ->insert(($valores ?: $this->values()));
    }

    public function delete($id = null, $primaryKey = 'id')
    {
        $primaryKey = $primaryKey ?? 'id';
        $value = $id ?? ($this->{$primaryKey} ?? null);
        if ($value === null || $value === '') {
            throw new \InvalidArgumentException('Model::delete requer um identificador.');
        }

        return (new QueryBuilder(Database::getDB($this->drive)))
            ->table($this->table)
            ->where([$primaryKey . ' = ?'])
            ->delete([$value]);
    }


    /**
     * Exclusão com filtro/join. group, having e limit não são suportados de forma portável e são
     * rejeitados (antes eram descartados em silêncio, apagando todo o conjunto filtrado).
     * Com join usa a sintaxe multi-tabela do MySQL.
     */
    public function deleteFull($id = null, $primaryKey = null, $wheres = null, $values = null, $join = null, $group = null, $having = null, $limit = null)
    {
        foreach (['group' => $group, 'having' => $having, 'limit' => $limit] as $nome => $valor) {
            if ($valor !== null && $valor !== '' && $valor !== []) {
                throw new \InvalidArgumentException("Model::deleteFull não suporta {$nome}; restrinja pelo where.");
            }
        }

        $primaryKey = $primaryKey ?? 'id';
        $value = $id ?? ($this->{$primaryKey} ?? null);
        if (($value === null || $value === '') && $values === null) {
            throw new \InvalidArgumentException('Model::deleteFull requer um identificador ou valores.');
        }

        $temJoin = $join !== null && $join !== '' && $join !== [];
        if ($wheres !== null && ($wheres === '' || $wheres === [])) {
            throw new \InvalidArgumentException('Model::deleteFull exige where; um where vazio apagaria todo o conjunto.');
        }
        // Com join, a chave primária é qualificada pelo alias (ou nome) da tabela do model para não ficar ambígua.
        $partesTabela = preg_split('/\s+/', trim((string) $this->table));
        $pkWhere = ($temJoin ? end($partesTabela) . '.' : '') . $primaryKey . ' = ?';

        $qb = (new QueryBuilder(Database::getDB($this->drive)))
            ->table($this->table)
            ->where($wheres ?? [$pkWhere]);

        if ($temJoin) {
            return $qb->join($join)->deleteFull($values ?? [$value]);
        }

        return $qb->delete($values ?? [$value]);
    }

    public function find($fields, $wheres, $values, $join = null, $group = null, $order = null, $having = null, $limit = null)
    {
        return (new QueryBuilder(Database::getDB($this->drive)))
            ->table($this->table)
            ->fields($fields ?? $this->atributos)
            ->where($wheres)
            ->join($join)
            ->group($group)
            ->order($order)
            ->having($having)
            ->limit($limit)
            ->select($values ?? []);
    }

    public function find_table($fields, $wheres, $values, $join = null, $group = null, $order = null, $having = null, $limit = null, $table = null)
    {
        return (new QueryBuilder(Database::getDB($this->drive)))
            ->table($table ?? $this->table)
            ->fields($fields ?? $this->atributos)
            ->where($wheres)
            ->join($join)
            ->group($group)
            ->order($order)
            ->having($having)
            ->limit($limit)
            ->select($values ?? []);
    }


    public function all($fields = null, $order = null, $limit = null, $join = null, $group = null)
    {
        return (new QueryBuilder(Database::getDB($this->drive)))
            ->table($this->table)
            ->fields($fields ?? $this->atributos)
            ->order($order)
            ->limit($limit)
            ->join($join)
            ->group($group)
            ->select();
    }

    public function count($wheres = null, $values = null, $join = null, $group = null, $order = null, $having = null, $limit = null)
    {
        return (new QueryBuilder(Database::getDB($this->drive)))
            ->table($this->table)
            ->fields(['count(*) as total'])
            ->where($wheres)
            ->join($join)
            ->group($group)
            ->order($order)
            ->having($having)
            ->limit($limit)
            ->select($values ?? []);
    }

    public function findForId($id, $primaryKey = null, $fields = null, $join = null, $order = null, $limit = null)
    {
        return (new QueryBuilder(Database::getDB($this->drive)))
            ->table($this->table)
            ->fields($fields ?? $this->atributos)
            ->where([($primaryKey ?? 'id') . ' = ?'])
            ->join($join)
            ->order($order)
            ->limit($limit)
            ->select([$id]);
    }

    public function values()
    {
        $array = [];
        foreach ($this->fill() as $field) {
            $array[] = $this->{$field} ?? null;
        }
        return $array;
    }


    /**
     * Campos que podem ser preenchidos a partir da requisição.
     * Lista explícita do desenvolvedor é respeitada; caso contrário fillable menos guarded.
     */
    public function massAssignable($fields = null): array
    {
        if ($fields !== null) {
            return array_values(array_filter((array) $fields, 'is_string'));
        }

        $guarded = array_map('strtolower', (array) $this->guarded);
        return array_values(array_filter(
            (array) $this->fillable,
            fn ($field) => is_string($field) && !in_array(strtolower($field), $guarded, true)
        ));
    }

    public function request_cripto($fields = null)
    {
        foreach ($this->massAssignable($fields) as $value) {
            if (!isset($_REQUEST[$value])) {
                continue;
            }

            $input = $_REQUEST[$value];
            if (substr($value, 0, 2) === 'id' && !is_numeric($input)) {
                $decrypted = Crypto::decrypt($input);
                if (is_numeric($decrypted)) {
                    $input = $decrypted;
                }
            }

            $this->$value = $input;
        }
    }

    public function request($fields = null)
    {
        foreach ($this->massAssignable($fields) as $value) {
            if (isset($_REQUEST[$value])) {
                $this->$value = $_REQUEST[$value];
            }
        }
    }

    public function fill()
    {
        $array = [];
        foreach ($this->fillable as $key => $value) {
            $array[] = $value;
        }
        return $array;
    }
}
