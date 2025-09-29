<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuncionarioGestor extends Model
{
    protected $table = 'NORBER_FUNCIONARIOS_GESTORES';

    protected $primaryKey = 'NORBER_FUNCIONARIO_GESTOR';

    protected $fillable = [
        'NORBER_FUNCIONARIO_GESTOR',
        'DATA',
        'MATRICULA',
        'NOME',
        'CENTRO_CUSTO',
        'MATRICULA_GESTOR',
        'INDICE',
        'NOME_GESTOR',
        'PAGINA'
    ];

    public $timestamps = false;
}
