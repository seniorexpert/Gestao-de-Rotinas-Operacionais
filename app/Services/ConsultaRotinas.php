<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class ConsultaRotinas
{
    public const CHAVES_CATALOGO = [
        'tarefas' => 'id_tarefa',
        'ferramentas' => 'id_ferramenta',
        'locais' => 'id_local',
    ];

    public function empresas(User $usuario): Builder
    {
        if ($usuario->isAdmin()) {
            return DB::table('empresas')->select('id_empresa');
        }

        return DB::table('empresa_usuario')
            ->select('id_empresa')
            ->where('id_usuario', $usuario->getKey());
    }

    public function colaboradores(User $usuario, ?int $idEmpresa = null): Builder
    {
        return DB::table('usuarios as u')
            ->leftJoin('grupos_acesso as g', 'u.id_grupo_acesso', '=', 'g.id_grupo_acesso')
            ->whereIn('u.id_usuario', DB::table('empresa_usuario')
                ->select('id_usuario')
                ->when($idEmpresa !== null, fn (Builder $vinculos) => $vinculos->where('id_empresa', $idEmpresa))
                ->whereIn('id_empresa', $this->empresas($usuario)))
            ->select('u.id_usuario', 'u.usuario', 'g.grupo_acesso')
            ->orderBy('u.usuario');
    }

    public function cadastrosDeRotinas(User $usuario): Builder
    {
        return DB::table('rotinas as r')
            ->whereIn('r.id_empresa', $this->empresas($usuario))
            ->whereNull('r.excluida_em');
    }

    public function rotinas(User $usuario): Builder
    {
        return $this->cadastrosDeRotinas($usuario)
            ->join('usuarios as u', 'r.id_usuario', '=', 'u.id_usuario')
            ->join('empresas as e', 'r.id_empresa', '=', 'e.id_empresa')
            ->leftJoin('grupos_acesso as g', 'u.id_grupo_acesso', '=', 'g.id_grupo_acesso')
            ->leftJoin('tarefas as t', 'r.id_tarefa', '=', 't.id_tarefa')
            ->leftJoin('locais as l', 'r.id_local', '=', 'l.id_local');
    }

    public function rotinasDiarias(User $usuario): Builder
    {
        return $this->rotinas($usuario)->where('r.tipo_rotina', ExecucoesDiarias::TIPO_DIARIA);
    }

    public function grupoDaRotina(Builder $consulta, string $grupo): Builder
    {
        $grupoIdentificado = Str::isUuid($grupo);
        abort_unless($grupoIdentificado || (ctype_digit($grupo) && filter_var($grupo, FILTER_VALIDATE_INT) > 0), 404);

        return $consulta->where('r.tipo_rotina', ExecucoesDiarias::TIPO_DIARIA)
            ->when($grupoIdentificado, fn (Builder $rotinas) => $rotinas->where('r.grupo_rotina', $grupo),
                fn (Builder $rotinas) => $rotinas->whereNull('r.grupo_rotina')->where('r.id_rotina', (int) $grupo));
    }

    public function filtrarPorMorador(Builder $consulta, string $morador, bool $rotinaInteira = false): Builder
    {
        $busca = '%'.strtr($morador, ['~' => '~~', '%' => '~%', '_' => '~_', '[' => '~[', ']' => '~]']).'%';
        $correspondencia = $consulta->getConnection()->getDriverName() === 'sqlsrv'
            ? 'r.morador COLLATE Latin1_General_100_CI_AI LIKE ?'
            : 'LOWER(r.morador) LIKE LOWER(?)';
        $condicao = "{$correspondencia} ESCAPE '~'";

        return $rotinaInteira
            ? $consulta->havingRaw("MAX(CASE WHEN {$condicao} THEN 1 ELSE 0 END) = 1", [$busca])
            : $consulta->whereRaw($condicao, [$busca]);
    }

    public function filtrarExecucoesVisiveis(Builder $consulta): Builder
    {
        return $this->filtrarEmergenciaisDisponiveis($consulta)
            ->whereNull('r.excluida_em');
    }

    public function filtrarEmergenciaisDisponiveis(Builder $consulta): Builder
    {
        return $consulta->where(fn (Builder $visiveis) => $visiveis
            ->whereNull('r.tipo_rotina')
            ->orWhere('r.tipo_rotina', ExecucoesDiarias::TIPO_DIARIA)
            ->orWhere(fn (Builder $emergenciais) => $emergenciais
                ->where('r.tipo_rotina', ExecucoesDiarias::TIPO_EMERGENCIAL)
                ->whereColumn('e.previsto_em', 'r.agendada_em')
                ->where('e.previsto_em', '<=', now('UTC'))));
    }

    public function execucoes(User $usuario): Builder
    {
        $consulta = DB::table('rotinas_execucoes as e')
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
                'e.tempo_limite_minutos', 'e.duracao_prevista_segundos', 'e.status', 'e.created_at', 'r.tipo_rotina',
                'u.usuario as responsavel', 'u.id_grupo_acesso', 'g.grupo_acesso', 'unidade.empresa',
                't.tarefa', 'r.morador', 'r.local as local_rotina', 'l.local as local_cadastrado',
                'r.status as rotina_ativa', 'r.id_usuario as rotina_responsavel',
            ]);

        return $this->filtrarExecucoesVisiveis($consulta);
    }

    public function execucoesDaAgenda(User $usuario, CarbonImmutable $agora): Builder
    {
        [$inicio, $fim] = ExecucoesDiarias::janela($agora);

        return $this->execucoes($usuario)->where(fn (Builder $jornada) => $jornada
            ->where('e.agenda_ate', '>', $agora->utc())
            ->orWhere(fn (Builder $legado) => $legado->whereNull('e.agenda_ate')
                ->where('e.previsto_em', '>=', $inicio)->where('e.previsto_em', '<', $fim))
            ->orWhere(fn (Builder $emergenciais) => $emergenciais->where('r.tipo_rotina', ExecucoesDiarias::TIPO_EMERGENCIAL)
                ->whereIn('e.status', ['pendente', 'em_andamento']))
            ->orWhere(fn (Builder $emAndamento) => $emAndamento->whereNotNull('e.agenda_ate')->where('e.status', 'em_andamento')));
    }

    public function execucoesPessoais(User $usuario, ?CarbonImmutable $agora = null): Builder
    {
        $consulta = $agora ? $this->execucoesDaAgenda($usuario, $agora) : $this->execucoes($usuario);

        return $consulta->when(! $usuario->isAdmin(), fn (Builder $consulta) => $consulta->where('e.id_usuario_responsavel', $usuario->getKey()))
            ->leftJoin('ferramentas_empresas as fe', fn (JoinClause $join) => $join
                ->on('fe.id_ferramenta', '=', 't.id_ferramenta')
                ->on('fe.id_empresa', '=', 'e.id_empresa'))
            ->leftJoin('ferramentas as f', 'fe.id_ferramenta', '=', 'f.id_ferramenta')
            ->addSelect('e.agenda_ate', 'r.horario', 'r.obs', 'r.auxilio', 't.descricao', 't.documentacao', 't.icone', 't.cor_icone', 'f.ferramenta',
                'e.comentario_conclusao', 'e.foto_conclusao', 'e.justificativa_nao_realizada');
    }

    /** @return Collection<int, stdClass> */
    public function concluidasNaJornada(User $usuario, CarbonImmutable $agora): Collection
    {
        $agora = $agora->utc();
        [$inicioDoDia, $fimDoDia] = ExecucoesDiarias::janela($agora);
        $chaveJornada = fn (stdClass $execucao): string => $execucao->id_empresa.':'.$execucao->id_usuario_responsavel;

        $jornadas = DB::table('rotinas_execucoes as e')
            ->join('rotinas as r', 'r.id_rotina', '=', 'e.id_rotina')
            ->where('r.tipo_rotina', ExecucoesDiarias::TIPO_DIARIA)
            ->whereNull('r.excluida_em')
            ->whereIn('e.id_empresa', $this->empresas($usuario))
            ->when(! $usuario->isAdmin(), fn (Builder $consulta) => $consulta->where('e.id_usuario_responsavel', $usuario->getKey()))
            ->where('e.agenda_ate', '>', $agora)
            ->groupBy('e.id_empresa', 'e.id_usuario_responsavel', 'e.agenda_ate')
            ->select('e.id_empresa', 'e.id_usuario_responsavel')
            ->selectRaw('MIN(e.previsto_em) as primeira_tarefa')
            ->selectRaw('MIN(e.created_at) as primeira_entrada')
            ->orderBy('primeira_tarefa')
            ->get()
            ->keyBy($chaveJornada)
            ->map(function (stdClass $jornada): CarbonImmutable {
                $inicioPrevisto = CarbonImmutable::parse($jornada->primeira_tarefa, 'UTC')
                    ->subMinutes(ExecucoesDiarias::ANTECEDENCIA_MINUTOS);

                return $jornada->primeira_entrada
                    ? min($inicioPrevisto, CarbonImmutable::parse($jornada->primeira_entrada, 'UTC'))
                    : $inicioPrevisto;
            });

        $desde = $jornadas->values()->push($inicioDoDia)->min();

        return $this->execucoesPessoais($usuario)
            ->where('e.status', 'concluida')
            ->whereBetween('e.concluido_em', [$desde, $agora])
            ->get()
            ->filter(function (stdClass $execucao) use ($chaveJornada, $jornadas, $inicioDoDia, $fimDoDia): bool {
                $concluidoEm = CarbonImmutable::parse($execucao->concluido_em, 'UTC');
                $inicioDaJornada = $jornadas->get($chaveJornada($execucao));
                if ($execucao->tipo_rotina === ExecucoesDiarias::TIPO_EMERGENCIAL) {
                    return $concluidoEm >= min($inicioDoDia, $inicioDaJornada ?? $inicioDoDia);
                }
                if ($inicioDaJornada) {
                    return $concluidoEm >= $inicioDaJornada;
                }
                if ($execucao->agenda_ate !== null) {
                    return false;
                }

                $previstoEm = CarbonImmutable::parse($execucao->previsto_em, 'UTC');

                return $previstoEm >= $inicioDoDia && $previstoEm < $fimDoDia && $concluidoEm >= $inicioDoDia;
            });
    }

    /** @return array{empresas: Collection, unidadePadrao: ?int} */
    public function unidadesParaCadastro(User $usuario): array
    {
        $empresas = DB::table('empresas')->whereIn('id_empresa', $this->empresas($usuario))
            ->orderBy('empresa')->get(['id_empresa', 'empresa']);
        $vinculada = DB::table('empresa_usuario')->where('id_usuario', $usuario->getKey())
            ->orderBy('id_empresa')->value('id_empresa');
        $padrao = $vinculada ?? ($empresas->count() === 1 ? $empresas->first()->id_empresa : null);

        return ['empresas' => $empresas, 'unidadePadrao' => $padrao === null ? null : (int) $padrao];
    }

    public function catalogo(string $tabela, User $usuario): Builder
    {
        $chave = self::CHAVES_CATALOGO[$tabela];

        return DB::table($tabela.' as c')->when(! $usuario->isAdmin(), fn (Builder $consulta) => $consulta
            ->whereExists(fn (Builder $vinculo) => $vinculo->from($tabela.'_empresas as v')
                ->whereColumn('v.'.$chave, 'c.'.$chave)
                ->whereIn('v.id_empresa', $this->empresas($usuario))));
    }

    public function catalogoNaUnidade(string $tabela, int $unidade): Builder
    {
        $chave = self::CHAVES_CATALOGO[$tabela];

        return DB::table($tabela.' as c')->join($tabela.'_empresas as v', 'v.'.$chave, '=', 'c.'.$chave)
            ->where('v.id_empresa', $unidade)->where('c.status', true);
    }

    /** @return Collection<int, stdClass> */
    public function comUnidades(string $tabela, User $usuario, Collection $registros): Collection
    {
        if ($registros->isEmpty()) {
            return $registros;
        }
        $chave = self::CHAVES_CATALOGO[$tabela];
        $vinculos = DB::table($tabela.'_empresas as v')->join('empresas as e', 'e.id_empresa', '=', 'v.id_empresa')
            ->whereIntegerInRaw('v.'.$chave, $registros->pluck($chave)->all());
        $totais = $usuario->isAdmin() ? collect() : (clone $vinculos)->groupBy('v.'.$chave)
            ->select('v.'.$chave)->selectRaw('COUNT(*) as total')->pluck('total', $chave);
        $unidades = $vinculos->whereIn('v.id_empresa', $this->empresas($usuario))
            ->orderBy('e.empresa')->get(['v.'.$chave, 'v.id_empresa', 'e.empresa'])->groupBy($chave);

        foreach ($registros as $registro) {
            $registro->unidades = $unidades->get($registro->{$chave}, collect());
            $registro->ids_empresas = $registro->unidades->pluck('id_empresa')->map(fn ($id): int => (int) $id)->all();
            $registro->editavel = $usuario->isAdmin() || $registro->unidades->count() === (int) $totais->get($registro->{$chave});
        }

        return $registros;
    }

    /** @return array{empresas: Collection, unidadePadrao: ?int, tarefas: Collection, locais: Collection} */
    public function catalogos(User $usuario): array
    {
        return [
            ...$this->unidadesParaCadastro($usuario),
            'tarefas' => $this->comUnidades('tarefas', $usuario, $this->catalogo('tarefas', $usuario)
                ->where('c.status', true)->orderBy('c.tarefa')->get(['c.id_tarefa', 'c.tarefa'])),
            'locais' => $this->comUnidades('locais', $usuario, $this->catalogo('locais', $usuario)
                ->where('c.status', true)->orderBy('c.local')->get(['c.id_local', 'c.local'])),
        ];
    }

    /** @return Collection<int, stdClass> */
    public function ferramentasParaCadastro(User $usuario): Collection
    {
        return $this->comUnidades('ferramentas', $usuario, $this->catalogo('ferramentas', $usuario)
            ->orderBy('c.ferramenta')->get(['c.id_ferramenta', 'c.ferramenta', 'c.status']));
    }
}
