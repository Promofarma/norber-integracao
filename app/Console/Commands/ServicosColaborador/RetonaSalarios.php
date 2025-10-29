<?php

namespace App\Console\Commands\ServicosColaborador;

use DOMXPath;
use DOMDocument;
use GuzzleHttp\Client;
use App\Http\LGheaders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\FinanceiroColaboradores;
use Carbon\Carbon;

class RetonaSalarios extends Command
{
    protected $signature = 'lg:consultar-salario {--Pagina=} {--Empresa=} {--Mes=} {--Ano=}';
    protected $description = 'Consulta colaboradores na API LG via SOAP';

    protected $pagina;
    protected $empresa;
    protected $mes;
    protected $ano;

    public function handle()
    {
        $this->pagina = $this->option('Pagina');
        $this->empresa = $this->option('Empresa');
        $this->mes = $this->option('Mes');
        $this->ano = $this->option('Ano');

        if (empty($this->mes) || empty($this->ano)) {
            $this->error('É necessário informar Mês e Ano de início');
            return 1;
        }

        $periodos = $this->gerarPeriodos();

        $matriculas = DB::connection('promofarma')
            ->table('dbo.LG_IMPORTA_FUNCIONARIOS')
            ->where('EMPRESA', $this->empresa)
            ->orderBy('MATRICULA')
            ->select('MATRICULA', 'DATA_ADMISSAO')
            ->get();

        $totalMatriculas = count($matriculas);
        $this->info("Total de matrículas encontradas: {$totalMatriculas}");

        $bar = $this->output->createProgressBar($totalMatriculas * count($periodos));
        $bar->start();

        foreach ($matriculas as $matricula) {
            $this->info("\nProcessando matrícula: {$matricula->MATRICULA}");

            foreach ($periodos as $periodo) {

                $dataAdmissao = Carbon::parse($matricula->DATA_ADMISSAO);
                $periodoDate = Carbon::createFromDate($periodo['ano'], $periodo['mes'], 1);

                if ($dataAdmissao->lessThanOrEqualTo($periodoDate->endOfMonth())) {

                    $matriculasFinanceiro = DB::connection('sqlsrv')
                        ->table('RH.LG_COLABORADORES_FINANCEIROS')
                        ->where('mes', $periodo['mes'])
                        ->where('ano', $periodo['ano'])
                        ->distinct()
                        ->pluck('MATRICULA')
                        ->toArray();

                    if (!in_array($matricula->MATRICULA, $matriculasFinanceiro)) {
                        $this->buscarFuncionarios($matricula->MATRICULA, $periodo['mes'], $periodo['ano']);
                        sleep(1);
                    }
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->info("\nProcesso concluído.");
    }

    private function gerarPeriodos(): array
    {
        $periodos = [];
        $dataInicio = Carbon::createFromDate($this->ano, $this->mes, 1);
        $dataFim = Carbon::now();
        $current = $dataInicio->copy();

        while ($current->lessThanOrEqualTo($dataFim)) {
            $periodos[] = [
                'mes' => $current->month,
                'ano' => $current->year
            ];
            $current->addMonth();
        }

        $this->info("Períodos a processar: " . count($periodos));
        foreach ($periodos as $periodo) {
            $this->info(" - {$periodo['mes']}/{$periodo['ano']}");
        }

        return $periodos;
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

            $matriculaNode = $xpath->query('//a:Matricula')->item(0)?->nodeValue ?? '';
            $nomeNode = $xpath->query('//a:Nome')->item(0)?->nodeValue ?? '';

            if (empty($matriculaNode) || empty($nomeNode)) return;

            $eventos = $xpath->query('//a:EventoCalculado');
            $resultados = [];
            $registrosInseridos = 0;

            foreach ($eventos as $evento) {
                $descricao = $xpath->query('a:Descricao', $evento)->item(0)?->nodeValue ?? '';
                $valor = $xpath->query('a:Valor', $evento)->item(0)?->nodeValue ?? '';
                $codigo = $xpath->query('a:Codigo', $evento)->item(0)?->nodeValue ?? '';

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
                        'PAGINA' => $this->pagina
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
