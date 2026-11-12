<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditoriaService
{
    public static function registrar(
        string $acao,
        string $tabela,
        string $registroId,
        ?array $dadosAnteriores = null,
        ?array $dadosNovos = null,
        ?string $descricao = null
    ): void {
        $console = app()->runningInConsole();

        DB::table('logs_auditoria')->insert([
            'id_usuario' => Auth::id(),
            'acao' => $acao,
            'tabela' => $tabela,
            'registro_id' => $registroId,
            'dados_anteriores' => self::prepararDados($dadosAnteriores),
            'dados_novos' => self::prepararDados($dadosNovos),
            'descricao' => $descricao,
            'origem' => $console ? 'console' : 'web',
            'ip' => $console ? null : request()->ip(),
            'user_agent' => $console ? null : request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    private static function prepararDados(?array $dados): ?string
    {
        if ($dados === null) {
            return null;
        }

        // Remove credenciais antes de armazenar os dados.
        unset(
            $dados['password'],
            $dados['password_confirmation'],
            $dados['remember_token']
        );

        return json_encode(
            $dados,
            JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}