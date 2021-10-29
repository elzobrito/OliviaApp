<?php

namespace OliviaApp\Models;

use OliviaDatabaseModel\Model;

class Configuracoes extends Model
{
    protected $table = 'configuracoes';
    protected $drive = 'mysql';
    
    protected $fillable = [
        'parametro',
        'valor',
    ];

    protected $atributos = [
        'id',
        'parametro',
        'valor',
        'status',
        'created_at',
    ];
}