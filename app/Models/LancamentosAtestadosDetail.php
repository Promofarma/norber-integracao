<?php

namespace App\Models;

use App\Models\ListaAfastamentos;
use App\Models\LancamentosAtestados;
use Illuminate\Database\Eloquent\Model;

class LancamentosAtestadosDetail extends Model
{
    protected $connection = 'promofarma';

    protected $table = 'LANCAMENTOS_ATESTADOS_LG_DETAIL';

    protected $primaryKey = 'LANCAMENTO_ATESTADO_LG_DETAIL';

   protected $fillable = [
                'LANCAMENTO_ATESTADO_LG_DETAIL',
                'FORMULARIO_ORIGEM',
                'TAB_MASTER_ORIGEM',
                'REG_MASTER_ORIGEM',
                'REG_LOG_INCLUSAO',
                'LANCAMENTO_ATESTADO_LG',
                'CID_FOLHA',
                'DATA_INICIO',
                'DATA_RETORNO',
                'DIAS_ATESTADO',
                'SEM_PREVISAO_RETORNO',
                'SEQUENCIA',
                'INSCRICAO_ORGAO_CLASSE',
                'NOME_EMITENTE',
                'ORGAO_EMISSOR',
                'UF',
            ];

    public $timestamps = false;


    public function master()
    {
        return $this->belongsTo(LancamentosAtestados::class, 'LANCAMENTO_ATESTADO_LG', 'LANCAMENTO_ATESTADO_LG');
    }


}
