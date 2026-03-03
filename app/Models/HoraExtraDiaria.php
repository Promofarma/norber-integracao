<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoraExtraDiaria extends Model
{
    protected $table =  'RH.NORBER_HORAS_EXTRAS_DIARIAS';

    protected $primaryKey = 'NORBER_HORA_EXTRA_DIARIA';


    protected $fillable = [

        'NORBER_HORA_EXTRA_DIARIA',
        'DATA_MARCACAO',
        'MATRICULA',
        'CPF',
        'QTD_HORA_EXTRA'

    ];



    public $timestamps = false;
}
