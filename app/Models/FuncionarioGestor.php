<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuncionarioGestor extends Model
{
    protected $table = 'RH.NORBER_FUNCIONARIOS_GESTORES';

    protected $primaryKey = 'NORBER_FUNCIONARIO_GESTOR';

    protected $fillable = [
        'NORBER_FUNCIONARIO_GESTOR',
        'DATA',
        'MATRICULA',
        'MATRICULA_GESTOR',
        'NOME_GESTOR',
        'CENTRO_CUSTO',
        'UNIDADE_ORGANIZACIONAL'


    ];

    public $timestamps = false;
}
