<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->cadastrarSeFaltar('empresas', 'empresa', [
                'Menino Deus',
                'Garden Ville',
            ]);

            $this->cadastrarSeFaltar('grupos_acesso', 'grupo_acesso', [
                'Enfermagem',
                'Cuidador',
                'Farmácia',
                'Coordenador',
                'Estoque',
                'Manutenção',
                'Auxiliar de Limpeza',
                'Auxiliar de Cozinha',
                'Gerente da Unidade',
                'Gerente Geral',
            ]);
        });
    }

    public function down(): void
    {
        // Os cadastros podem estar vinculados a usuários ou rotinas.
    }

    /** @param list<string> $nomes */
    private function cadastrarSeFaltar(string $tabela, string $coluna, array $nomes): void
    {
        $agora = now();

        foreach ($nomes as $nome) {
            $registro = DB::table($tabela)->where($coluna, $nome)->lockForUpdate()->first([$coluna]);

            if ($registro === null) {
                DB::table($tabela)->insert([
                    $coluna => $nome,
                    'status' => true,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }
        }
    }
};
