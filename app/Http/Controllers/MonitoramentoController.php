<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use App\Services\CalculadoraSla;
use App\Services\ConsultaRotinas;
use App\Services\ExecucoesDiarias;
use App\Services\VisibilidadeGrupos;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MonitoramentoController extends Controller
{
    public function __construct(
        private ConsultaRotinas $consultaRotinas,
        private ExecucoesDiarias $execucoesDiarias,
        private CalculadoraSla $sla,
        private VisibilidadeGrupos $visibilidade,
    ) {}

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $agora = now()->toImmutable();
        $this->execucoesDiarias->gerar($agora, $this->consultaRotinas->empresas($usuario));

        $rotinas = $this->consultaRotinas->rotinas($usuario)->orderBy('r.horario')->get([
            'r.id_rotina', 'r.id_usuario', 'r.horario', 'r.morador', 'r.status',
            'r.local', 'l.local as local_cadastrado', 'u.usuario', 't.tarefa',
        ]);
        $execucoes = $this->consultaRotinas->execucoesDaAgenda($usuario, $agora)
            ->whereIn('e.status', ['pendente', 'em_andamento', 'concluida', 'nao_realizada'])
            ->orderBy('e.previsto_em')->orderBy('e.id_execucao')->get();

        $hoje = $agora->setTimezone(ExecucoesDiarias::FUSO);
        foreach ($execucoes as $execucao) {
            $previsto = CarbonImmutable::parse($execucao->previsto_em, 'UTC')->setTimezone(ExecucoesDiarias::FUSO);
            $execucao->horario = $previsto->format($previsto->isSameDay($hoje) ? 'H:i' : 'H:i · d/m');
            $execucao->limite = null;
            $execucao->situacao_sla = 'semDados';
            if ($execucao->status === 'nao_realizada') {
                continue;
            }

            try {
                $resultado = $this->sla->calcular($execucao, $agora);
                $execucao->situacao_sla = $resultado['sla_atual'];
                $execucao->limite = $resultado['prazo_em']->setTimezone(ExecucoesDiarias::FUSO)->format('d/m H:i:s');
            } catch (InvalidArgumentException) {
                // A execução permanece visível para revisão dos dados de SLA.
            }
        }

        $abertas = $execucoes->whereIn('status', ['pendente', 'em_andamento']);
        $situacoes = $abertas->countBy('situacao_sla');
        $produtividades = $execucoes->groupBy('id_usuario_responsavel')->map(function ($tarefas) {
            $concluidas = $tarefas->where('status', 'concluida')->count();
            $primeira = $tarefas->first();

            return (object) [
                'colaborador_id' => $primeira->id_usuario_responsavel,
                'colaborador_nome' => $primeira->responsavel ?? 'Colaborador não localizado',
                'unidades' => $tarefas->pluck('empresa')->filter()->unique()->implode(' · '),
                'total_tarefas' => $tarefas->count(),
                'concluidas' => $concluidas,
                'percentual' => (int) round(100 * $concluidas / $tarefas->count()),
            ];
        })->sortBy('colaborador_nome', SORT_NATURAL | SORT_FLAG_CASE);
        $colaboradores = $this->consultaRotinas->colaboradores($usuario)->get();
        $data = $hoje->format('d/m/Y');

        return view('sla.centro-monitoramento', compact('produtividades', 'rotinas', 'colaboradores', 'execucoes', 'abertas', 'situacoes', 'data'));
    }

    public function atribuir(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'rotinas_ids' => 'required|array|min:1',
            'rotinas_ids.*' => 'required|integer|distinct',
            'novo_colaborador_id' => 'required|integer|exists:usuarios,id_usuario',
        ]);

        DB::transaction(function () use ($request, $dados) {
            $rotinas = $this->consultaRotinas->cadastrosDeRotinas($request->user())
                ->whereIn('id_rotina', $dados['rotinas_ids'])
                ->orderBy('id_rotina')
                ->lockForUpdate()
                ->get(['id_rotina', 'id_empresa', 'id_usuario', 'grupo_rotina']);

            abort_unless($rotinas->count() === count($dados['rotinas_ids']), 404);

            $empresas = $rotinas->pluck('id_empresa')->unique();
            $totalVinculos = DB::table('empresa_usuario')
                ->where('id_usuario', $dados['novo_colaborador_id'])
                ->when($this->visibilidade->colaboradores($request->user()), fn (Builder $vinculos, Builder $visiveis) => $vinculos->whereIn('id_usuario', $visiveis))
                ->whereIn('id_empresa', $empresas)
                ->count();

            if ($totalVinculos !== $empresas->count()) {
                throw ValidationException::withMessages([
                    'novo_colaborador_id' => 'O colaborador deve estar vinculado às unidades das rotinas selecionadas.',
                ]);
            }

            $novoResponsavel = (int) $dados['novo_colaborador_id'];
            $alteradas = $rotinas->reject(fn (object $rotina): bool => (int) $rotina->id_usuario === $novoResponsavel);
            $agora = now('UTC')->toImmutable();

            foreach ($alteradas->groupBy('grupo_rotina') as $grupo => $tarefas) {
                DB::table('rotinas')->whereIntegerInRaw('id_rotina', $tarefas->pluck('id_rotina')->all())
                    ->update([
                        'id_usuario' => $novoResponsavel,
                        'grupo_rotina' => $grupo === '' ? null : (string) Str::uuid(),
                        'updated_at' => $agora,
                    ]);
            }
            if ($alteradas->isNotEmpty()) {
                $this->execucoesDiarias->reatribuirPendentes($alteradas->pluck('id_rotina')->all(), $novoResponsavel, $agora);
            }

            foreach ($alteradas as $rotina) {
                AuditoriaService::registrar(
                    'UPDATE', 'rotinas', (string) $rotina->id_rotina,
                    ['id_usuario' => $rotina->id_usuario], ['id_usuario' => $novoResponsavel], 'Responsável da rotina alterado',
                    idEmpresa: (int) $rotina->id_empresa,
                    idRotina: (int) $rotina->id_rotina,
                );
            }
        }, 3);

        return back()->with('success', 'Tarefas atribuídas com sucesso!');
    }
}
