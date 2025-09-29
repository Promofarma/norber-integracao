<?php

namespace App\Console\Commands\Funcionarios;

use Carbon\Carbon;
use App\Models\Logs;
use App\Http\Headers;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use App\Http\BodyRequisition;
use App\Console\UrlBaseNorber;
use App\Models\MarcacoesPontos;
use Illuminate\Console\Command;
use App\Models\FuncionarioGestor;


class DadosFuncionarios extends Command
{
    // Modificado: Adicionar opções de data no signature
    protected $signature = 'norber:retornar-dados-funcionarios 
                            {--start-date= : Data de início (formato: YYYY-MM-DD)}
                            {--Conceito= : Conceito (formato: inteiro)}
                            {--CodigoExterno= : Código externo (formato: string)}';

    protected $description = "Lista colaboradores e o histórico de gestores" . PHP_EOL .
        "Modo de Uso: Data Inicial = (formato: YYYY-MM-DD) | Conceito = (1 para Empresa, 3 para Matrícula) | Codigo Externo= (Número com base no conceito)";


    protected function UrlBaseNorberApi()
    {
        $UrlBaseNorber = new UrlBaseNorber();
        return $UrlBaseNorber->getUrlBaseNorber();
    }

    public function handle()
    {
        // variaveis que serão atribuidas no comando
        $startDate = $this->option('start-date');
        $conceito = $this->option('Conceito');
        $codigoExterno = $this->option('CodigoExterno');


        // Validar se as datas foram fornecidas
        if (!$startDate) {
            $this->error('Por favor, forneça a data: --start-date');
            return 1;
        }

        $client = new Client();
        $headers = Headers::getHeaders();
        $url_base = $this->UrlBaseNorberApi();
        $command = 'v2/colaborador/retorna-funcionarios-mtr';


        $ultimaPaginaProcessada = 0;

        for ($pagina = $ultimaPaginaProcessada + 1;; $pagina++) {
            $body = BodyRequisition::getBodyV2($startDate,  $conceito, $codigoExterno, $pagina);

            try {
                $response = $client->post($url_base . $command, [
                    'headers' => $headers,
                    'body'    => json_encode($body, JSON_UNESCAPED_UNICODE)
                ]);

                $responseContent = $response->getBody()->getContents();
                $data = json_decode($responseContent, true);



                foreach ($data['Resultado'] as $funcionario) {

                    if (stripos($funcionario['Situacao'], 'RESC') === false) {


                        $gestores = $funcionario['Gestores'] ?? [];
                        if (empty($gestores)) {
                            FuncionarioGestor::updateOrCreate(
                                [
                                    'DATA'      => date('Y-m-d'),
                                    'MATRICULA' => (int) $funcionario['Matricula'],
                                    'MATRICULA_GESTOR' => null, // garante unicidade
                                    'NOME'            => $funcionario['NomeFunc'],
                                    'CENTRO_CUSTO' => (int) $funcionario['CodCentroCusto'],
                                    'INDICE'        => null,
                                    'NOME_GESTOR'     => null,
                                    'PAGINA'          => (int) $data['Pagina'],
                                ]
                            );
                        } else {
                            foreach ($gestores as $indice => $gestor) {
                                FuncionarioGestor::updateOrCreate(
                                    [
                                        'DATA'             => date('Y-m-d'),
                                        'MATRICULA'        => (int) $funcionario['Matricula'],
                                        'MATRICULA_GESTOR' => $gestor['MatrGestor'] ?? null,
                                        'NOME'            => $funcionario['NomeFunc'],
                                        'CENTRO_CUSTO' => (int) $funcionario['CodCentroCusto'],
                                        'INDICE'    => $indice,
                                        'NOME_GESTOR'     => $gestor['NomeGestor'] ?? null,
                                        'PAGINA'          => (int) $data['Pagina'],
                                    ]
                                );
                            }
                        }
                    }
                }
                if ($pagina % 10 === 0) {
                    sleep(1);
                }
                if (isset($data['TotalPaginas']) && $pagina >= $data['TotalPaginas']) {
                    break;
                }
            } catch (\Exception $e) {
                $this->error("Erro na página {$pagina}: " . $e->getMessage());
                break;
            }
        }
        return 0;
    }
}
