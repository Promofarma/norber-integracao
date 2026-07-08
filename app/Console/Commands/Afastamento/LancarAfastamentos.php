<?php

namespace App\Console\Commands\Afastamento;

use App\Http\LGheaders;
use App\Mail\AfastamentoAlerta;
use App\Models\LancamentosAtestados as LancamentosAtestadosModel;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

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
        $endpoint = 'https://hml-api1.lg.com.br/v2/servicodeafastamento';
        //  $endpoint = 'https://prd-api1.lg.com.br/v2/servicodeafastamento';S
        // $headers = new LGheaders()->getHeaders();
        $headers = (new LGheaders())->getHeadersHomolog();

        $afastamentos = $this->getAfastamentosProcfit();



        $sucessos = [];
        $erros = [];

        foreach ($afastamentos as $afastamento) {

            $dataOcorrencia    = date('Y-m-d', strtotime($afastamento->DATA_OCORRENCIA));
            $dataFim           = $afastamento->DATA_FIM ? date('Y-m-d', strtotime($afastamento->DATA_FIM)) : null;
            $dataFimXml        = $dataFim ? "<v1:DataFim>{$dataFim}</v1:DataFim>" : '';

            $cid               = $afastamento->CID_FOLHA ?? 0;
            $diasAuxilio       = $afastamento->DIAS_AUXILIO_DOENCA ?? 0;
            $doencaTrabalho = ($afastamento->DOENCA_RELACIONADA_TRABALHO ?? 'N') === 'S' ? 1 : 0;
            $semPrevisao       = ($afastamento->SEM_PREVISAO_RETORNO ?? 'N') === 'S' ? 1 : 0;
            $acidenteTrajeto   = ($afastamento->ACIDENTE_TRAJETO ?? 'N') === 'S' ? 1 : 0;

            $atestados = '';

if ($afastamento->details->isNotEmpty()) {

    $xmlAtestados = '';

    foreach ($afastamento->details as $detail) {

        $dataInicio = $detail->DATA_INICIO
            ? date('Y-m-d', strtotime($detail->DATA_INICIO))
            : '';

        $dataRetorno = $detail->DATA_RETORNO
        ? date('Y-m-d', strtotime($detail->DATA_RETORNO))
        : '';

        $dataRetornoXml = $dataRetorno
            ? "<v1:DataRetorno>{$dataRetorno}</v1:DataRetorno>"
            : '';

        $diasAtestadoXml = $detail->DIAS_ATESTADO !== null && $detail->DIAS_ATESTADO !== ''
            ? "<v1:DiasAtestado>{$detail->DIAS_ATESTADO}</v1:DiasAtestado>"
            : '';

        $semPrevisaoRetorno = ($detail->SEM_PREVISAO_RETORNO ?? 'N') === 'S' ? 1 : 0;


        $diasAfastamentoXml = $afastamento->DIAS_AFASTAMENTO !== null && $afastamento->DIAS_AFASTAMENTO !== ''
        ? "<v1:DiasAfastamento>{$afastamento->DIAS_AFASTAMENTO}</v1:DiasAfastamento>"
        : '';

        $xmlAtestados .= <<<XML
                        <v1:AtestadoMedico>
                            <v1:DadosGerais>
                                <v1:Cid>
                                    <v1:Codigo>{$detail->CID_FOLHA}</v1:Codigo>
                                </v1:Cid>
                                <v1:DataInicio>{$dataInicio}</v1:DataInicio>
                                    {$dataRetornoXml}
                                    {$diasAtestadoXml}
                                <v1:SemPrevisaoDeRetorno>{$semPrevisaoRetorno}</v1:SemPrevisaoDeRetorno>
                                <v1:Sequencia>{$detail->SEQUENCIA}</v1:Sequencia>
                            </v1:DadosGerais>
                        </v1:AtestadoMedico>

                        XML;
                            }

                            $atestados = <<<XML
                                    <v1:AtestadosMedicos>
                                    {$xmlAtestados}
                                    </v1:AtestadosMedicos>
                        XML;
                        }


            $soapBody = <<<XML
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dto="lg.com.br/svc/dto" xmlns:v2="lg.com.br/api/v2" xmlns:v1="lg.com.br/api/dto/v1">

            {$headers}
            <soapenv:Body>
                <v2:SalvarLista>
                    <v2:listaDeAfastamento>
                        <v1:AfastamentoV2>
                            <v1:DataDaOcorrencia>{$dataOcorrencia}</v1:DataDaOcorrencia>
                            <v1:IdentificacaoDoContrato>
                                <v1:Empresa>
                                    <v1:Codigo>{$afastamento->EMPRESA}</v1:Codigo>
                                </v1:Empresa>
                                <v1:Matricula>{$afastamento->MATRICULA}</v1:Matricula>
                            </v1:IdentificacaoDoContrato>
                             <v1:AcidenteDeTrajeto>$acidenteTrajeto</v1:AcidenteDeTrajeto>
                                             {$atestados}
                            <v1:Cid>
                                <v1:Codigo>{$cid}</v1:Codigo>
                            </v1:Cid>
                            {$dataFimXml}
                             $diasAfastamentoXml
                            <v1:DiasAuxilioDoenca>{$diasAuxilio}</v1:DiasAuxilioDoenca>
                            <v1:DoencaRelacionadaAoTrabalho>{$doencaTrabalho}</v1:DoencaRelacionadaAoTrabalho>
                            <v1:Motivo>{$afastamento->MOTIVO_AFASTAMENTO_FOLHA}</v1:Motivo>
                            <v1:NovaSituacaoDoFuncionario>
                                <v1:Codigo>{$afastamento->SITUACAO_CONTRATUAL}</v1:Codigo>
                            </v1:NovaSituacaoDoFuncionario>
                            <v1:Observacao>{$afastamento->OBSERVACAO}</v1:Observacao>
                            <v1:SemPrevisaoDeRetorno>{$semPrevisao}</v1:SemPrevisaoDeRetorno>
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
                    $mensagem = (string) $xml->xpath('//a:ListaDeRetorno//a:Mensagens/b:string')[0];
                    $this->error("Erro ao lançar afastamento: {$mensagem}");
                    $erros[] = "Matrícula {$afastamento->MATRICULA} / Empresa {$afastamento->EMPRESA} / {$dataOcorrencia}: {$mensagem}";
                    continue;
                }

                LancamentosAtestadosModel::where(
                    'LANCAMENTO_ATESTADO_LG',
                    $afastamento->LANCAMENTO_ATESTADO_LG,
                )->update(['ENVIADO_FOLHA' => 'S']);

                $this->info('Afastamento lançado com sucesso!');
                $sucessos[] = [
                    'matricula'       => $afastamento->MATRICULA,
                    'empresa'         => $afastamento->EMPRESA,
                    'data_ocorrencia' => $dataOcorrencia,
                ];
            } catch (\Throwable $th) {


                $this->error('Erro ao inserir dados: ' . $th->getMessage());
                $erros[] = "Matrícula {$afastamento->MATRICULA} / Empresa {$afastamento->EMPRESA} / {$dataOcorrencia}: " . $th->getMessage();
                continue;
            }
        }

         if (!empty($sucessos) || !empty($erros)) {
            Mail::to(['viktor.santos@promofarma.com.br'])->send(new AfastamentoAlerta($sucessos, $erros));
        }
    }
}
