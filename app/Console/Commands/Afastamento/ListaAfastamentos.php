<?php

namespace App\Console\Commands\Afastamento;

use App\Http\LGheaders;
use App\Models\ListaAfastamentos as ListaAfastamentosModel;
use App\Models\Logs;
use Carbon\Carbon;
use DateError;
use DateTime;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ListaAfastamentos extends Command
{
    protected $signature = 'lg:retorna-afastamentos {--Empresa= : Empresa (formato: inteiro)}';

    protected $description = 'Retorna os afastamentos da folha de pagamento dos colaboradores via API LG';

    protected $empresa;
    protected $matricula;

    public function handle()
    {
        $this->empresa = $this->option('Empresa');
        

        $matriculas = DB::connection('promofarma')
            ->table('dbo.LG_IMPORTA_FUNCIONARIOS')
            ->where('EMPRESA', $this->empresa)
          
            ->orderBy('MATRICULA')
            ->pluck('MATRICULA');

    foreach ($matriculas as $matricula) {
        $this->buscaAfastamentos($this->empresa, $matricula);
    }
  
       
    }

    public function buscaAfastamentos($empresa, $matricula)
    {
        $endpoint = 'https://prd-api1.lg.com.br/v1/ServicoDeAfastamento';
        $headers = (new LGheaders())->getHeaders();
        $dataFinal = new DateTime();
        $dataInicial = clone $dataFinal;


        $dataFinal = (string) $dataFinal->format('Y-m-d');
        $dataInicial = (string) $dataInicial->modify('-45 days')->format('Y-m-d');

      


       

        $soapBody = <<<XML
  <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" 
                    xmlns:dto="lg.com.br/svc/dto" 
                    xmlns:v1="lg.com.br/api/v1" 
                    xmlns:v11="lg.com.br/api/dto/v1" 
                    xmlns:arr="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
    {$headers}
    <soapenv:Body>
      <v1:ConsultarListaParaVariosContratos>
         <v1:filtro>
            <v11:CodigoDaEmpresa>{$empresa}</v11:CodigoDaEmpresa>
            <v11:ListaDeMatriculas>
               <arr:string>{$matricula}</arr:string>
            </v11:ListaDeMatriculas>
             <v1:Periodo>
               <v1:DataInicio>{$dataInicial}</v1:DataInicio>
               <v1:DataFim>{$dataFinal}</v1:DataFim>
            </v1:Periodo>
         </v1:filtro>
      </v1:ConsultarListaParaVariosContratos>
   </soapenv:Body>

</soapenv:Envelope>
XML;

        $client = new Client();

        try {
            $response = $client->post($endpoint, [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '"lg.com.br/api/v1/ServicoDeAfastamento/ConsultarListaParaVariosContratos"',
                ],
                'body' => $soapBody,
                'verify' => false,
                'timeout' => 30,
            ]);

            $body = $response->getBody()->getContents();
            $body = str_replace('xmlns="lg.com.br/api/v1"', 'xmlns="https://lg.com.br/api/v1"', $body);
            $body = str_replace('xmlns:a="lg.com.br/api/dto/v1"', 'xmlns:a="https://lg.com.br/api/dto/v1"', $body);
            $dom = new DOMDocument();
            $dom->loadXML($body);

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('s', 'http://schemas.xmlsoap.org/soap/envelope/');
            $xpath->registerNamespace('v1', 'https://lg.com.br/api/v1');
            $xpath->registerNamespace('a', 'https://lg.com.br/api/dto/v1');

            $afastamentos = $xpath->query('//a:Afastamento');

            $resultados = [];

        foreach ($afastamentos as $afastamento) {
            $DataDaOcorrencia = $xpath->evaluate('string(a:DataDaOcorrencia)', $afastamento);
            $Empresa = $xpath->evaluate('string(a:IdentificacaoDeContrato/a:Empresa/a:Codigo)', $afastamento);
            $Matricula = $xpath->evaluate('string(a:IdentificacaoDeContrato/a:Matricula)', $afastamento);
            $AcidenteDeTrajeto = $xpath->evaluate('string(a:AcidenteDeTrajeto)', $afastamento);
            $AcidenteDeTransito = $xpath->evaluate('string(a:AcidenteDeTransito)', $afastamento);
            $Cid = $xpath->evaluate('string(a:Cid/a:Codigo)', $afastamento);
            $CidDescricao = $xpath->evaluate('string(a:Cid/a:DescricaoCompleta)', $afastamento);
            $DataDoRetorno = $xpath->evaluate('string(a:DataDoRetorno)', $afastamento);
            $DataFim = $xpath->evaluate('string(a:DataFim)', $afastamento);
            $DiasAfastamento = $xpath->evaluate('string(a:DiasAfastamento)', $afastamento);
            $DiasAuxilioDoenca = $xpath->evaluate('string(a:DiasAuxilioDoenca)', $afastamento);
            $Motivo = $xpath->evaluate('string(a:Motivo)', $afastamento);
            $Observacao = $xpath->evaluate('string(a:Observacao)', $afastamento); 
            $SituacaoDoFuncionario = $xpath->evaluate('string(a:SituacaoDoFuncionario/a:Codigo)', $afastamento);
            $SituacaoDoFuncionarioDescricao = $xpath->evaluate('string(a:SituacaoDoFuncionario/a:Descricao)', $afastamento);
            $TipoDeAfastamento = $xpath->evaluate('string(a:TipoDeAfastamento)', $afastamento);

            $resultados[] = [
                'DataDaOcorrencia' => $DataDaOcorrencia,
                'Empresa' => $Empresa,
                'Matricula' => $Matricula,
                'AcidenteDeTrajeto' => $AcidenteDeTrajeto,
                'AcidenteDeTransito' => $AcidenteDeTransito,
                'Cid' => $Cid,
                'CidDescricao' => $CidDescricao,
                'DataDoRetorno' => $DataDoRetorno,
                'DataFim' => $DataFim,
                'DiasAfastamento' => $DiasAfastamento,
                'DiasAuxilioDoenca' => $DiasAuxilioDoenca,
                'Motivo' => $Motivo,
                'Observacao' => $Observacao,
                'SituacaoDoFuncionario' => $SituacaoDoFuncionario,
                'SituacaoDoFuncionarioDescricao' => $SituacaoDoFuncionarioDescricao,
                'TipoDeAfastamento' => $TipoDeAfastamento,
            ];
        }
            foreach ($resultados as $resultado) {
                    ListaAfastamentosModel::updateOrCreate(
                        [
                            'MATRICULA'                      => $resultado['Matricula'],
                            'DATA_OCORRENCIA'                => $resultado['DataDaOcorrencia'],

                        ],    
                        [
                        'DATA_REGISTRO'                  => date('d-m-Y H:i:s'),
                        'EMPRESA'                        => $resultado['Empresa'],
                        'ACIDENTE_TRAJETO'               => $resultado['AcidenteDeTrajeto'],
                        'ACIDENTE_TRANSITO'              => $resultado['AcidenteDeTransito'],
                        'CID'                            => $resultado['Cid'],
                        'CID_DESCRICAO'                  => $resultado['CidDescricao'],
                        'DATA_RETORNO'                   => $resultado['DataDoRetorno'],
                        'DATA_FIM'                       => $resultado['DataFim'],
                        'DIAS_AFASTAMENTO'               => (int) $resultado['DiasAfastamento'],
                        'DIAS_AUXILIO_DOENCA'            => $resultado['DiasAuxilioDoenca'],
                        'MOTIVO_AFASTAMENTO'             => $resultado['Motivo'],
                        'OBSERVACAO'                     => $resultado['Observacao'],
                        'SITUACAO_FUNCIONARIO'           => $resultado['SituacaoDoFuncionario'],
                        'SITUACAO_FUNCIONARIO_DESCRICAO' => $resultado['SituacaoDoFuncionarioDescricao'],
                        'TIPO_AFASTAMENTO'               => $resultado['TipoDeAfastamento'],
                    ]);

                Logs::create([
                        'DATA_EXECUCAO' => Carbon::now()->format('d-m-Y H:i:s.v'),
                        'COMANDO_EXECUTADO' =>  json_encode([$this->signature, $resultado['Empresa'], $resultado['Matricula'], $resultado['DataDaOcorrencia']]),
                        'STATUS_COMANDO' => $response->getStatusCode(),
                        'TOTAL_REGISTROS' => 1
                    ]);


            }

            return $this->info('Afastamentos atualizados com sucesso!');

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->error('Erro ao buscar afastamentos: ' . $e->getMessage());
        }
    }
}
