<?php

namespace App\Console\Commands\Ocorrencias;

use Carbon\Carbon;
use App\Models\Logs;
use App\Http\Headers;
use GuzzleHttp\Client;
use App\Http\BodyRequisition;
use App\Console\UrlBaseNorber;
use App\Models\HoraExtraDiaria;
use Illuminate\Console\Command;

class RetornaHoraExtraDiaria extends Command
{
    // Modificado: Adicionar opções de data no signature
    protected $signature = 'norber:retornar-hora-extra-diaria 
                            {--start-date= : Data de início (formato: YYYY-MM-DD)}
                            {--end-date= : Data de fim (formato: YYYY-MM-DD)}
                            {--Conceito= : Conceito (formato: inteiro)}
                            {--CodigoExterno= : Código externo (formato: string)}';

    protected $description = "Listar hora extra diaria" . PHP_EOL .
        "Modo de Uso: Data Inicial = (formato: YYYY-MM-DD) | Data Final = (formato: YYYY-MM-DD) | Conceito = (1 para Empresa, 3 para Matrícula) | Codigo Externo= (Número com base no conceito)";


    protected function UrlBaseNorberApi()
    {
        $UrlBaseNorber = new UrlBaseNorber();
        return $UrlBaseNorber->getUrlBaseNorber();
    }

    public function handle()
    {
        // variaveis que serão atribuidas no comando
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $conceito = $this->option('Conceito');
        $codigoExterno = $this->option('CodigoExterno');


        // Validar se as datas foram fornecidas
        if (!$startDate || !$endDate) {
            $this->error('Por favor, forneça ambas as datas: --start-date e --end-date');
            return 1;
        }

        $client = new Client();
        $headers = Headers::getHeaders();
        $url_base = $this->UrlBaseNorberApi();
        $command = 'ocorrencia/RetornaHoraExtraDiaria';

        HoraExtraDiaria::whereBetween('DATA_MARCACAO', [date_format(Carbon::parse($startDate), 'd-m-Y'), date_format(Carbon::parse($endDate), 'd-m-Y')])->delete();


        for ($pagina =  1;; $pagina++) {
            $body = BodyRequisition::getBody($startDate, $endDate, $conceito, $codigoExterno, $pagina);

            try {
                $response = $client->post($url_base . $command, [
                    'headers' => $headers,
                    'body'    => json_encode($body, JSON_UNESCAPED_UNICODE)
                ]);

                $responseContent = $response->getBody()->getContents();
                $data = json_decode($responseContent, true);

                $itens = $data['ListaDeFiltro'] ?? [];
                $resultado = [];
                
                
                foreach ($data['ListaDeFiltro'] as $filtro) {
                   
                   HoraExtraDiaria::UpdateOrCreate([
                         'DATA_MARCACAO' => $filtro['Data'],
                         'MATRICULA'     => $filtro['Matricula'],
                         'CPF'           => $filtro['Cpf'],
                         'QTD_HORA_EXTRA' => $filtro['QtdHoraExtra'],
                   ]);

               }
            
                    Logs::create([
                        'DATA_EXECUCAO' => Carbon::now()->format('d-m-Y H:i:s.v'),
                        'COMANDO_EXECUTADO' =>  $command . ' - ' . json_encode($body),
                        'STATUS_COMANDO' => $response->getStatusCode(),
                        'TOTAL_REGISTROS' => count($itens)
                    ]);

                if (isset($data['TotalPaginas']) && $pagina >= $data['TotalPaginas']) {
                        $this->info('Hora extra diaria cadastrada com sucesso.');
                        return self::SUCCESS;
                 }

            } catch (\Exception $e) {
                  $this->error("Erro ao inserir dados: " . $e->getMessage());
                    return self::FAILURE;
            }
        }

        return 0; // só aqui no final

    }
}
