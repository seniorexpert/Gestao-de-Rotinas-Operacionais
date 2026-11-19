<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuditoriaService
{
    public const AUTOR_HTTP = 'auditoria.autor';

    public const RESULTADO_HTTP = 'auditoria.resultado';

    public const UNIDADE_HTTP = 'auditoria.unidade';

    public const ROTINA_HTTP = 'auditoria.rotina';

    public const ALTERACAO_REGISTRADA = 'auditoria.alteracao_registrada';

    public static function registrar(
        string $acao,
        string $tabela,
        string $registroId,
        ?array $dadosAnteriores = null,
        ?array $dadosNovos = null,
        ?string $descricao = null,
        ?int $idEmpresa = null,
        ?int $idRotina = null,
        ?Request $requisicao = null,
        ?User $autor = null,
        ?int $statusHttp = null,
        string $resultado = 'sucesso'
    ): void {
        if ($requisicao === null && app()->bound('request') && request()->route()) {
            $requisicao = request();
        }
        $autor ??= $requisicao ? $requisicao->user() : Auth::user();

        DB::table('logs_auditoria')->insert([
            'id_usuario' => $autor?->getKey(),
            'usuario_nome' => $autor?->usuario,
            'usuario_login' => $autor?->username,
            'id_empresa' => $idEmpresa,
            'id_rotina' => $idRotina,
            'acao' => $acao,
            'tabela' => $tabela,
            'registro_id' => $registroId,
            'dados_anteriores' => self::prepararDados($dadosAnteriores),
            'dados_novos' => self::prepararDados($dadosNovos),
            'descricao' => $descricao,
            'origem' => $requisicao ? 'web' : 'console',
            'rota' => $requisicao?->route()?->getName(),
            'metodo_http' => $requisicao?->method(),
            'status_http' => $statusHttp,
            'resultado' => $resultado,
            'ip' => $requisicao?->ip(),
            'user_agent' => mb_substr($requisicao?->userAgent() ?? '', 0, 512) ?: null,
            'created_at' => now('UTC'),
        ]);

        if ($statusHttp === null) {
            $requisicao?->attributes->set(self::ALTERACAO_REGISTRADA, true);
        }
    }

    public static function lerDados(?string $json): array
    {
        $dados = $json === null ? null : json_decode($json, true);

        return is_array($dados) ? self::ocultarCredenciais($dados) : [];
    }

    private static function prepararDados(?array $dados): ?string
    {
        if ($dados === null) {
            return null;
        }

        return json_encode(
            self::ocultarCredenciais($dados),
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
    }

    private static function ocultarCredenciais(array $dados): array
    {
        foreach ($dados as $campo => $valor) {
            if (preg_match('/password|passwd|senha|token|secret|segredo|cookie|authorization|csrf|api[_-]?key/i', (string) $campo)) {
                unset($dados[$campo]);
            } elseif (is_array($valor)) {
                $dados[$campo] = self::ocultarCredenciais($valor);
            }
        }

        return $dados;
    }
}
