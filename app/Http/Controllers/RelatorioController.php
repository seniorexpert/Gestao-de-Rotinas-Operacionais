<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ConsultaRotinas;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RelatorioController extends Controller
{
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
}
