<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CadastroCatalogo
{
    public function __construct(private ConsultaRotinas $consulta) {}

    /**
     * @param  array<string, mixed>  $dados
     * @param  list<int|string>  $unidades
     */
    public function salvar(User $usuario, string $tabela, ?int $id, array $dados, array $unidades, string $recurso): void
    {
        $chave = ConsultaRotinas::CHAVES_CATALOGO[$tabela];
        $unidades = array_map('intval', $unidades);
        $campo = $tabela === 'locais' ? 'id_empresa' : 'id_empresas';

        DB::transaction(function () use ($usuario, $tabela, $chave, $id, $dados, $unidades, $campo, $recurso): void {
            $empresas = $this->consulta->empresas($usuario)->pluck('id_empresa')->map(fn ($id): int => (int) $id)->all();
            if (array_diff($unidades, $empresas) !== []) {
                throw ValidationException::withMessages([$campo => 'Selecione apenas unidades às quais você tem acesso.']);
            }

            $agora = now();
            $anterior = null;
            $atuais = [];

            if ($id === null) {
                $dados = ['status' => true, ...$dados];
            } else {
                $consulta = DB::table($tabela)->where($chave, $id);
                $registro = (clone $consulta)->lockForUpdate()->first(array_keys($dados));
                abort_unless($registro, 404);
                $anterior = (array) $registro;
                $atuais = DB::table($tabela.'_empresas')->where($chave, $id)
                    ->orderBy('id_empresa')->pluck('id_empresa')->map(fn ($id): int => (int) $id)->all();
                abort_unless($usuario->isAdmin() || ($atuais !== [] && array_diff($atuais, $empresas) === []), 404);
            }

            if ($tabela === 'locais' && count($atuais) > 1) {
                if (array_diff($unidades, $atuais) !== []) {
                    throw ValidationException::withMessages([$campo => 'Este local está compartilhado no histórico. Cadastre um novo local para outra unidade.']);
                }
                $unidades = $atuais;
            }
            if ($tabela === 'tarefas' && filled($dados['id_ferramenta'] ?? null)) {
                $this->validarFerramenta((int) $dados['id_ferramenta'], $unidades, $anterior, $atuais);
            }

            $removidas = array_values(array_diff($atuais, $unidades));
            if ($removidas !== []) {
                $this->verificarDependencias($tabela, $chave, $id, $removidas, $campo);
            }

            if ($id === null) {
                $id = DB::table($tabela)->insertGetId([...$dados, 'created_at' => $agora, 'updated_at' => $agora], $chave);
            } else {
                $consulta->update([...$dados, 'updated_at' => $agora]);
            }

            if ($removidas !== []) {
                DB::table($tabela.'_empresas')->where($chave, $id)->whereIn('id_empresa', $removidas)->delete();
            }
            $novas = array_diff($unidades, $atuais);
            if ($novas !== []) {
                DB::table($tabela.'_empresas')->insert(array_map(
                    fn (int $unidade): array => [$chave => $id, 'id_empresa' => $unidade],
                    array_values($novas),
                ));
            }

            AuditoriaService::registrar(
                $anterior === null ? 'CREATE' : 'UPDATE',
                $tabela,
                (string) $id,
                $anterior === null ? null : [...$anterior, 'id_empresas' => $atuais],
                [...$dados, 'id_empresas' => $unidades],
                ($anterior === null ? 'Cadastro de ' : 'Edição de ').$recurso,
                idEmpresa: count($unidades) === 1 ? $unidades[0] : null,
            );
        }, 3);
    }

    /**
     * @param  list<int>  $unidades
     * @param  list<int>  $atuais
     */
    private function validarFerramenta(int $id, array $unidades, ?array $anterior, array $atuais): void
    {
        $ferramenta = DB::table('ferramentas')->where('id_ferramenta', $id)->lockForUpdate()->first(['status']);
        $vinculada = DB::table('ferramentas_empresas')->where('id_ferramenta', $id)->whereIn('id_empresa', $unidades)->exists();
        $mantida = (int) ($anterior['id_ferramenta'] ?? 0) === $id && array_diff($unidades, $atuais) === [];

        if (! $ferramenta || (! $ferramenta->status && ! $mantida) || ! $vinculada) {
            throw ValidationException::withMessages(['id_ferramenta' => 'Selecione uma ferramenta ativa de uma das unidades da tarefa.']);
        }
    }

    /** @param list<int> $unidades */
    private function verificarDependencias(string $tabela, string $chave, int $id, array $unidades, string $campo): void
    {
        $vinculada = $tabela === 'ferramentas'
            ? DB::table('tarefas as t')->join('tarefas_empresas as v', 't.id_tarefa', '=', 'v.id_tarefa')
                ->where('t.id_ferramenta', $id)->whereIn('v.id_empresa', $unidades)->exists()
            : DB::table('rotinas')->where($chave, $id)->whereIn('id_empresa', $unidades)->exists();

        if ($vinculada) {
            throw ValidationException::withMessages([$campo => 'Uma das unidades removidas ainda usa este cadastro. Mantenha o vínculo ou altere os registros que o utilizam.']);
        }
    }
}
