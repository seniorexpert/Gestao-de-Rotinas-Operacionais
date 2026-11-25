<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ConsultaRotinas;
use App\Services\ExecucoesDiarias;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatorioController extends Controller
{
    private const INICIO_DIA_GRADE = '06:00';

    public function __construct(private ConsultaRotinas $consultaRotinas) {}

    public function tarefas(Request $request): View
    {
        $usuario = $request->user();
        $ferramentas = $this->consultaRotinas->ferramentasParaCadastro($usuario);
        $tarefas = $this->consultaRotinas->comUnidades('tarefas', $usuario, $this->consultaRotinas->catalogo('tarefas', $usuario)
            ->orderBy('c.id_tarefa')->get(['c.*']));
        $ferramentasPorId = $ferramentas->keyBy('id_ferramenta');
        foreach ($tarefas as $tarefa) {
            $ferramenta = $ferramentasPorId->get($tarefa->id_ferramenta);
            $tarefa->ferramenta = array_intersect($tarefa->ids_empresas, $ferramenta->ids_empresas ?? []) !== []
                ? $ferramenta->ferramenta
                : null;
        }

        return view('relatorios.tarefas', [
            ...$this->consultaRotinas->unidadesParaCadastro($usuario),
            'tarefas' => $tarefas,
            'ferramentas' => $ferramentas,
        ]);
    }

    public function locais(Request $request): View
    {
        $usuario = $request->user();

        return view('relatorios.locais', [
            ...$this->consultaRotinas->unidadesParaCadastro($usuario),
            'locais' => $this->consultaRotinas->comUnidades('locais', $usuario, $this->consultaRotinas->catalogo('locais', $usuario)
                ->orderBy('c.id_local')->get(['c.*'])),
        ]);
    }

    public function ferramentas(Request $request): View
    {
        $usuario = $request->user();

        return view('relatorios.ferramentas', [
            ...$this->consultaRotinas->unidadesParaCadastro($usuario),
            'ferramentas' => $this->consultaRotinas->ferramentasParaCadastro($usuario),
        ]);
    }

    public function rotinas(Request $request, ExecucoesDiarias $execucoes): View
    {
        $filtros = $request->validate([
            'colaborador' => 'nullable|string|max:255', 'morador' => 'nullable|string|max:255',
            'editar' => 'nullable|string|max:36', 'tarefa' => 'nullable|integer|min:1',
            'adicionar_tarefa' => 'nullable|boolean',
        ], [
            'morador.string' => 'Informe o nome do morador em texto.',
            'morador.max' => 'Digite até 255 caracteres para buscar por morador.',
        ]);
        $colaboradorFiltro = $filtros['colaborador'] ?? null;
        $moradorFiltro = trim($filtros['morador'] ?? '');
        $filtrosRelatorio = array_filter([
            'colaborador' => $colaboradorFiltro === 'Todas' ? null : $colaboradorFiltro,
            'morador' => $moradorFiltro,
        ], filled(...));
        $usuario = $request->user();
        $grupoEdicao = $filtros['editar'] ?? null;
        $tarefasEdicao = collect();
        $colaboradoresEdicao = collect();
        $edicaoTarefa = null;
        $rotinaParaAdicionarTarefa = null;
        if ($grupoEdicao !== null) {
            Gate::authorize('editar-modulo', 'rotinas');
            $tarefasEdicao = $this->consultaRotinas->grupoDaRotina($this->consultaRotinas->rotinas($usuario), $grupoEdicao)
                ->orderBy('r.horario')->orderBy('r.id_rotina')->get([
                    'r.id_rotina', 'r.id_usuario', 'r.id_empresa', 'r.status', 'r.horario', 'r.dias_semanas', 'r.morador',
                    'r.id_tarefa', 'r.id_local', 'r.auxilio', 'r.obs',
                    'r.local as local_rotina', 'l.local as local_cadastrado', 't.tarefa', 't.icone', 't.cor_icone', 'e.empresa',
                ]);
            abort_if($tarefasEdicao->isEmpty(), 404);
            $rotina = $tarefasEdicao->first();
            $colaboradoresEdicao = $this->consultaRotinas->colaboradores($usuario, (int) $rotina->id_empresa)->get();
            foreach ($tarefasEdicao as $tarefa) {
                $tarefa->dias_programados = $execucoes->diasDaSemana($tarefa->dias_semanas);
            }

            if (isset($filtros['tarefa'])) {
                $tarefa = $tarefasEdicao->firstWhere('id_rotina', $filtros['tarefa']);
                abort_unless($tarefa, 404);
                $edicaoTarefa = [
                    'registro' => $tarefa,
                    'dias' => $tarefa->dias_programados,
                    'auxiliares' => DB::table('rotina_auxiliares')->where('id_rotina', $tarefa->id_rotina)->pluck('id_usuario')->all(),
                ];
            } elseif ($request->boolean('adicionar_tarefa')) {
                $rotinaParaAdicionarTarefa = $rotina;
            }
        }

        $rotinas = $this->consultaRotinas->rotinasDiarias($usuario)
            ->when($colaboradorFiltro && $colaboradorFiltro !== 'Todas', fn (Builder $query) => $query->where('u.usuario', $colaboradorFiltro))
            ->select('r.id_usuario', 'r.grupo_rotina', 'u.usuario as nome_colaborador', 'e.empresa as unidade_colaborador', 'g.grupo_acesso as cargo_colaborador')
            ->selectRaw('MIN(r.id_rotina) as id_rotina_principal, MIN(r.horario) as horario_inicio, MAX(r.horario) as horario_fim')
            ->selectRaw('COUNT(r.id_rotina) as total_tarefas, MAX(CASE WHEN r.status = 1 THEN 1 ELSE 0 END) as status_rotina')
            ->selectRaw('MIN(CASE WHEN r.status = 1 THEN 1 ELSE 0 END) as todas_ativas')
            ->groupBy('r.grupo_rotina', 'r.id_usuario', 'u.usuario', 'e.id_empresa', 'e.empresa', 'g.grupo_acesso')
            ->groupByRaw('CASE WHEN r.grupo_rotina IS NULL THEN r.id_rotina END')
            ->when($moradorFiltro !== '', fn (Builder $consulta) => $this->consultaRotinas->filtrarPorMorador($consulta, $moradorFiltro, rotinaInteira: true))
            ->orderBy('u.usuario')->orderBy('e.empresa')->orderBy('id_rotina_principal')->get();
        $colaboradores = $this->nomesDosColaboradores($usuario);

        return view('relatorios.rotinas', [
            ...$this->consultaRotinas->catalogos($usuario),
            'rotinas' => $rotinas,
            'colaboradores' => $colaboradores,
            'colaboradorFiltro' => $colaboradorFiltro,
            'moradorFiltro' => $moradorFiltro,
            'filtrosRelatorio' => $filtrosRelatorio,
            'grupoEdicao' => $grupoEdicao,
            'tarefasEdicao' => $tarefasEdicao,
            'colaboradoresEdicao' => $colaboradoresEdicao,
            'edicaoTarefa' => $edicaoTarefa,
            'rotinaParaAdicionarTarefa' => $rotinaParaAdicionarTarefa,
        ]);
    }

    public function rotinasFinalizadas(Request $request): View
    {
        $colaboradorFiltro = $request->validate(['colaborador' => 'nullable|string|max:255'])['colaborador'] ?? null;
        $usuario = $request->user();
        $rotinas = $this->consultaRotinas->rotinasDiarias($usuario)
            ->where('r.status', 1)
            ->when($colaboradorFiltro && $colaboradorFiltro !== 'Todas', fn (Builder $query) => $query->where('u.usuario', $colaboradorFiltro))
            ->select('r.id_usuario', 'u.usuario as nome_colaborador', 'e.empresa as unidade_colaborador', 'g.grupo_acesso as cargo_colaborador')
            ->selectRaw('MIN(r.id_rotina) as id_rotina_principal, MIN(r.horario) as horario_inicio, MAX(r.horario) as horario_fim')
            ->selectRaw('COUNT(r.id_rotina) as total_tarefas, MAX(CASE WHEN r.status = 1 THEN 1 ELSE 0 END) as status_rotina')
            ->groupBy('r.id_usuario', 'u.usuario', 'e.id_empresa', 'e.empresa', 'g.grupo_acesso')
            ->orderBy('u.usuario')->orderBy('e.empresa')->get();
        $colaboradores = $this->nomesDosColaboradores($usuario);

        return view('relatorios.rotinas-finalizadas', [
            ...$this->consultaRotinas->catalogos($usuario),
            'rotinas' => $rotinas,
            'colaboradores' => $colaboradores,
            'colaboradorFiltro' => $colaboradorFiltro,
        ]);
    }

    public function rotinasExcel(Request $request, ExecucoesDiarias $execucoes): View
    {
        return view('relatorios.rotinas-excel', $this->gradeDeRotinas($request, $execucoes));
    }

    public function exportarExcel(Request $request, ExecucoesDiarias $execucoes): StreamedResponse
    {
        $grade = $this->gradeDeRotinas($request, $execucoes);

        return response()->streamDownload(function () use ($grade): void {
            echo view('relatorios.rotinas-excel-exportacao', $grade)->render();
        }, 'relatorio-rotinas-grade-'.now(ExecucoesDiarias::FUSO)->format('Y-m-d').'.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=utf-8']);
    }

    public function estatisticas(): View
    {
        return view('relatorios.estatisticas');
    }

    /** @return array{colaboradores: Collection, horarios: Collection, tarefasPorHorario: Collection, opcoesColaboradores: Collection, grupos: Collection, filtros: array, totalTarefas: int, textoTotal: string} */
    private function gradeDeRotinas(Request $request, ExecucoesDiarias $execucoes): array
    {
        $filtrosValidados = $request->validate([
            'colaborador' => 'nullable|string|max:255',
            'morador' => 'nullable|string|max:255',
            'grupo' => 'nullable|integer|min:1',
        ]);
        $colaboradorFiltro = $filtrosValidados['colaborador'] ?? null;
        $filtros = array_filter([
            'colaborador' => $colaboradorFiltro === 'Todas' ? null : $colaboradorFiltro,
            'morador' => trim($filtrosValidados['morador'] ?? ''),
            'grupo' => isset($filtrosValidados['grupo']) ? (int) $filtrosValidados['grupo'] : null,
        ], filled(...));
        $usuario = $request->user();
        $opcoesColaboradores = $this->consultaRotinas->colaboradores($usuario)->addSelect('g.id_grupo_acesso')->get();
        $rotinas = $this->consultaRotinas->rotinasDiarias($usuario)->where('r.status', true)
            ->whereIntegerInRaw('r.id_usuario', $opcoesColaboradores->pluck('id_usuario')->all())
            ->when(isset($filtros['colaborador']), fn (Builder $consulta) => $consulta->where('u.usuario', $filtros['colaborador']))
            ->when(isset($filtros['grupo']), fn (Builder $consulta) => $consulta->where('g.id_grupo_acesso', $filtros['grupo']))
            ->when(isset($filtros['morador']), fn (Builder $consulta) => $this->consultaRotinas->filtrarPorMorador($consulta, $filtros['morador']))
            ->orderBy('r.horario')->orderBy('r.id_rotina')
            ->get([
                'r.id_usuario', 'r.horario', 'r.dias_semanas', 'r.morador', 'r.local', 'l.local as local_cadastrado',
                't.tarefa', 't.descricao', 't.icone', 't.cor_icone',
            ]);

        foreach ($rotinas as $rotina) {
            $rotina->horario = substr($rotina->horario, 0, 5);
            $rotina->tarefa = $rotina->tarefa ?? $rotina->descricao ?? 'Tarefa de rotina';
            $rotina->detalhes = implode(' · ', array_filter([trim((string) $rotina->morador), trim((string) ($rotina->local ?: $rotina->local_cadastrado))], filled(...)));
            $rotina->dias_formatados = count($execucoes->diasDaSemana($rotina->dias_semanas)) === 7 ? 'Todos os dias' : $rotina->dias_semanas;
        }

        [$horariosDoDia, $horariosDaMadrugada] = $rotinas->pluck('horario')->unique()->sort(SORT_STRING)
            ->partition(fn (string $horario): bool => $horario >= self::INICIO_DIA_GRADE);
        $horarios = $horariosDoDia->concat($horariosDaMadrugada)->values();
        $posicoes = $horarios->flip();
        $tarefasPorColaborador = $rotinas->sortBy(fn (object $rotina): int => $posicoes[$rotina->horario])->groupBy('id_usuario');
        $colaboradores = $opcoesColaboradores->whereIn('id_usuario', $tarefasPorColaborador->keys())->values();
        foreach ($colaboradores as $colaborador) {
            $colaborador->tarefas = $tarefasPorColaborador->get($colaborador->id_usuario);
            $quantidade = $colaborador->tarefas->count();
            $colaborador->texto_quantidade = $quantidade === 1 ? '1 tarefa' : "{$quantidade} tarefas";
            $colaborador->resumo = implode(' · ', array_filter([$colaborador->grupo_acesso, $colaborador->texto_quantidade], filled(...)));
        }

        $totalTarefas = $rotinas->count();

        return [
            'colaboradores' => $colaboradores,
            'horarios' => $horarios,
            'tarefasPorHorario' => $rotinas->groupBy(['horario', 'id_usuario']),
            'opcoesColaboradores' => $opcoesColaboradores->pluck('usuario')->unique()->values(),
            'grupos' => $opcoesColaboradores->whereNotNull('id_grupo_acesso')->unique('id_grupo_acesso')->sortBy('grupo_acesso'),
            'filtros' => $filtros,
            'totalTarefas' => $totalTarefas,
            'textoTotal' => $totalTarefas === 1 ? '1 tarefa encontrada' : "{$totalTarefas} tarefas encontradas",
        ];
    }

    private function nomesDosColaboradores(User $usuario): Collection
    {
        return $this->consultaRotinas->colaboradores($usuario)->pluck('u.usuario')->unique()->values();
    }
}
