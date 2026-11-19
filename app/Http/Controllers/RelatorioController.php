<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ConsultaRotinas;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatorioController extends Controller
{
    private const EVENTO = 'COALESCE(a.descricao, a.acao)';

    private const GRUPO = 'COALESCE(g.grupo_acesso, u.grupo_acesso_nome)';

    public function __construct(private ConsultaRotinas $consultaRotinas) {}

    public function tarefas(): View
    {
        $tarefas = DB::table('tarefas as t')
            ->leftJoin('ferramentas as f', 't.id_ferramenta', '=', 'f.id_ferramenta')
            ->orderBy('t.id_tarefa')->get(['t.*', 'f.ferramenta']);
        $ferramentas = DB::table('ferramentas')->orderBy('ferramenta')->get(['id_ferramenta', 'ferramenta']);

        return view('relatorios.tarefas', compact('tarefas', 'ferramentas'));
    }

    public function locais(): View
    {
        return view('relatorios.locais', ['locais' => DB::table('locais')->orderBy('id_local')->get()]);
    }

    public function ferramentas(): View
    {
        return view('relatorios.ferramentas', ['ferramentas' => DB::table('ferramentas')->orderBy('id_ferramenta')->get()]);
    }

    public function rotinas(Request $request): View
    {
        $colaboradorFiltro = $request->validate(['colaborador' => 'nullable|string|max:255'])['colaborador'] ?? null;
        $usuario = $request->user();
        $rotinas = $this->consultaRotinas->rotinas($usuario)
            ->when($colaboradorFiltro && $colaboradorFiltro !== 'Todas', fn (Builder $query) => $query->where('u.usuario', $colaboradorFiltro))
            ->select('r.id_usuario', 'u.usuario as nome_colaborador', 'e.empresa as unidade_colaborador', 'g.grupo_acesso as cargo_colaborador')
            ->selectRaw('MIN(r.id_rotina) as id_rotina_principal, MIN(r.horario) as horario_inicio, MAX(r.horario) as horario_fim')
            ->selectRaw('COUNT(r.id_rotina) as total_tarefas, MAX(CASE WHEN r.status = 1 THEN 1 ELSE 0 END) as status_rotina')
            ->groupBy('r.id_usuario', 'u.usuario', 'e.id_empresa', 'e.empresa', 'g.grupo_acesso')
            ->orderBy('u.usuario')->orderBy('e.empresa')->get();
        $colaboradores = $this->nomesDosColaboradores($usuario);

        return view('relatorios.rotinas', [
            ...$this->consultaRotinas->catalogos($usuario),
            'rotinas' => $rotinas,
            'colaboradores' => $colaboradores,
            'colaboradorFiltro' => $colaboradorFiltro,
        ]);
    }

    public function rotinasExcel(Request $request): View
    {
        return view('relatorios.rotinas-excel', $this->gradeDeRotinas($request));
    }

    public function exportarExcel(Request $request): StreamedResponse
    {
        ['colaboradores' => $colaboradores, 'horarios' => $horarios, 'rotinas' => $rotinas] = $this->gradeDeRotinas($request);
        $porHorario = $rotinas->groupBy(['horario', 'id_usuario']);

        return response()->streamDownload(function () use ($colaboradores, $horarios, $porHorario) {
            echo '<table border="1"><thead><tr><th>HORA</th>';
            foreach ($colaboradores as $colaborador) {
                echo '<th>'.e($colaborador->usuario).'</th>';
            }
            echo '</tr></thead><tbody>';

            foreach ($horarios as $horario) {
                echo '<tr><th>'.e($horario).'</th>';
                foreach ($colaboradores as $colaborador) {
                    echo '<td>';
                    foreach ($porHorario->get($horario)?->get($colaborador->id_usuario, []) ?? [] as $tarefa) {
                        echo '<div><b>'.e($tarefa->tarefa ?? 'Tarefa de rotina').'</b><br>'.e($tarefa->dias_semanas).'</div>';
                    }
                    echo '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }, 'relatorio-rotinas-grade-'.now()->format('Y-m-d').'.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=utf-8']);
    }

    public function logsRotinas(Request $request): View
    {
        $filtros = $this->filtrosDosLogs($request);
        $usuario = $request->user();
        $auditorias = $this->auditoriasDaUnidade($usuario);
        $gruposAcesso = (clone $auditorias)->selectRaw(self::GRUPO.' as grupo_acesso')
            ->distinct()->pluck('grupo_acesso')->filter()->sort()->values();
        $descricoesEventos = (clone $auditorias)->selectRaw(self::EVENTO.' as evento')
            ->distinct()->pluck('evento')->filter()->sort()->values();

        return view('relatorios.logs-rotinas', [
            'logs' => $this->projetarLogs($this->filtrarLogs($auditorias, $filtros))->latest('a.id_log')->paginate(10)->withQueryString(),
            'gruposAcesso' => $gruposAcesso,
            'colaboradores' => $this->nomesDosColaboradores($usuario),
            'descricoesEventos' => $descricoesEventos,
            'dataCriacao' => $filtros['data_criacao'] ?? null,
            'dataEvento' => $filtros['data_evento'] ?? null,
            'nomeExecutor' => $filtros['nome_executor'] ?? null,
            'tarefaFiltro' => $filtros['tarefa'] ?? null,
            'descricaoEvento' => $filtros['descricao_evento'] ?? null,
            'grupoAcesso' => $filtros['grupo_acesso'] ?? null,
            'colaboradorOriginal' => $filtros['colaborador_original'] ?? null,
        ]);
    }

    public function apiLogDetalhes(Request $request, int $id): JsonResponse
    {
        $log = $this->projetarLogs($this->auditoriasDaUnidade($request->user()))->where('a.id_log', $id)->first();

        return $log ? response()->json($log) : response()->json(['error' => 'Log não encontrado'], 404);
    }

    public function exportarExcelLogs(Request $request): StreamedResponse
    {
        $logs = $this->projetarLogs($this->filtrarLogs($this->auditoriasDaUnidade($request->user()), $this->filtrosDosLogs($request)))
            ->latest('a.id_log')->cursor();

        return response()->streamDownload(function () use ($logs) {
            echo '<table border="1"><thead><tr><th>ID</th><th>SITUAÇÃO</th><th>EVENTO</th><th>DATA DO EVENTO</th>'
                .'<th>TAREFA</th><th>DETALHE TAREFA</th><th>COLABORADOR(A)</th><th>GRUPO DE ACESSO</th><th>EXECUTOR</th></tr></thead><tbody>';
            foreach ($logs as $log) {
                $valores = [
                    '#'.$log->id, $log->situacao, $log->descricao_evento, $log->data_evento,
                    $log->tarefa, $log->detalhe_tarefa, $log->colaborador_original, $log->grupo_acesso, $log->executor,
                ];
                echo '<tr>';
                foreach ($valores as $valor) {
                    echo '<td>'.e($valor ?? '').'</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }, 'relatorio-logs-rotinas-'.now()->format('Y-m-d').'.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=utf-8']);
    }

    public function rotinasFinalizadas(): View
    {
        return view('relatorios.rotinas-finalizadas');
    }

    public function estatisticas(): View
    {
        return view('relatorios.estatisticas');
    }

    /** @return array{colaboradores: Collection, rotinas: Collection, horarios: Collection, todosColaboradoresFiltro: Collection, colaboradorFiltro: ?string} */
    private function gradeDeRotinas(Request $request): array
    {
        $colaboradorFiltro = $request->validate(['colaborador' => 'nullable|string|max:255'])['colaborador'] ?? null;
        $usuario = $request->user();
        $colaboradores = $this->consultaRotinas->colaboradores($usuario)
            ->when($colaboradorFiltro && $colaboradorFiltro !== 'Todas', fn (Builder $query) => $query->where('u.usuario', $colaboradorFiltro))
            ->get();
        $rotinas = $this->consultaRotinas->rotinas($usuario)->where('r.status', true)
            ->whereIn('r.id_usuario', $colaboradores->pluck('id_usuario'))->orderBy('r.horario')
            ->get(['r.id_usuario', 'r.horario', 'r.dias_semanas', 'r.morador', 'r.local', 'l.local as local_cadastrado', 't.tarefa', 't.descricao']);

        foreach ($rotinas as $rotina) {
            $rotina->horario = substr($rotina->horario, 0, 5);
            $rotina->local = $rotina->local ?: $rotina->local_cadastrado;
        }

        return [
            'colaboradores' => $colaboradores,
            'rotinas' => $rotinas,
            'horarios' => $rotinas->pluck('horario')->unique()->values(),
            'todosColaboradoresFiltro' => $this->nomesDosColaboradores($usuario),
            'colaboradorFiltro' => $colaboradorFiltro,
        ];
    }

    private function nomesDosColaboradores(User $usuario): Collection
    {
        return $this->consultaRotinas->colaboradores($usuario)->pluck('u.usuario')->unique()->values();
    }

    private function auditoriasDaUnidade(User $usuario): Builder
    {
        return DB::table('logs_auditoria as a')
            ->join('rotinas as r', fn (JoinClause $join) => $join
                ->on('a.registro_id', '=', DB::raw('CAST(r.id_rotina AS VARCHAR(32))'))
                ->where('a.tabela', 'rotinas'))
            ->leftJoin('tarefas as t', 'r.id_tarefa', '=', 't.id_tarefa')
            ->leftJoin('usuarios as u', 'r.id_usuario', '=', 'u.id_usuario')
            ->leftJoin('grupos_acesso as g', 'u.id_grupo_acesso', '=', 'g.id_grupo_acesso')
            ->leftJoin('usuarios as executor', 'a.id_usuario', '=', 'executor.id_usuario')
            ->whereIn('r.id_empresa', $this->consultaRotinas->empresas($usuario));
    }

    private function projetarLogs(Builder $query): Builder
    {
        return $query->select(
            'a.id_log as id', 'a.created_at', 'a.created_at as data_evento',
            't.tarefa', 'r.obs as detalhe_tarefa', 'u.usuario as colaborador_original', 'executor.usuario as executor',
        )->selectRaw(self::EVENTO.' as descricao_evento, '.self::GRUPO.' as grupo_acesso')
            ->selectRaw("CASE WHEN r.status = 1 THEN 'Ativa' ELSE 'Inativa' END as situacao");
    }

    /** @return array{data_criacao?: ?string, data_evento?: ?string, nome_executor?: ?string, tarefa?: ?string, descricao_evento?: ?string, grupo_acesso?: ?string, colaborador_original?: ?string} */
    private function filtrosDosLogs(Request $request): array
    {
        return $request->validate([
            'data_criacao' => 'nullable|date_format:Y-m-d',
            'data_evento' => 'nullable|date_format:Y-m-d',
            'nome_executor' => 'nullable|string|max:255',
            'tarefa' => 'nullable|string|max:255',
            'descricao_evento' => 'nullable|string',
            'grupo_acesso' => 'nullable|string|max:255',
            'colaborador_original' => 'nullable|string|max:255',
        ]);
    }

    private function filtrarLogs(Builder $query, array $filtros): Builder
    {
        foreach (['data_criacao', 'data_evento'] as $campo) {
            if (! empty($filtros[$campo])) {
                $query->whereDate('a.created_at', $filtros[$campo]);
            }
        }

        foreach (['nome_executor' => 'executor.usuario', 'tarefa' => 't.tarefa'] as $campo => $coluna) {
            if (! empty($filtros[$campo])) {
                $query->where($coluna, 'like', '%'.$filtros[$campo].'%');
            }
        }

        if (! empty($filtros['descricao_evento']) && $filtros['descricao_evento'] !== 'Todas') {
            $query->whereRaw(self::EVENTO.' = ?', [$filtros['descricao_evento']]);
        }
        if (! empty($filtros['grupo_acesso']) && $filtros['grupo_acesso'] !== 'Todos') {
            $query->whereRaw(self::GRUPO.' = ?', [$filtros['grupo_acesso']]);
        }
        if (! empty($filtros['colaborador_original']) && $filtros['colaborador_original'] !== 'Todos') {
            $query->where('u.usuario', $filtros['colaborador_original']);
        }

        return $query;
    }
}
