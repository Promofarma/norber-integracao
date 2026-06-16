<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivosAfastamentoFolha extends Model
{
    protected $connection = 'promofarma';
    protected $table = 'MOTIVOS_AFASTAMENTO_FOLHA';
    protected $primaryKey = 'MOTIVO_AFASTAMENTO_FOLHA';
    public $timestamps = false;
}
