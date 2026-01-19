<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaReprocessamentos extends Model
{
    protected $table = 'REPROCESSAMENTOS_DESCIDAS_PONTOS_LG';

    protected $primaryKey = 'REPROCESSAMENTO_DESCIDA_PONTO_LG';


    public $fillable = [
                'REPROCESSAMENTO_DESCIDA_PONTO_LG',	
                'FORMULARIO_ORIGEM',
                'TAB_MASTER_ORIGEM',
                'REG_MASTER_ORIGEM',
                'REG_LOG_INCLUSAO',
                'USUARIO_LOGADO',	
                'DATA_HORA',
                'CONCEITO',
                'CODIGO_EXTERNO',
                'PROCESSAR',
                'VALIDADO',
                'DATA_INICIAL',
                'DATA_FINAL'

    ];  

    public $timestamps = false;
}
