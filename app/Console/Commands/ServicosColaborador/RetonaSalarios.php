<?php

namespace App\Console\Commands\ServicosColaborador;

use DOMXPath;
use DOMDocument;
use GuzzleHttp\Client;
use App\Http\LGheaders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\FinanceiroColaboradores;
use Termwind\Components\Dd;

class RetonaSalarios extends Command
{
    protected $signature = 'lg:consultar-salario';
    protected $description = 'Consulta um colaborador na API LG via SOAP';

    public function handle()
    {
        $matriculas = DB::connection('promofarma')
            ->table('dbo.LG_IMPORTA_FUNCIONARIOS')
            ->select('MATRICULA')
            ->whereIn('MATRICULA', [3082, 6548])
            ->get();



        foreach ($matriculas as $matricula) {
            $this->info("Buscando dados para a matrícula: {$matricula->MATRICULA}");
            $this->buscarFuncionarios($matricula->MATRICULA);
        }

        $this->info('Processo concluído.');
    }

    public function buscarFuncionarios($matricula)
    {

        $endpoint = 'https://prd-api1.lg.com.br/v1/servicoderecibodepagamento';

        $headers = new LGheaders();
        $headers = $headers->getHeaders();

        $anoAtual = date('Y');
        $mesAtual = date('m') - 1; // Mês anterior

        $soapBody = <<<XML
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dto="lg.com.br/svc/dto" xmlns:v1="lg.com.br/api/v1" xmlns:v11="lg.com.br/api/dto/v1">
            {$headers}
                <soapenv:Body>
                    <v1:ConsultarReciboDePagamentoDetalhado>
                        <v1:filtro>
                        <v11:Colaborador>
                        <v11:Empresa>
                        <v11:Codigo>8</v11:Codigo>
                        </v11:Empresa>
                        <v11:Matricula>$matricula</v11:Matricula>
                        </v11:Colaborador>
                        <v11:FolhaDePagamentoCodigo>1</v11:FolhaDePagamentoCodigo>
                        <v11:Referencia>
                        <v11:Ano>$anoAtual</v11:Ano>
                        <v11:Mes>$mesAtual</v11:Mes>
                        </v11:Referencia>
                    </v1:filtro>
                </v1:ConsultarReciboDePagamentoDetalhado>
                </soapenv:Body>
            </soapenv:Envelope>
            XML;

        $client = new Client();

        try {

            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => '"lg.com.br/api/v1/ServicoDeReciboDePagamento/ConsultarReciboDePagamentoDetalhado"'
            ];

            $response = $client->post($endpoint, [
                'headers' => $headers,
                'body' => $soapBody,
                'verify' => false,
                'timeout' => 30
            ]);

            $body = $response->getBody()->getContents();

            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadXML($body);

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xpath->registerNamespace('v1', 'lg.com.br/api/v1');
            $xpath->registerNamespace('a',  'lg.com.br/api/dto/v1');

            $matricula = $xpath->query('//a:Matricula')->item(0)?->nodeValue ?? '';
            $nome      = $xpath->query('//a:Nome')->item(0)?->nodeValue ?? '';

            $eventos = $xpath->query('//a:EventoCalculado');

            $resultados = [];

            foreach ($eventos as $evento) {
                $descricao = $xpath->query('a:Descricao', $evento)->item(0)?->nodeValue ?? '';
                $valor     = $xpath->query('a:Valor',     $evento)->item(0)?->nodeValue ?? '';
                $codigo    = $xpath->query('a:Codigo',    $evento)->item(0)?->nodeValue ?? '';

                $resultados[$descricao] = [
                    'descricao' => $descricao,
                    'valor'     => $valor,
                    'codigo_evento'  => $codigo,
                ];
            }

            $resultados = array_values($resultados);

            try {
                foreach ($resultados as $resultado) {
                    FinanceiroColaboradores::updateOrCreate(
                        [
                            'MATRICULA' => $matricula,
                            'DESCRICAO' => $resultado['descricao'],
                            'DATA_REGISTRO' => date('Y-m-d'),
                        ],
                        [
                            'NOME'  => $nome,
                            'VALOR' => $resultado['valor'],
                            'CODIGO_EVENTO' => $resultado['codigo_evento'],
                        ]
                    );
                }
            } catch (\Throwable $th) {
                echo $th->getMessage();
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->error('ERRO NA REQUISIÇÃO: ' . $e->getMessage());

            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $this->error('STATUS: ' . $response->getStatusCode());
                $this->error('RESPOSTA DO SERVIDOR:');
                $this->error($response->getBody()->getContents());
            }
        }
    }
}
