<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TiposAfastamentosFolha extends Model
{
    protected $connection = 'promofarma';
    protected $table = 'TIPOS_AFASTAMENTOS_FOLHA';
    protected $primaryKey = 'TIPO_AFASTAMENTO';
    public $timestamps = false;
}
