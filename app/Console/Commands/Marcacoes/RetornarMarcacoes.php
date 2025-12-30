<?php

namespace App\Console\Commands\Marcacoes;

use Carbon\Carbon;
use App\Models\Logs;
use App\Http\Headers;
use GuzzleHttp\Client;
use App\Http\BodyRequisition;
use App\Console\UrlBaseNorber;
use App\Models\MarcacoesPontos;
use Illuminate\Console\Command;

class RetornarMarcacoes extends Command
{
    // Modificado: Adicionar opções de data no signature
    protected $signature = 'norber:retornar-marcacoes 
                            {--start-date= : Data de início (formato: YYYY-MM-DD)}
                            {--end-date= : Data de fim (formato: YYYY-MM-DD)}
                            {--Conceito= : Conceito (formato: inteiro)}
                            {--CodigoExterno= : Código externo (formato: string)}';

    protected $description = "Listar marcações de pontos" . PHP_EOL .
        "Modo de Uso: Data Inicial = (formato: YYYY-MM-DD) | Data Final = (formato: YYYY-MM-DD) | Conceito = (1 para Empresa, 3 para Matrícula) | Codigo Externo= (Número com base no conceito)";


    protected function UrlBaseNorberApi()
    {
        $UrlBaseNorber = new UrlBaseNorber();
        return $UrlBaseNorber->getUrlBaseNorber();
    }

    public function handle()
    {
        // variaveis que serão atribuidas no comando
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $conceito = $this->option('Conceito');
        $codigoExterno = $this->option('CodigoExterno');


        // Validar se as datas foram fornecidas
        if (!$startDate || !$endDate) {
            $this->error('Por favor, forneça ambas as datas: --start-date e --end-date');
            return 1;
        }

        $client = new Client();
        $headers = Headers::getHeaders();
        $url_base = $this->UrlBaseNorberApi();
        $command = 'marcacao/RetornaMarcacoes';

        MarcacoesPontos::whereBetween('DATA', [date_format(Carbon::parse($startDate), 'd-m-Y'), date_format(Carbon::parse($endDate), 'd-m-Y')])->delete();


        for ($pagina =  1;; $pagina++) {
            $body = BodyRequisition::getBody($startDate, $endDate, $conceito, $codigoExterno, $pagina);

            try {
                $response = $client->post($url_base . $command, [
                    'headers' => $headers,
                    'body'    => json_encode($body, JSON_UNESCAPED_UNICODE)
                ]);

                $responseContent = $response->getBody()->getContents();
                $data = json_decode($responseContent, true);



                $itens = $data['ListaDeFiltro'] ?? [];
                $dadosCorrigidos = [];

                foreach ($itens as $item) {
                    $marcacao = str_replace(['–', '—'], '-', $item['Marcacoes']);
                    $marcacao = trim($marcacao);

                    if (strpos($marcacao, '-') !== false) {
                        $marcacoesArray = array_map('trim', explode('-', $marcacao));

                        $dados = [
                            'Data'       => $item['Data'],
                            'Nome'       => $item['Nome'],
                            'Matricula'  => $item['Matricula'],
                            'Cpf'        => $item['Cpf'],
                        ];

                        foreach ($marcacoesArray as $index => $marcacoes) {
                            $dados['Marcacoes' . ($index + 1)] = $marcacoes;
                        }

                        $dadosCorrigidos[] = $dados;
                    } else {
                        // SEM MARCAÇÕES também precisa ir pro banco
                        $dadosCorrigidos[] = [
                            'Data'      => $item['Data'],
                            'Nome'      => $item['Nome'],
                            'Matricula' => $item['Matricula'],
                            'Cpf'       => $item['Cpf'],
                            'Marcacoes1' => $marcacao
                        ];
                    }
                }

                // Inserir no banco de dados
                foreach ($dadosCorrigidos as $dado) {
                    $marcacoes = [];
                    $i = 1;

                    while (isset($dado['Marcacoes' . $i]) && !empty($dado['Marcacoes' . $i])) {
                        $marcacoes[] = $dado['Marcacoes' . $i];
                        $i++;
                    }



                    foreach ($marcacoes as $marcacao) {


                        MarcacoesPontos::updateOrCreate([
                            'DATA' => Carbon::createFromFormat('d/m/Y', $dado['Data'])->format('Y-m-d'),
                            'MATRICULA' => $dado['Matricula'],
                            'NOME' => $dado['Nome'],
                            'CPF' => $dado['Cpf'],
                            'MARCACOES' => $marcacao,
                            'PAGINA' => $data['Pagina']
                        ]);
                    }
                }

                $this->info("Página {$pagina} processada com sucesso. Total registros: " . count($dadosCorrigidos));

                Logs::create([
                    'DATA_EXECUCAO' => Carbon::now()->format('d-m-Y H:i:s.v'),
                    'COMANDO_EXECUTADO' =>  $command . ' - ' . json_encode($body),
                    'STATUS_COMANDO' => $response->getStatusCode(),
                    'TOTAL_REGISTROS' => count($dadosCorrigidos)
                ]);


                if ($pagina % 10 === 0) {
                    sleep(1);
                }

                // quando chegar na última página, para o loop
                if (isset($data['TotalPaginas']) && $pagina >= $data['TotalPaginas']) {
                    return self::SUCCESS;
                }
            } catch (\Exception $e) {
                $this->error("Erro na página {$pagina}: " . $e->getMessage());
                return self::FAILURE;
             }
        }

        return 0; // só aqui no final

    }
}
