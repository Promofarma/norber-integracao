<?php

namespace App\Http;

class LGheaders
{
    /**
     * Retorna os headers padrão para requisições HTTP em XML.
     *
     * @return string
     */
    public static function getHeaders(): string
    {
        $user = env('LG_USER');
        $pass = env('LG_PASS');
        $guid = env('LG_GUID');
        $ambiente = env('LG_AMBIENTE');

        return <<<XML
<soapenv:Header>
    <dto:LGAutenticacao>
        <dto:TokenUsuario>
            <dto:Senha>{$pass}</dto:Senha>
            <dto:Usuario>{$user}</dto:Usuario>
            <dto:GuidTenant>{$guid}</dto:GuidTenant>
        </dto:TokenUsuario>
    </dto:LGAutenticacao>
    <dto:LGContextoAmbiente>
        <dto:Ambiente>{$ambiente}</dto:Ambiente>
    </dto:LGContextoAmbiente>
</soapenv:Header>
XML;
    }
}
