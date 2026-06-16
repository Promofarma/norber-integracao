<?php

namespace App\Models;

use App\Models\ListaAfastamentos;
use App\Models\MotivosAfastamentoFolha;
use App\Models\TiposAfastamentosFolha;
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
        'NOME',
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
        'ACIDENTE_TRAJETO',
        'CID_FOLHA',
        'DIAS_AUXILIO_DOENCA',
        'MOTIVO_AFASTAMENTO_FOLHA',
        'SITUACAO_CONTRATUAL',
        'OBSERVACAO',
        'PROCESSAR',
        'ENVIADO_FOLHA',
    ];

    public $timestamps = false;

    public function tipoAfastamento()
    {
        return $this->belongsTo(TiposAfastamentosFolha::class, 'TIPO_AFASTAMENTO', 'TIPO_AFASTAMENTO');
    }

    public function motivoAfastamento()
    {
        return $this->belongsTo(MotivosAfastamentoFolha::class, 'MOTIVO_AFASTAMENTO_FOLHA', 'MOTIVO_AFASTAMENTO_FOLHA');
    }

    public function getLancamentosAfastamentos(array $filters = [])
    {
        $afastamentos = ListaAfastamentos::select('MATRICULA', 'EMPRESA', 'DATA_OCORRENCIA')->get();

        $chaves = $afastamentos->map(fn($a) => $a->MATRICULA . '|' . $a->EMPRESA . '|' . $a->DATA_OCORRENCIA);

        $query = self::select([
            'A.LANCAMENTO_ATESTADO_LG',
            'A.FORMULARIO_ORIGEM',
            'A.TAB_MASTER_ORIGEM',
            'A.REG_MASTER_ORIGEM',
            'A.REG_LOG_INCLUSAO',
            'A.DATA_HORA',
            'A.USUARIO_LOGADO',
            'A.NOME',
            'A.MATRICULA',
            'A.EMPRESA',
            'B.CODIGO AS TIPO_AFASTAMENTO',
            'A.CODIGO_AFASTAMENTO',
            'A.PARAMETRIZACAO_MOTIVO_AFASTAMENTO_FOLHA',
            'A.DATA_OCORRENCIA',
            'A.DIAS_AFASTAMENTO',
            'A.DATA_FIM',
            'A.SEM_PREVISAO_RETORNO',
            'A.DOENCA_RELACIONADA_TRABALHO',
            'A.ACIDENTE_TRAJETO',
            'A.CID_FOLHA',
            'A.DIAS_AUXILIO_DOENCA',
            'C.CODIGO AS MOTIVO_AFASTAMENTO_FOLHA',
            'A.SITUACAO_CONTRATUAL',
            'A.OBSERVACAO',
            'A.PROCESSAR',
            'A.ENVIADO_FOLHA',
        ])
            ->from('LANCAMENTOS_ATESTADOS_LG AS A')
            ->leftJoin('TIPOS_AFASTAMENTOS_FOLHA AS B', 'A.TIPO_AFASTAMENTO', '=', 'B.TIPO_AFASTAMENTO')
            ->leftJoin('MOTIVOS_AFASTAMENTO_FOLHA AS C', 'A.MOTIVO_AFASTAMENTO_FOLHA', '=', 'C.MOTIVO_AFASTAMENTO_FOLHA')
            ->where('A.PROCESSAR', 'S')
            ->where(function ($query) {
                $query->whereNull('A.ENVIADO_FOLHA')->orWhere('A.ENVIADO_FOLHA', 'N');
            });

        foreach ($filters as $column => $value) {
            $query->where("A.{$column}", $value);
        }

        return $query->get()
            ->filter(function ($lancamento) use ($chaves) {
                $chave = $lancamento->MATRICULA . '|' . $lancamento->EMPRESA . '|' . $lancamento->DATA_OCORRENCIA;
                return !$chaves->contains($chave);
            })
            ->values();
    }
}
