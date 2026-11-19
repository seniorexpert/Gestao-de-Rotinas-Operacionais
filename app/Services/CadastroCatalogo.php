<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CadastroCatalogo
{
    /** @param array<string, mixed> $dados */
    public static function salvar(string $tabela, string $chave, ?int $id, array $dados, string $recurso): void
    {
        DB::transaction(function () use ($tabela, $chave, $id, $dados, $recurso) {
            $agora = now();
            $anterior = null;

            if ($id === null) {
                $dados = ['status' => true, ...$dados];
                $id = DB::table($tabela)->insertGetId([...$dados, 'created_at' => $agora, 'updated_at' => $agora], $chave);
            } else {
                $consulta = DB::table($tabela)->where($chave, $id);
                $registro = (clone $consulta)->lockForUpdate()->first(array_keys($dados));
                abort_unless($registro, 404);
                $anterior = (array) $registro;
                $consulta->update([...$dados, 'updated_at' => $agora]);
            }

            AuditoriaService::registrar(
                $anterior === null ? 'CREATE' : 'UPDATE',
                $tabela,
                (string) $id,
                $anterior,
                $dados,
                ($anterior === null ? 'Cadastro de ' : 'Edição de ').$recurso,
            );
        });
    }
}
