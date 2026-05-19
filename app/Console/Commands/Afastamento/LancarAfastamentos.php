<?php

namespace App\Console\Commands\Afastamento;

use DOMXPath;
use Carbon\Carbon;
use App\Models\Logs;
use DOMDocument;
use GuzzleHttp\Client;
use App\Http\LGheaders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\LancamentosAtestados as LancamentosAtestadosModel;


class LancarAfastamentos extends Command
{
    protected $signature = 'lg:lancar-afastamentos';
    protected $description = 'Envia para a folha os dados de afastamentos dos colaboradores';

    public function handle()
    {
        $this->LancarAfastamentoFolha();
    }


    public function getAfastamentosProcfit()
    {
        return (new LancamentosAtestadosModel())->getLancamentosAfastamentos();
    }


    public function LancarAfastamentoFolha()
    {

        $client = new Client();
     //   $endpoint = 'https://hml-api1.lg.com.br/v2/servicodeafastamento';
        $endpoint = 'https://prd-api1.lg.com.br/v2/servicodeafastamento';
        $headers = (new LGheaders())->getHeaders();
     // $headers = (new LGheaders())->getHeadersHomolog();

        $afastamentos = $this->getAfastamentosProcfit();

      

      

        foreach ($afastamentos as $afastamento) {

         $dataFimXml = $afastamento->DATA_FIM != null
         ? "<v1:DataFim>{$afastamento->DATA_FIM}</v1:DataFim>"
         : '';   

                    
            $soapBody = <<<XML
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dto="lg.com.br/svc/dto" xmlns:v2="lg.com.br/api/v2" xmlns:v1="lg.com.br/api/dto/v1">

                {$headers}
                <soapenv:Body>
                    <v2:SalvarLista>
                        <v2:listaDeAfastamento>
                            <v1:AfastamentoV2>
                                <v1:DataDaOcorrencia>{$afastamento->DATA_OCORRENCIA}</v1:DataDaOcorrencia>
                                <v1:IdentificacaoDoContrato>
                                    <v1:Empresa>
                                        <v1:Codigo>{$afastamento->EMPRESA}</v1:Codigo>
                                    </v1:Empresa>
                                    <v1:Matricula>{$afastamento->MATRICULA}</v1:Matricula>
                                </v1:IdentificacaoDoContrato>
                                <v1:Cid>
                                    <v1:Codigo>{$afastamento->CID_FOLHA}</v1:Codigo>
                                </v1:Cid>
                                {$dataFimXml}
                                <v1:DiasAfastamento>{$afastamento->DIAS_AFASTAMENTO}</v1:DiasAfastamento>
                                <v1:DiasAuxilioDoenca>{$afastamento->DIAS_AUXILIO_DOENCA}</v1:DiasAuxilioDoenca>
                                <v1:DoencaRelacionadaAoTrabalho>{$afastamento->DOENCA_RELACIONADA_TRABALHO}</v1:DoencaRelacionadaAoTrabalho>
                                <v1:Motivo>{$afastamento->MOTIVO_AFASTAMENTO_FOLHA}</v1:Motivo>
                                <v1:NovaSituacaoDoFuncionario>
                                    <v1:Codigo>{$afastamento->SITUACAO_CONTRATUAL}</v1:Codigo>
                                </v1:NovaSituacaoDoFuncionario>
                                <v1:Observacao>{$afastamento->OBSERVACAO}</v1:Observacao>
                                <v1:SemPrevisaoDeRetorno>{$afastamento->SEM_PREVISAO_RETORNO}</v1:SemPrevisaoDeRetorno>
                                <v1:TipoDeAfastamento>{$afastamento->CODIGO_AFASTAMENTO}</v1:TipoDeAfastamento>
                            </v1:AfastamentoV2>
                        </v2:listaDeAfastamento>
                    </v2:SalvarLista>
                </soapenv:Body>
            </soapenv:Envelope>
            XML;

      
     
            try {
                $response = $client->post($endpoint, [
                    'headers' => [
                        'Content-Type' => 'text/xml; charset=utf-8',
                        'SOAPAction' => 'lg.com.br/api/v2/ServicoDeAfastamento/SalvarLista',
                    ],
                    'body' => $soapBody,
                    'verify' => false,
                ]);


                $body = $response->getBody()->getContents();

             

                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($body);
                libxml_clear_errors();

                $xml->registerXPathNamespace('a', 'lg.com.br/api/dto/v1');
                $xml->registerXPathNamespace('b', 'http://schemas.microsoft.com/2003/10/Serialization/Arrays');

                $tipo = (string) $xml->xpath('//a:ListaDeRetorno//a:Tipo')[0];

                if ($tipo === '1') {
                    $erro = (string) $xml->xpath('//a:ListaDeRetorno//a:Mensagens/b:string')[0];
                    return $this->error("Erro ao lançar afastamento: {$erro}");
                }

                LancamentosAtestadosModel::where('LANCAMENTO_ATESTADO_LG', $afastamento->LANCAMENTO_ATESTADO_LG)
                    ->update(['ENVIADO_FOLHA' => 'S']);

                 $this->info('Afastamento lançado com sucesso!');

            

            } catch (\Throwable $th) {
                $this->error("Erro ao inserir dados: " . $th->getMessage());
                continue;
            }
        }
       
    }
}
