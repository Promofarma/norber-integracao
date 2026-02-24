<?php

namespace App\Console\Commands\ServicosColaborador;

use DOMXPath;
use DOMDocument;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Http\LGheaders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\FinanceiroColaboradores;

class RetornaSalariosDemitidos extends Command
{
    protected $signature = 'lg:consultar-demitidos-salario {--Pagina=} {--Empresa=} ';
    protected $description = 'Consulta colaboradores desligados na API LG via SOAP';

    protected $pagina;
    protected $empresa;
    protected $mes;
    protected $ano;

    public function handle()
    {
        $this->pagina = $this->option('Pagina');
        $this->empresa = $this->option('Empresa');

   

        $matriculas = DB::connection('promofarma')
            ->table('dbo.lg_importa_funcionarios_demitidos')
            ->where('EMPRESA', $this->empresa)
            ->orderBy('MATRICULA')
            ->select(
                'MATRICULA',
                'DATA_ADMISSAO',
                DB::RAW('MAX(DATA_RESCISAO) AS DATA_RESCISAO'),
                DB::RAW('MONTH(MAX(DATA_RESCISAO)) AS MES'),
                DB::RAW('YEAR(MAX(DATA_RESCISAO)) AS ANO'),
            )
            ->groupBy('MATRICULA', 'DATA_ADMISSAO')
            ->where('DATA_RESCISAO', '>=', '2025-01-01')
            ->orderBy('DATA_RESCISAO', 'asc')
            ->get();

        $totalMatriculas = count($matriculas);
        $this->info("Total de matrículas encontradas: {$totalMatriculas}");

        foreach ($matriculas as $matricula) {
            $this->info("\nProcessando matrícula: {$matricula->MATRICULA}");
            $this->mes = $matricula->MES;
            $this->ano = $matricula->ANO;

            $exists =  DB::connection('sqlsrv')
                        ->table('RH.LG_COLABORADORES_FINANCEIROS')
                        ->where('MATRICULA', $matricula->MATRICULA)
                        ->where('MES', $matricula->MES)
                        ->where('ANO', $matricula->ANO)
                        ->where('TIPO_PAGINA', $this->pagina)
                        ->exists();

            if ($exists) {
                 $this->info("\n Matricula: {$matricula->MATRICULA} já estava processada com sucesso.");
                continue;
            } else {

             $this->buscarFuncionarios($matricula->MATRICULA, $matricula->MES, $matricula->ANO);
             $this->info("\n Matricula: {$matricula->MATRICULA} processada com sucesso.");
            sleep(1);

            }           
          
        }
        $this->info("\nProcesso concluído.");
    }



    public function buscarFuncionarios($matricula, $mes, $ano)
    {
        $endpoint = 'https://prd-api1.lg.com.br/v1/servicoderecibodepagamento';
        $headers = (new LGheaders())->getHeaders();

        $soapBody = <<<XML
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dto="lg.com.br/svc/dto" xmlns:v1="lg.com.br/api/v1" xmlns:v11="lg.com.br/api/dto/v1">
            {$headers}
                <soapenv:Body>
                    <v1:ConsultarReciboDePagamentoDetalhado>
                        <v1:filtro>
                            <v11:Colaborador>
                                <v11:Empresa>
                                    <v11:Codigo>{$this->empresa}</v11:Codigo>
                                </v11:Empresa>
                                <v11:Matricula>{$matricula}</v11:Matricula>
                            </v11:Colaborador>
                            <v11:FolhaDePagamentoCodigo>{$this->pagina}</v11:FolhaDePagamentoCodigo>
                            <v11:Referencia>
                                <v11:Ano>{$ano}</v11:Ano>
                                <v11:Mes>{$mes}</v11:Mes>
                            </v11:Referencia>
                        </v1:filtro>
                    </v1:ConsultarReciboDePagamentoDetalhado>
                </soapenv:Body>
            </soapenv:Envelope>
        XML;

        $client = new Client();

        try {
            $response = $client->post($endpoint, [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '"lg.com.br/api/v1/ServicoDeReciboDePagamento/ConsultarReciboDePagamentoDetalhado"'
                ],
                'body' => $soapBody,
                'verify' => false,
                'timeout' => 90
            ]);

            $body = $response->getBody()->getContents();



            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadXML($body);

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('a', 'lg.com.br/api/dto/v1');

            $matriculaNode = $xpath->query('//a:Matricula')->item(0)->nodeValue ?? '';
            $nomeNode = $xpath->query('//a:Nome')->item(0)->nodeValue ?? '';

            if (empty($matriculaNode) || empty($nomeNode)) return;

            $eventos = $xpath->query('//a:EventoCalculado');
            $resultados = [];
            $registrosInseridos = 0;

            foreach ($eventos as $evento) {
                $descricao = $xpath->query('a:Descricao', $evento)->item(0)->nodeValue ?? '';
                $valor = $xpath->query('a:Valor', $evento)->item(0)->nodeValue ?? '';
                $codigo = $xpath->query('a:Codigo', $evento)->item(0)->nodeValue ?? '';

                if (empty($descricao) || empty($valor)) continue;

                $chaveUnica = "{$matriculaNode}_{$descricao}_{$mes}_{$ano}";
                $resultados[$chaveUnica] = [
                    'descricao' => $descricao,
                    'valor' => $valor,
                    'codigo_evento' => $codigo,
                ];
            }


            foreach ($resultados as $resultado) {
                $registro = FinanceiroColaboradores::updateOrCreate(
                    [
                        'MATRICULA' => $matriculaNode,
                        'DESCRICAO' => $resultado['descricao'],
                        'MES' => $mes,
                        'ANO' => $ano
                    ],
                    [
                        'NOME' => $nomeNode,
                        'VALOR' => $resultado['valor'],
                        'CODIGO_EVENTO' => $resultado['codigo_evento'],
                        'DATA_REGISTRO' => now()->format('d-m-Y'),
                        'EMPRESA' => $this->empresa,
                        'TIPO_PAGINA' => $this->pagina
                    ]
                );

                if ($registro->wasRecentlyCreated) {
                    $registrosInseridos++;
                }
            }

            if ($registrosInseridos > 0) {
                $this->info(" [Matrícula {$matriculaNode} - {$mes}/{$ano}: {$registrosInseridos} registros]");
            }
        } catch (\Throwable $e) {
            $this->error("Erro para matrícula {$matricula}: " . $e->getMessage());
        }
    }
}
