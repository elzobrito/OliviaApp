<?php

namespace OliviaApp\Models;

use elzobrito\Model\Model;

class User extends Model
{
    protected $table = 'users';
    protected $drive = 'mysql';
    //add_item_demanda   
    protected $fillable = [
        'nome',
        'email',
        'funcao',
        'cpf',
        'matricula',
        'tipo',
        'subTipo',
        'id_unidade',
        'password',
        'remember_token',
        'status'
    ];

    protected $atributos = [
        'id',
        'nome',
        'email',
        'funcao',
        'cpf',
        'matricula',
        'tipo',
        'subTipo',
        'id_unidade',
        'password',
        'remember_token',
        'status',
        'created_at',
        'updated_at',
        'deleted_at'
    ];
}