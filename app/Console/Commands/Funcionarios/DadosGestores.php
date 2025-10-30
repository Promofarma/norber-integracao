<?php

namespace App\Console\Commands\Funcionarios;

use DOMXPath;
use DOMDocument;
use GuzzleHttp\Client;
use App\Http\LGheaders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\FuncionarioGestor;

class DadosGestores extends Command
{
    protected $signature = 'lg:gestores-folha {--Empresa= : Empresa (formato: inteiro)}';
    protected $description = 'Retorna os gestores de folha de pagamento dos colaboradores via API LG';

    protected $empresa;

    public function handle()
    {
        $this->empresa = $this->option('Empresa');

        $matriculas = DB::connection('promofarma')
            ->table('dbo.LG_IMPORTA_FUNCIONARIOS')
            ->where('EMPRESA', $this->empresa)
            // ->whereIn('MATRICULA', [3315, 6620, 5291, 6102, 5872, 4855, 7126, 5965, 3085])
            ->orderBy('MATRICULA')
            ->pluck('MATRICULA');

        $maxTipoSituacao = 4;

        foreach ($matriculas as $matricula) {
            for ($tipoSituacao = 1; $tipoSituacao <= $maxTipoSituacao; $tipoSituacao++) {
                $this->info("Matrícula: {$matricula} | Tipo de situação: {$tipoSituacao}");
                $this->buscaEventos($matricula, $tipoSituacao);
            }
        }
    }

    public function buscaEventos($matricula, $tipoSituacao)
    {
        $endpoint = 'https://prd-api1.lg.com.br/v1/ServicoDeContratoDeTrabalho';
        $headers = (new LGheaders())->getHeaders();

        $soapBody = <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:dto="lg.com.br/svc/dto"
                  xmlns:v1="lg.com.br/api/v1"
                  xmlns:v11="lg.com.br/api/dto/v1"
                  xmlns:arr="http://schemas.microsoft.com/2003/10/Serialization/Arrays">
    {$headers}
    <soapenv:Body>
        <v1:ConsultarListaGestoresNivelMaisProximo>
            <v1:filtro>
                <v11:Colaborador>
                    <v11:Empresa>
                        <v11:Codigo>{$this->empresa}</v11:Codigo>
                    </v11:Empresa>
                    <v11:Matricula>{$matricula}</v11:Matricula>
                </v11:Colaborador>
                <v11:TiposDeSituacoes>
                        <arr:int>{$tipoSituacao}</arr:int>

                  
                </v11:TiposDeSituacoes>
            </v1:filtro>
        </v1:ConsultarListaGestoresNivelMaisProximo>
    </soapenv:Body>
</soapenv:Envelope>
XML;

        $client = new Client();



        try {
            $response = $client->post($endpoint, [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction' => '"lg.com.br/api/v1/ServicoDeContratoDeTrabalho/ConsultarListaGestoresNivelMaisProximo"',
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
            $xpath->registerNamespace('a', 'https://lg.com.br/api/dto/v1');

            $contratos = $xpath->query('//a:ContratoDeTrabalhoParcial');

            $dados = [];

            foreach ($contratos as $contrato) {
                $matriculaContrato = $xpath->evaluate('string(a:Matricula)', $contrato);
                $cpf = $xpath->evaluate('string(a:Pessoa/a:Cpf)', $contrato);
                $nome = $xpath->evaluate('string(a:Pessoa/a:Nome)', $contrato);
                $dataNascimento = $xpath->evaluate('string(a:Pessoa/a:DataDeNascimento)', $contrato);
                $pessoaId = $xpath->evaluate('string(a:Pessoa/a:PessoaId)', $contrato);
                $codigoCentro = $xpath->evaluate('string(a:CentroDeCusto/a:Codigo)', $contrato);
                $descricaoCentro = $xpath->evaluate('string(a:CentroDeCusto/a:Descricao)', $contrato);
                $codigoUnidade = $xpath->evaluate('string(a:UnidadeOrganizacional/a:Codigo)', $contrato);
                $descricaoUnidade = $xpath->evaluate('string(a:UnidadeOrganizacional/a:Descricao)', $contrato);

                $dados[] = [
                    'matricula' => $matriculaContrato,
                    'cpf' => $cpf,
                    'nome' => $nome,
                    'data_nascimento' => $dataNascimento,
                    'pessoa_id' => $pessoaId,
                    'centro_custo_codigo' => $codigoCentro,
                    'centro_custo_descricao' => $descricaoCentro,
                    'unidade_codigo' => $codigoUnidade,
                    'unidade_descricao' => $descricaoUnidade,
                ];
            }

            foreach ($dados as $item) {
                FuncionarioGestor::updateOrCreate(
                    [
                        'MATRICULA' => $matricula,
                        'DATA'      => date('d-m-Y'),
                    ],
                    [
                        'MATRICULA_GESTOR'       => (int) $item['matricula'],
                        'NOME_GESTOR'            => $item['nome'],
                        'CENTRO_CUSTO'           => $item['centro_custo_codigo'],
                        'UNIDADE_ORGANIZACIONAL' => $item['unidade_codigo'],
                    ]
                );
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->error("Erro para matrícula {$matricula}, tipo {$tipoSituacao}: " . $e->getMessage());
        }
    }
}
