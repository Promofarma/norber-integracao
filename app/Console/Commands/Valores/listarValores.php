<?php

namespace App\Console\Commands\Valores;

use App\Http\LGheaders;
use App\Models\ValoresLancados;
use App\Models\Logs;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class listarValores extends Command
{
    protected $signature = 'lg:retorna-valores {--Empresa= : Empresa (formato: inteiro)}';

    protected $description = 'Retorna os valores da folha dos colaboradores via API LG';

    protected $empresa;

    public function handle()
    {
        $this->empresa = $this->option('Empresa');

        $matriculas = DB::connection('promofarma')
            ->table('dbo.LG_IMPORTA_FUNCIONARIOS')
            ->where('EMPRESA', $this->empresa)
            ->where('MATRICULA', 8255)
            ->orderBy('MATRICULA')
            ->pluck('MATRICULA');

        foreach ($matriculas as $matricula) {
            $this->buscarValores($this->empresa, $matricula);
        }
    }

    public function buscarValores($empresa, $matricula)
    {
        $endpoint = 'https://hml-api1.lg.com.br/v2/servicodelancamentodevalor';
        $headers = (new LGheaders())->getHeadersHomolog();

        $mes = now()->month;
        $ano = now()->year;

        $soapBody = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dto="lg.com.br/svc/dto" xmlns:v2="lg.com.br/api/v2" xmlns:v1="lg.com.br/api/dto/v1">

    {$headers}
   <soapenv:Body>
      <v2:ConsultarListaPorColaborador>
         <v2:filtro>
            <v1:Colaborador>
               <v1:Empresa>
                  <v1:Codigo>{$empresa}</v1:Codigo>
               </v1:Empresa>
               <v1:Matricula>{$matricula}</v1:Matricula>
            </v1:Colaborador>
            <v1:Referencia>
               <v1:Ano>{$ano}</v1:Ano>
               <v1:Mes>{$mes}</v1:Mes>
            </v1:Referencia>
         </v2:filtro>
      </v2:ConsultarListaPorColaborador>
   </soapenv:Body>

</soapenv:Envelope>
XML;

        $client = new Client();

        try {
            $response = $client->post($endpoint, [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '"lg.com.br/api/v2/ServicoDeLancamentoDeValor/ConsultarListaPorColaborador"',
                ],
                'body' => $soapBody,
                'verify' => false,
                'timeout' => 30,
            ]);

            $body = $response->getBody()->getContents();

            $body = str_replace('xmlns="lg.com.br/api/v2"', 'xmlns="https://lg.com.br/api/v2"', $body);
            $body = str_replace('xmlns:a="lg.com.br/api/dto/v1"', 'xmlns:a="https://lg.com.br/api/dto/v1"', $body);

            $dom = new DOMDocument();
            $dom->loadXML($body);

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xpath->registerNamespace('v2', 'https://lg.com.br/api/v2');
            $xpath->registerNamespace('a', 'https://lg.com.br/api/dto/v1');

            $lancamentos = $xpath->query('//a:LancamentoDeValorPorColaborador');

            if ($lancamentos->length === 0) {
                return $this->warn("Nenhum lançamento encontrado para matrícula {$matricula}.");
            }

            foreach ($lancamentos as $lancamento) {
                $id               = $xpath->evaluate('string(a:Id)', $lancamento);
                $codigoEvento     = $xpath->evaluate('string(a:Evento/a:Codigo)', $lancamento);
                $refInicialMes    = $xpath->evaluate('string(a:ReferenciaInicial/a:Mes)', $lancamento);
                $refInicialAno    = $xpath->evaluate('string(a:ReferenciaInicial/a:Ano)', $lancamento);
                $refFinalMes      = $xpath->evaluate('string(a:ReferenciaFinal/a:Mes)', $lancamento);
                $refFinalAno      = $xpath->evaluate('string(a:ReferenciaFinal/a:Ano)', $lancamento);
                $tipoDeLancamento = $xpath->evaluate('string(a:TipoDeLancamento)', $lancamento);
                $valor            = $xpath->evaluate('string(a:Valor)', $lancamento);
                $matriculaColab   = $xpath->evaluate('string(a:MatriculaDoColaborador)', $lancamento);
                $tipoColaborador  = $xpath->evaluate('string(a:TipoColaborador)', $lancamento);

                ValoresLancados::updateOrCreate(
                    [
                        'ID_FOLHA'      => $id,
                        'CODIGO_EVENTO' => $codigoEvento,
                        'MATRICULA'     => (int) $matriculaColab,
                    ],
                    [
                        'REFERENCIA_INICIAL_MES' => (int) $refInicialMes,
                        'REFERENCIA_INICIAL_ANO' => (int) $refInicialAno,
                        'REFERENCIA_FINAL_MES'   => (int) $refFinalMes,
                        'REFERENCIA_FINAL_ANO'   => (int) $refFinalAno,
                        'TIPO_LANCAMENTO'        => (int) $tipoDeLancamento,
                        'VALOR'                  => (float) $valor,
                        'TIPO_COLABORADOR'       => (int) $tipoColaborador,
                    ]
                );

                Logs::create([
                    'DATA_EXECUCAO'     => Carbon::now()->format('d-m-Y H:i:s.v'),
                    'COMANDO_EXECUTADO' => json_encode([
                        $this->signature,
                        (int) $matriculaColab,
                        $codigoEvento,
                        $id,
                    ]),
                    'STATUS_COMANDO'  => $response->getStatusCode(),
                    'TOTAL_REGISTROS' => 1,
                ]);
            }

            return $this->info("Valores atualizados com sucesso! ({$lancamentos->length} registros para matrícula {$matricula})");
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->error('Erro ao buscar valores: ' . $e->getMessage());
        }
    }
}
