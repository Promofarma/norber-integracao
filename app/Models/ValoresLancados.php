<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ValoresLancados extends Model
{
    protected $table = 'RH.LG_COLABORADORES_VALORES_LANCADOS';

    protected $primaryKey = 'LG_COLABORADOR_VALOR_LANCADO';

    protected $fillable = [
        'LG_COLABORADOR_VALOR_LANCADO',
        'ID_FOLHA',
        'CODIGO_EVENTO',
        'REFERENCIA_INICIAL_MES',
        'REFERENCIA_INICIAL_ANO',
        'REFERENCIA_FINAL_MES',
        'REFERENCIA_FINAL_ANO',
        'TIPO_LANCAMENTO',
        'VALOR',
        'MATRICULA',
        'TIPO_COLABORADOR',
    ];

    public $timestamps = false;



    public function getValoresFuturos()
    {
        $mes = now()->month;
        $ano = now()->year;

        $futurosLancamentos = collect(DB::connection('promofarma')->select('EXEC USP_EXPORTA_EVENTOS_FOLHA ?, ?', [$mes, $ano]));

        $jaLancados = self::where('REFERENCIA_INICIAL_MES', $mes)
            ->where('REFERENCIA_INICIAL_ANO', $ano)
            ->get(['MATRICULA', 'CODIGO_EVENTO', 'REFERENCIA_INICIAL_MES', 'REFERENCIA_INICIAL_ANO'])
            ->map(fn($item) => $item->MATRICULA . '|' . $item->CODIGO_EVENTO . '|' . $item->REFERENCIA_INICIAL_MES . '|' . $item->REFERENCIA_INICIAL_ANO);

        $LancamentosPendentes = collect($futurosLancamentos)->filter(function ($lancamento) use ($jaLancados) {
            $chave = $lancamento->MATRICULA . '|' . $lancamento->CODIGO_EVENTO . '|' . $lancamento->MES . '|' . $lancamento->ANO;
            return !$jaLancados->contains($chave);
        })->values();



        return $LancamentosPendentes;
    }
}
