<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BancoHorasPeriodo extends Model
{
    protected $table = 'RH.NORBER_BANCOS_HORAS_PERIODOS';

    protected $primaryKey = 'NORBER_BANCO_HORA_PERIODO';

    protected $fillable = [
        'NORBER_BANCO_HORA_PERIODO',
        'MES_ANO_REFERENCIA',
        'MATRICULA',
        'SALDO_BANCO',
        'PAGINA'
    ];

    public $timestamps = false;
}
