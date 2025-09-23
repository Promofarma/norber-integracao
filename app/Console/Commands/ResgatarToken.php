<?php

namespace App\Console\Commands;

use App\Http\Headers;
use GuzzleHttp\Client;
use App\Console\UrlBaseNorber;
use App\Http\BodyToken;
use Illuminate\Console\Command;

class ResgatarToken extends Command
{
    protected $signature = "norber:resgatar-token";
    protected $description = 'Resgata o token e salva no .env';

    protected function UrlBaseNorberApi()
    {
        $UrlBaseNorber = new UrlBaseNorber();
        return $UrlBaseNorber->getUrlBaseNorber();
    }

    public function handle()
    {
        $client = new Client();
        $headers = Headers::getHeaders();
        $body = BodyToken::getBody();
        $url_base = $this->UrlBaseNorberApi();

        $comandToken = "autenticacao/autenticar";
        $urlCompleta = $url_base . $comandToken;



        $res = $client->post($urlCompleta, [
            'headers' => $headers,
            'body'    => json_encode($body),
        ]);

        $response = $res->getBody()->getContents();
        $data = json_decode($response, true);



        $token = $data['ObjetoDeRetorno'] ?? null;

        if ($token) {
            $path = base_path('.env');
            $env = file_get_contents($path);

            if (preg_match('/^API_TOKEN=.*/m', $env)) {

                $env = preg_replace('/^API_TOKEN=.*/m', 'API_TOKEN=' . $token, $env);
            } else {

                $env .= "\nAPI_TOKEN=" . $token;
            }

            file_put_contents($path, $env);
        }
    }
}
