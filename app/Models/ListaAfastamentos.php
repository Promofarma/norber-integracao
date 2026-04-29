<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaAfastamentos extends Model
{
    protected $table =  'RH.LG_HISTORICO_AFASTAMENTOS';

    protected $primaryKey = 'LG_HISTORICO_AFASTAMENTO';


    protected $fillable = [

    'LG_HISTORICO_AFASTAMENTO',
    'DATA_REGISTRO',
    'DATA_OCORRENCIA',
    'EMPRESA',
    'MATRICULA',
    'ACIDENTE_TRAJETO',
    'ACIDENTE_TRANSITO',
    'CID',
    'CID_DESCRICAO',
    'DATA_RETORNO',
    'DATA_FIM',
    'DIAS_AFASTAMENTO',
    'DIAS_AUXILIO_DOENCA',
    'MOTIVO_AFASTAMENTO',
    'OBSERVACAO',
    'SITUACAO_FUNCIONARIO',
    'SITUACAO_FUNCIONARIO_DESCRICAO',
    'TIPO_AFASTAMENTO',
    ];



    public $timestamps = false;
}
