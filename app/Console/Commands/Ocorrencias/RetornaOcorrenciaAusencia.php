<?php

namespace App\Console\Commands\Ocorrencias;

use App\Models\Logs;
use App\Http\Headers;
use GuzzleHttp\Client;
use App\Console\UrlBaseNorber;
use App\Http\BodyRequisition;
use Illuminate\Console\Command;
use App\Models\OcorrenciasAusencias;
use Illuminate\Support\Carbon;

class RetornaOcorrenciaAusencia extends Command
{
    protected $signature = 'norber:retornar-ocorrencia-ausencia 
                           {--start-date= : Data de início (formato: YYYY-MM-DD)}
                            {--end-date= : Data de fim (formato: YYYY-MM-DD)}
                            {--Conceito= : Conceito (formato: inteiro)}
                            {--CodigoExterno= : Código externo (formato: string)}';

    protected $description = "Listar ocorrencias de ausencia";

    protected function UrlBaseNorberApi()
    {
        $UrlBaseNorber = new UrlBaseNorber();
        return $UrlBaseNorber->getUrlBaseNorber();
    }

    public function handle()
    {

        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        $conceito = $this->option('Conceito');
        $codigoExterno = $this->option('CodigoExterno');

        // Validar se as datas foram fornecidas
        if (!$startDate || !$endDate) {
            $this->error('Por favor, forneça ambas as datas: --start-date e --end-date');
            return 1;
        }
        if (
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate)
        ) {
            $this->error('Formato de data inválido. Use YYYY-MM-DD');
            return 1;
        }

        $client = new Client();
        $headers = Headers::getHeaders();
        $url_base = $this->UrlBaseNorberApi();
        $command = 'Ocorrencia/RetornaOcorrenciaAusencia';
        $ultimaPaginaProcessada = OcorrenciasAusencias::where('DATA_OCORRENCIA', '>=', $startDate)
            ->where('DATA_OCORRENCIA', '<=',  $endDate)
            ->max('PAGINA') ?? 0;


        for ($pagina = $ultimaPaginaProcessada + 1;; $pagina++) {

            $body = BodyRequisition::getBody($startDate, $endDate, $conceito, $codigoExterno, $pagina);

            try {
                $response = $client->post($url_base . $command, [
                    'headers' => $headers,
                    'body'    => json_encode($body, JSON_UNESCAPED_UNICODE)
                ]);

                $responseContent = $response->getBody()->getContents();
                $data = json_decode($responseContent, true);
                $itens = $data['ListaDeFiltro'] ?? [];
                $resultado = [];

                foreach ($data['ListaDeFiltro'] as $filtro) {
                    $matricula = $filtro['Contrato']['Matricula'];

                    foreach ($filtro['OcorrenciasAusencias'] as $ocorrencia) {
                        $resultado[] = [
                            'Matricula'      => $matricula,
                            'DataOcorrencia' => $ocorrencia['DataOcorrencia'],
                            'Inicio'         => $ocorrencia['Inicio'],
                            'Fim'            => $ocorrencia['Fim'],
                            'Descricao'      => $ocorrencia['Tipo']['Descricao'],
                            'Justificativa'  => $ocorrencia['Tipo']['Justificativa'],
                            'QtdeHoras'      => $ocorrencia['QtdeHoras'],
                        ];
                    }
                }
                try {
                    foreach ($resultado as $item) {

                        $dataOcorrencia = Carbon::createFromFormat('d/m/Y', $item['DataOcorrencia'])->format('Y-m-d');
                        $inicioExpediente = Carbon::createFromFormat('d/m/Y H:i', $item['Inicio'])->format('Y-m-d H:i:s');
                        $fimExpediente = Carbon::createFromFormat('d/m/Y H:i', $item['Fim'])->format('Y-m-d H:i:s');

                        OcorrenciasAusencias::UpdateOrCreate([
                            'MATRICULA'         => $item['Matricula'],
                            'DATA_OCORRENCIA'   => $dataOcorrencia,
                            'INICIO_EXPEDIENTE' => $inicioExpediente,
                            'FIM_EXPEDIENTE'    => $fimExpediente,
                            'DESCRICAO'         => $item['Descricao'],
                            'JUSTIFICATIVA'     => $item['Justificativa'],
                            'QUANTIDADE_HORAS'  => $item['QtdeHoras'],
                            'PAGINA'            => $data['Pagina']
                        ]);
                    }

                    $this->info("Página {$pagina} processada com sucesso. Total registros: " . count($itens));

                    Logs::create([
                        'DATA_EXECUCAO' => date('Y-m-d'),
                        'COMANDO_EXECUTADO' =>  $command . ' - ' . json_encode($body),
                        'STATUS_COMANDO' => $response->getStatusCode(),
                        'TOTAL_REGISTROS' => count($itens)
                    ]);

                    if ($pagina % 10 === 0) {
                        sleep(1);
                    }


                    if (isset($data['TotalPaginas']) && $pagina >= $data['TotalPaginas']) {
                        break;
                    }
                } catch (\Throwable $th) {
                    $this->error("Erro ao inserir dados: " . $th->getMessage());
                    return 1;
                }
            } catch (\Exception $e) {
                $this->error('Erro na requisição: ' . $e->getMessage());
                return 1;
            }
        }
        return 0;
    }
}
