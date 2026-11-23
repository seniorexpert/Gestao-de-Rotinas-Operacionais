<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use App\Services\CalculadoraSla;
use App\Services\ConsultaRotinas;
use App\Services\ExecucoesDiarias;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class MonitoramentoController extends Controller
{
    public function __construct(
        private ConsultaRotinas $consultaRotinas,
        private ExecucoesDiarias $execucoesDiarias,
        private CalculadoraSla $sla,
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
        $execucoes = $this->consultaRotinas->execucoesDoDia($usuario, $agora)
            ->whereIn('e.status', ['pendente', 'em_andamento', 'concluida', 'nao_realizada'])
            ->orderBy('e.previsto_em')->orderBy('e.id_execucao')->get();

        foreach ($execucoes as $execucao) {
            $execucao->horario = CarbonImmutable::parse($execucao->previsto_em, 'UTC')
                ->setTimezone(ExecucoesDiarias::FUSO)->format('H:i');
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
        $data = $agora->setTimezone(ExecucoesDiarias::FUSO)->format('d/m/Y');

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
            $rotinas = DB::table('rotinas')
                ->whereIn('id_empresa', $this->consultaRotinas->empresas($request->user()))
                ->whereIn('id_rotina', $dados['rotinas_ids'])
                ->lockForUpdate()
                ->get(['id_rotina', 'id_empresa', 'id_usuario']);

            abort_unless($rotinas->count() === count($dados['rotinas_ids']), 404);

            $empresas = $rotinas->pluck('id_empresa')->unique();
            $totalVinculos = DB::table('empresa_usuario')
                ->where('id_usuario', $dados['novo_colaborador_id'])
                ->whereIn('id_empresa', $empresas)
                ->count();

            if ($totalVinculos !== $empresas->count()) {
                throw ValidationException::withMessages([
                    'novo_colaborador_id' => 'O colaborador deve estar vinculado às unidades das rotinas selecionadas.',
                ]);
            }

            $novoResponsavel = (int) $dados['novo_colaborador_id'];
            $alteradas = $rotinas->reject(fn (object $rotina): bool => (int) $rotina->id_usuario === $novoResponsavel);
            $ids = $alteradas->pluck('id_rotina');
            $agora = now()->toImmutable();
            [$inicioDoDia] = ExecucoesDiarias::janela($agora);

            DB::table('rotinas')->whereIn('id_rotina', $ids)
                ->update(['id_usuario' => $novoResponsavel, 'updated_at' => $agora]);
            DB::table('rotinas_execucoes')->whereIn('id_rotina', $ids)
                ->where('previsto_em', '>=', $inicioDoDia)
                ->where('status', 'pendente')->whereNull('iniciado_em')->whereNull('concluido_em')
                ->update(['id_usuario_responsavel' => $novoResponsavel, 'updated_at' => $agora]);

            foreach ($alteradas as $rotina) {
                AuditoriaService::registrar(
                    'UPDATE', 'rotinas', (string) $rotina->id_rotina,
                    ['id_usuario' => $rotina->id_usuario], ['id_usuario' => $novoResponsavel], 'Responsável da rotina alterado',
                    idEmpresa: (int) $rotina->id_empresa,
                    idRotina: (int) $rotina->id_rotina,
                );
            }
        });

        return back()->with('success', 'Tarefas atribuídas com sucesso!');
    }
}
