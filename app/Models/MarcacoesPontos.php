<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarcacoesPontos extends Model
{
    protected $table = 'RH.NORBER_MARCACOES_PONTOS';

    protected $primaryKey = 'NORBER_MARCACAO_PONTO';

    protected $fillable = [
        'NORBER_MARCACAO_PONTO',
        'DATA',
        'NOME',
        'MATRICULA',
        'CPF',
        'MARCACOES',
        'PAGINA'
    ];

    public $timestamps = false;
}
