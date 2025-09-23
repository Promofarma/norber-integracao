<?php

namespace App\Console\Commands\EventosFolha;

use DOMXPath;
use DOMDocument;
use GuzzleHttp\Client;
use App\Http\LGheaders;
use Illuminate\Console\Command;
use App\Models\EventosFolha;


class RetornaEventos extends Command
{
    protected $signature = 'lg:eventos-folha';
    protected $description = 'Retorna os eventos de folha de pagamento dos colaboradores via API LG';

    public function handle()
    {
        $MaxTipoDeterminacao = 4;
        $MaxEvento = 2;

        for ($evento = 0; $evento <= $MaxEvento; $evento++) {
            for ($determinacao = 1; $determinacao <= $MaxTipoDeterminacao; $determinacao++) {
                $this->info("Buscando eventos do tipo: {$evento} e determinação: {$determinacao}");
                $this->buscaEventos($evento, $determinacao);
            }
        }
    }

    public function buscaEventos($evento, $determinacao)
    {

        $endpoint = 'https://prd-api1.lg.com.br/v1/servicodeevento';

        $headers = new LGheaders();
        $headers = $headers->getHeaders();

        $soapBody = <<<XML
            <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:dto="lg.com.br/svc/dto" xmlns:v1="lg.com.br/api/v1" xmlns:v11="lg.com.br/api/dto/v1">
            {$headers}
                <soapenv:Body>
                        <v1:ConsultarLista>
                             <v1:filtro>
                                   <v11:Empresa>
                                           <v11:Codigo>8</v11:Codigo>
                                   </v11:Empresa>
                                   <v11:TipoDeterminacao>$determinacao</v11:TipoDeterminacao>
                                   <v11:TipoDoEvento>$evento</v11:TipoDoEvento>
                                   </v1:filtro>
                                </v1:ConsultarLista>
                </soapenv:Body>
            </soapenv:Envelope>
            XML;


        $client = new Client();

        try {

            $headers = [
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => '"lg.com.br/api/v1/ServicoDeEvento/ConsultarLista"'
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

            $eventos = $xpath->query('//a:ObjetoComCodigoNumericoEDescricao');

            $resultados = [];


            foreach ($eventos as $nodoEvento) {
                $Codigo = $xpath->query('a:Codigo', $nodoEvento)->item(0)?->nodeValue ?? '';
                $Descricao     = $xpath->query('a:Descricao',     $nodoEvento)->item(0)?->nodeValue ?? '';

                $resultados[$Descricao] = [
                    'codigo' => $Codigo,
                    'descricao'     => $Descricao,
                ];
            }

            $resultados = array_values($resultados);

            try {
                foreach ($resultados as $resultado) {
                    EventosFolha::updateOrCreate(
                        [
                            'CODIGO'      => $resultado['codigo'],
                            'DESCRICAO'   => $resultado['descricao'],
                            'TIPO_EVENTO' => $evento,
                            'DESCRICAO_EVENTO' => ($evento == 0) ? 'Provento' : (($evento == 1) ? 'Desconto' : 'Resultado'),
                        ]
                    );
                }
            } catch (\Throwable $th) {
                dd($th);
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
