<?php

namespace App\Console\Commands\Valores;

use App\Http\LGheaders;
use App\Models\ValoresLancados as ValoresLancados;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

class LancarValores extends Command
{
    protected $signature = 'lg:lancar-valores';
    protected $description = 'Envia para a folha os dados de valores dos colaboradores';

    public function handle()
    {
        $this->lancarValores();
    }

    public function getValoresFuturos()
    {
        return (new ValoresLancados())->getValoresFuturos();
    }

    public function lancarValores()
    {
        $client = new Client();
        $endpoint = 'https://hml-api1.lg.com.br/v2/servicodelancamentodevalor';
        //  $endpoint = 'https://prd-api1.lg.com.br/v2/servicodeafastamento';S
        // $headers = new LGheaders()->getHeaders();
        $headers = (new LGheaders())->getHeadersHomolog();

        $valoresFuturos = (new ValoresLancados())->getValoresFuturos();

        $valoresFuturos = $valoresFuturos->where('MATRICULA', '8255');



        foreach ($valoresFuturos as $valorFuturo) {

            $soapBody = <<<XML
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dto="lg.com.br/svc/dto" xmlns:v2="lg.com.br/api/v2" xmlns:v1="lg.com.br/api/dto/v1">

            {$headers}
             <soapenv:Body>
            <v2:SalvarPorColaborador>
                <v2:lancamentoDeValorPorColaborador>
                    <v1:Evento>
                    <v1:Codigo>{$valorFuturo->CODIGO_EVENTO}</v1:Codigo>
                    <v1:Empresa>
                        <v1:Codigo>{$valorFuturo->EMPRESA}</v1:Codigo>
                    </v1:Empresa>
                    </v1:Evento>
                    <v1:ParcelaAtual>1</v1:ParcelaAtual>
                    <v1:ParcelaFinal>1</v1:ParcelaFinal>
                    <v1:ReferenciaFinal>
                    <v1:Ano>{$valorFuturo->ANO}</v1:Ano>
                    <v1:Mes>{$valorFuturo->MES}</v1:Mes>
                    </v1:ReferenciaFinal>
                    <v1:ReferenciaInicial>
                    <v1:Ano>{$valorFuturo->ANO}</v1:Ano>
                    <v1:Mes>{$valorFuturo->MES}</v1:Mes>
                    </v1:ReferenciaInicial>
                    <v1:TipoDeLancamento>1</v1:TipoDeLancamento>
                    <v1:Valor>{$valorFuturo->VALOR}</v1:Valor>
                    <v1:VigenciaDaParcela>0{$valorFuturo->MES}</v1:VigenciaDaParcela>
                    <v1:VigenciaDaParcelaFim>0{$valorFuturo->MES}</v1:VigenciaDaParcelaFim>
                    <v1:MatriculaDoColaborador>{$valorFuturo->MATRICULA}</v1:MatriculaDoColaborador>
                    <v1:TipoColaborador>1</v1:TipoColaborador>
                    <v1:ReferenciaParaAtualizacao>
                    <v1:Ano>{$valorFuturo->ANO}</v1:Ano>
                    <v1:Mes>{$valorFuturo->MES}</v1:Mes>
                    </v1:ReferenciaParaAtualizacao>
                </v2:lancamentoDeValorPorColaborador>
            </v2:SalvarPorColaborador>
        </soapenv:Body>
                </soapenv:Envelope>
        XML;




            try {
                $response = $client->post($endpoint, [
                    'headers' => [
                        'Content-Type' => 'text/xml; charset=utf-8',
                        'SOAPAction'   => 'lg.com.br/api/v2/ServicoDeLancamentoDeValor/SalvarPorColaborador',
                    ],
                    'body'   => $soapBody,
                    'verify' => false,
                ]);

                $body = $response->getBody()->getContents();

                libxml_use_internal_errors(true);
                $xml = simplexml_load_string($body);
                libxml_clear_errors();

                $namespaces = $xml->getNamespaces(true);
                $soapBody   = $xml->children($namespaces['s'])->Body;
                $result     = $soapBody->children('lg.com.br/api/v2')
                    ->SalvarPorColaboradorResponse
                    ->SalvarPorColaboradorResult;

                $tipo     = (string) $result->children('lg.com.br/api/dto/v1')->Tipo;
                $operacao = (string) $result->children('lg.com.br/api/dto/v1')->OperacaoExecutada;
                $erro     = (string) $result->children('lg.com.br/api/dto/v1')->CodigoDoErro;
                $mensagem = (string) $result->children('lg.com.br/api/dto/v1')->Mensagens;

                if ($tipo === '1') {
                    $this->error("Erro ao lançar valor: Código {$erro} - {$mensagem}");
                    continue;
                }

                if ($operacao === '3') {
                    $this->warn("Registro já existia na folha: Matrícula {$valorFuturo->MATRICULA} - Evento {$valorFuturo->CODIGO_EVENTO}");
                    continue;
                }

                $this->info("Valor lançado com sucesso! Matrícula {$valorFuturo->MATRICULA} - Evento {$valorFuturo->CODIGO_EVENTO}");
            } catch (\Throwable $th) {
                $this->error('Erro ao inserir dados: ' . $th->getMessage());
                continue;
            }
        }
    }
}
