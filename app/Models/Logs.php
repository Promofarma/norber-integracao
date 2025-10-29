<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Logs extends Model
{
    protected $table = 'RH.NORBER_LOG_COMANDOS';

    protected $primaryKey = 'NORBER_LOG_COMANDO';

    protected $fillable = [
        'NORBER_LOG_COMANDO',
        'DATA_EXECUCAO',
        'COMANDO_EXECUTADO',
        'STATUS_COMANDO',
        'TOTAL_REGISTROS'
    ];

    public $timestamps = false;
}
