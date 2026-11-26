<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Normalizer;

class TextoEmTitulo
{
    // O navegador usa o mesmo padrão para preservar links e endereços de e-mail.
    public const PADRAO = '(?:https?://|www\.)[^\s<>]*|(?<![\p{L}\p{M}\p{N}._%+\-])[\p{L}\p{M}\p{N}._%+\-]+@[\p{L}\p{M}\p{N}.\-]*|(\p{L})([\p{L}\p{M}]*)|([\r\n]+)';

    public const PALAVRAS_DE_LIGACAO = [
        'a', 'as', 'o', 'os', 'um', 'uma', 'uns', 'umas',
        'ante', 'após', 'até', 'com', 'contra', 'de', 'desde', 'em', 'entre', 'para', 'perante', 'por', 'sem', 'sob', 'sobre',
        'ao', 'aos', 'à', 'às', 'da', 'das', 'do', 'dos', 'dum', 'duma', 'duns', 'dumas',
        'na', 'nas', 'no', 'nos', 'num', 'numa', 'nuns', 'numas', 'pelo', 'pela', 'pelos', 'pelas',
        'e', 'mas', 'nem', 'ou', 'que', 'se',
    ];

    public static function formatar(string $texto): string
    {
        $inicioDaLinha = true;

        return preg_replace_callback('~'.self::PADRAO.'~iu', static function (array $trecho) use (&$inicioDaLinha): string {
            if (isset($trecho[3])) {
                $inicioDaLinha = true;

                return $trecho[0];
            }

            $primeiraPalavra = $inicioDaLinha;
            $inicioDaLinha = false;
            if (! isset($trecho[1])) {
                return $trecho[0];
            }

            $minuscula = Str::lower($trecho[0]);
            if (! $primeiraPalavra && in_array(Normalizer::normalize($minuscula), self::PALAVRAS_DE_LIGACAO, true)) {
                return $minuscula;
            }

            $inicial = Str::upper($trecho[1]);

            return Str::substr($inicial, 0, 1).Str::lower(Str::substr($inicial, 1).$trecho[2]);
        }, $texto) ?? $texto;
    }

    public static function naRequisicao(Request $request, string ...$campos): void
    {
        $formatados = [];
        foreach ($campos as $campo) {
            $valor = $request->input($campo);
            if (is_string($valor)) {
                $formatados[$campo] = self::formatar($valor);
            }
        }

        $request->merge($formatados);
    }
}
