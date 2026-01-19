<?php 

namespace App\Console\Commands\Reprocessamento;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use App\Models\ListaReprocessamentos;

class ReprocessamentoPontos extends Command{


    protected $signature = 'norber:reprocessamento-ponto';

    protected $description = "Lista de solicitações de reprocessamento de ponto";


public function handle()
{
    $solicitacoes = $this->getSolicitacoesReprocessamento();

    
    foreach ($solicitacoes as $solicitacao) {

        $startDate = date_format(date_create($solicitacao->DATA_INICIAL), 'Y-m-d');
        $endDate   = date_format(date_create($solicitacao->DATA_FINAL), 'Y-m-d');

      
        try {
            $this->executeOcorrencias(
                $startDate,
                $endDate,
                $solicitacao->CONCEITO,
                $solicitacao->CODIGO_EXTERNO
            );

            $this->info(
                "Ocorrências OK | ID: {$solicitacao->REPROCESSAMENTO_DESCIDA_PONTO_LG}"
            );

        } catch (\Throwable $e) {
            $this->error(
                "Erro em Ocorrências | ID: {$solicitacao->REPROCESSAMENTO_DESCIDA_PONTO_LG} | {$e->getMessage()}"
            );
        }

        
        try {
            $this->executePonto(
                $startDate,
                $endDate,
                $solicitacao->CONCEITO,
                $solicitacao->CODIGO_EXTERNO
            );

            $this->info(
                "Ponto OK | ID: {$solicitacao->REPROCESSAMENTO_DESCIDA_PONTO_LG}"
            );

         ListaReprocessamentos::on('promofarma')->where('REPROCESSAMENTO_DESCIDA_PONTO_LG', $solicitacao->REPROCESSAMENTO_DESCIDA_PONTO_LG)
                ->update(['VALIDADO' => 'S']);

        } catch (\Throwable $e) {
            $this->error(
                "Erro em Ponto | ID: {$solicitacao->REPROCESSAMENTO_DESCIDA_PONTO_LG} | {$e->getMessage()}"
            );
        }
    }
}

public function executeOcorrencias($startDate, $endDate, $conceito, $codigoExterno)
{
    return Artisan::call('norber:retornar-ocorrencia-ausencia', [
        '--start-date'     => $startDate,
        '--end-date'       => $endDate,
        '--Conceito'       => $conceito,
        '--CodigoExterno'  => $codigoExterno,
    ]);
}

public function executePonto($startDate, $endDate, $conceito, $codigoExterno)
{
    return Artisan::call('norber:retornar-marcacoes', [
        '--start-date'     => $startDate,
        '--end-date'       => $endDate,
        '--Conceito'       => $conceito,
        '--CodigoExterno'  => $codigoExterno,
    ]);
}



public function getSolicitacoesReprocessamento()
    {
        return ListaReprocessamentos::on('promofarma')
             ->where('PROCESSAR', 'S')
            ->where('VALIDADO', 'N')
            ->select('REPROCESSAMENTO_DESCIDA_PONTO_LG','DATA_INICIAL', 'DATA_FINAL', 'CONCEITO', 'CODIGO_EXTERNO')
            ->get();
    }


}