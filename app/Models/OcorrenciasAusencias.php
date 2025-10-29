<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OcorrenciasAusencias extends Model
{
    protected $table = 'RH.NORBER_OCORRENCIAS_AUSENCIAS';

    protected $primaryKey = 'NORBER_OCORRENCIA_AUSENCIA';

    protected $fillable = [
        'NORBER_OCORRENCIA_AUSENCIA',
        'MATRICULA',
        'DATA_OCORRENCIA',
        'INICIO_EXPEDIENTE',
        'FIM_EXPEDIENTE',
        'DESCRICAO',
        'JUSTIFICATIVA',
        'QUANTIDADE_HORAS',
        'PAGINA'
    ];

    public $timestamps = false;
}
