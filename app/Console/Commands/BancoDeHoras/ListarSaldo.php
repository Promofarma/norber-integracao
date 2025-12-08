<?php

namespace App\Console\Commands\BancoDeHoras;

use Carbon\Carbon;
use App\Http\Headers;
use GuzzleHttp\Client;
use App\Http\BodyRequisition;
use App\Console\UrlBaseNorber;
use Illuminate\Console\Command;
use App\Models\BancoHorasPeriodo;
use App\Models\Logs; // <-- adicionado

class ListarSaldo extends Command
{
    protected $signature = "norber:listar-saldo  
                            {--MesAnoReferencia= : Data de referência (formato: YYYY-MM)}
                            {--Conceito= : Conceito (formato: inteiro)}
                            {--CodigoExterno= : Código externo (formato: string)}";

    protected $description = "Listar saldo do banco de horas" . PHP_EOL .
        "Modo de Uso: Mes e Ano de Referencia = (formato: YYYY-MM) | Conceito = (1 para Empresa, 3 para Matrícula) | Codigo Externo= (Número com base no conceito)";


    protected function UrlBaseNorberApi()
    {
        return (new UrlBaseNorber())->getUrlBaseNorber();
    }

    public function handle()
    {
        $MesAnoReferencia = $this->option('MesAnoReferencia');
        $conceito         = $this->option('Conceito');
        $codigoExterno    = $this->option('CodigoExterno');

        $client   = new Client();
        $headers  = Headers::getHeaders();
        $url_base = $this->UrlBaseNorberApi();
        $command  = 'banco-de-horas/listar-saldo-v2';

        $ultimaPaginaProcessada = BancoHorasPeriodo::where('MES_ANO_REFERENCIA', $MesAnoReferencia)
        ->max('PAGINA') ?? 0;

        for ($pagina = $ultimaPaginaProcessada + 1;; $pagina++) {
            $body = BodyRequisition::getBodySaldo($MesAnoReferencia, $conceito, $codigoExterno, $pagina);

            try {
                $response = $client->post($url_base . $command, [
                    'headers' => $headers,
                    'body'    => json_encode($body, JSON_UNESCAPED_UNICODE)
                ]);

                $data = json_decode($response->getBody()->getContents(), true);

                // processa e conta registros
                $total = $this->processarPagina($data, $pagina);

                // grava log
                Logs::create([
                    'DATA_EXECUCAO' => Carbon::now()->format('d-m-Y H:i:s.v'),
                    'COMANDO_EXECUTADO' => $command . ' - ' . json_encode($body),
                    'STATUS_COMANDO'   => $response->getStatusCode(),
                    'TOTAL_REGISTROS'  => $total
                ]);

                if ($pagina % 10 === 0) sleep(1);

                if (isset($data['TotalPaginas']) && $pagina >= $data['TotalPaginas'])  {

                    return self::SUCCESS;

                }
                 

            } catch (\GuzzleHttp\Exception\RequestException $e) {
                
            $this->error("Falha na página $pagina: " . $e->getMessage());
                
                return self::FAILURE;
            }
        }
      
    }

    private function processarPagina(array $data, int $pagina): int
    {
        $registros = [];

        foreach ($data['ListaDeFiltro'] as $item) {
            $registros[] = [
                'MATRICULA'          => $item['Matricula'],
                'SALDO_BANCO'        => $item['SaldoBanco'],
                'MES_ANO_REFERENCIA' => $data['MesAnoReferencia'],
                'PAGINA'             => $pagina
            ];
        }

        BancoHorasPeriodo::upsert(
            $registros,
            ['MATRICULA', 'MES_ANO_REFERENCIA'],
            ['SALDO_BANCO', 'PAGINA']
        );

        return count($registros); // retorna total para o log
    }
}
