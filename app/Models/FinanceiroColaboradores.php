<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinanceiroColaboradores extends Model
{
    protected $table = 'LG_COLABORADORES_FINANCEIROS';

    protected $primaryKey = 'LG_COLABORADORE_FINANCEIRO';

    protected $fillable = [
        'LG_COLABORADORE_FINANCEIRO',
        'MATRICULA',
        'NOME',
        'DESCRICAO',
        'VALOR',
        'CODIGO_EVENTO',
        'DATA_REGISTRO',
        'MES',
        'ANO'
    ];

    public $timestamps = false;
}
