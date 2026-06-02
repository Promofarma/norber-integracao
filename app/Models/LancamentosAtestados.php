<?php

namespace App\Models;

use App\Models\ListaAfastamentos;
use Illuminate\Database\Eloquent\Model;

class LancamentosAtestados extends Model
{
    protected $connection = 'promofarma';

    protected $table = 'LANCAMENTOS_ATESTADOS_LG';

    protected $primaryKey = 'LANCAMENTO_ATESTADO_LG';

    protected $fillable = [
        'LANCAMENTO_ATESTADO_LG',
        'DATA_HORA',
        'USUARIO_LOGADO',
        'ENTIDADE',
        'MATRICULA',
        'EMPRESA',
        'TIPO_AFASTAMENTO',
        'CODIGO_AFASTAMENTO',
        'PARAMETRIZACAO_MOTIVO_AFASTAMENTO_FOLHA',
        'DATA_OCORRENCIA',
        'DATA_INICIAL',
        'DIAS_AFASTAMENTO',
        'DATA_FIM',
        'SEM_PREVISAO_RETORNO',
        'DOENCA_RELACIONADA_TRABALHO',
        'CID_FOLHA',
        'DIAS_AUXILIO_DOENCA',
        'MOTIVO_AFASTAMENTO_FOLHA',
        'SITUACAO_CONTRATUAL',
        'OBSERVACAO',
        'PROCESSAR',
        'ENVIADO_FOLHA',
    ];

    public $timestamps = false;

    public function getLancamentosAfastamentos()
    {
        $afastamentos = ListaAfastamentos::select('MATRICULA', 'EMPRESA', 'DATA_OCORRENCIA')->get();

        $chaves = $afastamentos->map(fn($a) => $a->MATRICULA . '|' . $a->EMPRESA . '|' . $a->DATA_OCORRENCIA);


        return self::where('PROCESSAR', 'S')
            ->where(function ($query) {
                $query->whereNull('ENVIADO_FOLHA')->orWhere('ENVIADO_FOLHA', 'N');
            })
            ->get()
            ->filter(function ($lancamento) use ($chaves) {
                $chave = $lancamento->MATRICULA . '|' . $lancamento->EMPRESA . '|' . $lancamento->DATA_OCORRENCIA;
                return !$chaves->contains($chave);
            })
            ->values();
    }
}
