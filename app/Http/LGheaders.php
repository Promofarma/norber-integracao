<?php

namespace App\Http;

class LGheaders
{
    public static function getHeaders(): string
    {
        return self::buildHeaders(env('LG_USER'), env('LG_PASS'), env('LG_GUID'), env('LG_AMBIENTE'));
    }

    public static function getHeadersHomolog(): string
    {
        return self::buildHeaders(
            env('LG_USER_HOMOLOG'),
            env('LG_PASS_HOMOLOG'),
            env('LG_GUID_HOMOLOG'),
            env('LG_AMBIENTE_HOMOLOG'),
        );
    }

    private static function buildHeaders(string $user, string $pass, string $guid, string $ambiente): string
    {
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
