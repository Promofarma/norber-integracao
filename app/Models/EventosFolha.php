<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventosFolha extends Model
{
    protected $table = 'CODIGOS_EVENTOS_FOLHAS';

    protected $primaryKey = 'CODIGO_EVENTO_FOLHA';

    protected $fillable = [
        'CODIGO_EVENTO_FOLHA',
        'CODIGO',
        'DESCRICAO',
        'TIPO_EVENTO',
        'DESCRICAO_EVENTO'
    ];

    public $timestamps = false;
}
