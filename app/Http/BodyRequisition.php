<?php

namespace App\Http;

use Illuminate\Support\Env;

class BodyRequisition
{
    /**
     * Retorna os headers padrão para requisições HTTP.
     *
     * @return array
     */

    public static function getPeriod($startDate = null, $endDate = null)
    {
        $start = $startDate ?: date('Y-m-01'); // Primeiro dia do mês atual
        $end = $endDate ?: date('Y-m-t'); // Último dia do mês atual

        return [
            "DataInicio" => $start,
            "DataFim"    => $end,
        ];
    }

    public static function getBody($startDate = null, $endDate = null, $conceito = null, $codigoExterno = null, $pagina = null)
    {
        $period = self::getPeriod($startDate, $endDate);

        return [
            "DataInicio"  => $period['DataInicio'],
            "DataFim"     => $period['DataFim'],
            "ListaDeFiltros" => [
                [
                    "Conceito"      => $conceito,
                    "CodigoExterno" => $codigoExterno

                ]
            ],
            "Pagina" => $pagina
        ];
    }

    public static function getBodySaldo($startDate = null,  $conceito = null, $codigoExterno = null, $pagina = null)
    {
        $period = self::getPeriod($startDate);

        return [
            "MesAnoReferencia"  => $period['DataInicio'],
            "ListaDeFiltros" => [
                [
                    "Conceito"      => $conceito,
                    "CodigoExterno" => $codigoExterno

                ]
            ],
            "Pagina" => $pagina
        ];
    }

    public static function getBodyV2($startDate = null, $conceito = null, $codigoExterno = null, $pagina = null)
    {


        return [
            "DataInicial"  => $startDate,
            "ListaDeFiltros" => [
                [
                    "Conceito"      => $conceito,
                    "CodigoExterno" => $codigoExterno

                ]
            ],
            "Pagina" => $pagina
        ];
    }
}
