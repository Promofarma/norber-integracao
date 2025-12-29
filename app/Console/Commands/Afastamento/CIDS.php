<?php

namespace App\Console\Commands\Afastamento;

use DOMXPath;
use DOMDocument;
use GuzzleHttp\Client;
use App\Http\LGheaders;
use Illuminate\Console\Command;
use App\Models\CIDS as cidsmodel;

class CIDS extends Command
{
    protected $signature = 'lg:cids';
    protected $description = 'Retorna os cids parametrizados na folha via API LG';

    public function handle()
    {
        $this->buscaCids();
    }

    public function buscaCids()
    {

        $endpoint = 'https://prd-api1.lg.com.br/v2/ServicoDeAfastamento';

        $headers = new LGheaders();
        $headers = $headers->getHeaders();

        $soapBody = <<<XML
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dto="lg.com.br/svc/dto" xmlns:v2="lg.com.br/api/v2" xmlns:v11="lg.com.br/api/dto/v1">
            {$headers}
                <soapenv:Body>
                        <v2:ConsultarListaDeCIDs>
                            <v2:filtro>
                            </v2:filtro>
                        </v2:ConsultarListaDeCIDs>
                </soapenv:Body>
            </soapenv:Envelope>
            XML;


        $client = new Client();




        try {

            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => '"lg.com.br/api/v2/ServicoDeAfastamento/ConsultarListaDeCIDs"'
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

            $eventos = $xpath->query('//a:Cid');

            $resultados = [];

              foreach ($eventos as $nodoEvento) {
                $codigoNode = $xpath->query('a:Codigo', $nodoEvento)->item(0);
                $Codigo = $codigoNode ? $codigoNode->nodeValue : '';
                $descricaoNode = $xpath->query('a:DescricaoCompleta',  $nodoEvento)->item(0);
                $Descricao = $descricaoNode ? $descricaoNode->nodeValue : '';

                $resultados[$Descricao] = [
                    'codigo' => $Codigo,
                    'DescricaoCompleta'     => $Descricao,
                ];
            }

                    $resultados = array_values($resultados);

            try {
                    foreach ($resultados as $resultado) {
                       

                        cidsmodel::updateOrCreate(
                            [
                            'CODIGO_CID'      => $resultado['codigo'],
                            'DESCRICAO'   => $resultado['DescricaoCompleta'], 
                            ]
                        );
                    }
               

             
               return self::SUCCESS;
            } catch (\Throwable $th) {
               return self::FAILURE;
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
