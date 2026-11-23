<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ConsultaRotinas
{
    public function empresas(User $usuario): Builder
    {
        if ($usuario->isAdmin()) {
            return DB::table('empresas')->select('id_empresa');
        }

        return DB::table('empresa_usuario')
            ->select('id_empresa')
            ->where('id_usuario', $usuario->getKey());
    }

    public function colaboradores(User $usuario): Builder
    {
        return DB::table('usuarios as u')
            ->leftJoin('grupos_acesso as g', 'u.id_grupo_acesso', '=', 'g.id_grupo_acesso')
            ->whereIn('u.id_usuario', DB::table('empresa_usuario')
                ->select('id_usuario')
                ->whereIn('id_empresa', $this->empresas($usuario)))
            ->select('u.id_usuario', 'u.usuario', 'g.grupo_acesso')
            ->orderBy('u.usuario');
    }

    public function rotinas(User $usuario): Builder
    {
        return DB::table('rotinas as r')
            ->join('usuarios as u', 'r.id_usuario', '=', 'u.id_usuario')
            ->join('empresas as e', 'r.id_empresa', '=', 'e.id_empresa')
            ->leftJoin('grupos_acesso as g', 'u.id_grupo_acesso', '=', 'g.id_grupo_acesso')
            ->leftJoin('tarefas as t', 'r.id_tarefa', '=', 't.id_tarefa')
            ->leftJoin('locais as l', 'r.id_local', '=', 'l.id_local')
            ->whereIn('r.id_empresa', $this->empresas($usuario));
    }

    public function execucoes(User $usuario): Builder
    {
        return DB::table('rotinas_execucoes as e')
            ->leftJoin('usuarios as u', 'e.id_usuario_responsavel', '=', 'u.id_usuario')
            ->leftJoin('grupos_acesso as g', 'u.id_grupo_acesso', '=', 'g.id_grupo_acesso')
            ->leftJoin('rotinas as r', fn (JoinClause $join) => $join
                ->on('e.id_rotina', '=', 'r.id_rotina')->on('e.id_empresa', '=', 'r.id_empresa'))
            ->leftJoin('tarefas as t', 'r.id_tarefa', '=', 't.id_tarefa')
            ->leftJoin('locais as l', 'r.id_local', '=', 'l.id_local')
            ->leftJoin('empresas as unidade', 'e.id_empresa', '=', 'unidade.id_empresa')
            ->whereIn('e.id_empresa', $this->empresas($usuario))
            ->select([
                'e.id_execucao', 'e.id_rotina', 'e.id_empresa', 'e.id_usuario_responsavel',
                'e.previsto_em', 'e.prazo_em', 'e.iniciado_em', 'e.concluido_em',
                'e.tempo_limite_minutos', 'e.duracao_prevista_segundos', 'e.status',
                'u.usuario as responsavel', 'u.id_grupo_acesso', 'g.grupo_acesso', 'unidade.empresa',
                't.tarefa', 'r.morador', 'r.local as local_rotina', 'l.local as local_cadastrado',
                'r.status as rotina_ativa', 'r.id_usuario as rotina_responsavel',
            ]);
    }

    public function execucoesDoDia(User $usuario, CarbonImmutable $agora): Builder
    {
        [$inicio, $fim] = ExecucoesDiarias::janela($agora);

        return $this->execucoes($usuario)->where('e.previsto_em', '>=', $inicio)->where('e.previsto_em', '<', $fim);
    }

    public function execucoesPessoais(User $usuario, ?CarbonImmutable $dia = null): Builder
    {
        $consulta = $dia ? $this->execucoesDoDia($usuario, $dia) : $this->execucoes($usuario);

        return $consulta->when(! $usuario->isAdmin(), fn (Builder $consulta) => $consulta->where('e.id_usuario_responsavel', $usuario->getKey()))
            ->leftJoin('ferramentas as f', 't.id_ferramenta', '=', 'f.id_ferramenta')
            ->addSelect('r.horario', 'r.obs', 'r.auxilio', 't.descricao', 't.documentacao', 't.icone', 't.cor_icone', 'f.ferramenta',
                'e.comentario_conclusao', 'e.foto_conclusao', 'e.justificativa_nao_realizada');
    }

    /** @return array{empresas: Collection, tarefas: Collection, locais: Collection} */
    public function catalogos(User $usuario): array
    {
        return [
            'empresas' => DB::table('empresas')->whereIn('id_empresa', $this->empresas($usuario))
                ->orderBy('empresa')->get(['id_empresa', 'empresa']),
            'tarefas' => DB::table('tarefas')->orderBy('tarefa')->get(['id_tarefa', 'tarefa']),
            'locais' => DB::table('locais')->orderBy('local')->get(['id_local', 'local']),
        ];
    }
}
