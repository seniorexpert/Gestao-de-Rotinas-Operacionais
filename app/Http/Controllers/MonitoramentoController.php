<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\CalculadoraSla;
use App\Services\ConsultaRotinas;
use App\Services\EscalonamentoAcompanhamento;
use App\Services\ExecucoesDiarias;
use App\Services\VisibilidadeGrupos;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use stdClass;

class MonitoramentoController extends Controller
{
    private const PROCESSOS = [
        ExecucoesDiarias::TIPO_DIARIA => 'Rotinas Diárias',
        'acompanhamento' => 'Rotinas de Acompanhamento',
        'auxilio' => 'Solicitações de Auxílio',
        ExecucoesDiarias::TIPO_EMERGENCIAL => 'Tarefas Emergenciais',
        'nao_realizada' => 'Verificação de Tarefas Não realizadas',
    ];

    private const STATUS = [
        'pendente' => 'Pendentes',
        'em_andamento' => 'Em andamento',
        'concluida' => 'Concluídas',
        'nao_realizada' => 'Não realizadas',
        'todas' => 'Todas as tarefas',
    ];

    private const PROCESSOS_ATRIBUIVEIS = [ExecucoesDiarias::TIPO_DIARIA, ExecucoesDiarias::TIPO_EMERGENCIAL];

    private const STATUS_EM_ABERTO = ['pendente', 'em_andamento'];

    public function __construct(
        private ConsultaRotinas $consultaRotinas,
        private ExecucoesDiarias $execucoesDiarias,
        private CalculadoraSla $sla,
        private VisibilidadeGrupos $visibilidade,
        private EscalonamentoAcompanhamento $escalonamento,
    ) {}

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $verAcompanhamento = $usuario->can('visualizar-modulo', 'acompanhamento');
        $nomesProcessos = $verAcompanhamento ? self::PROCESSOS : array_diff_key(self::PROCESSOS, [
            'acompanhamento' => true, 'auxilio' => true, 'nao_realizada' => true,
        ]);
        $rotulosStatus = self::STATUS;
        $statusResumidos = array_diff_key($rotulosStatus, ['em_andamento' => true, 'nao_realizada' => true]);
        $statusDoFiltro = in_array($request->input('processo'), [null, ExecucoesDiarias::TIPO_DIARIA], true) ? $rotulosStatus : $statusResumidos;
        $filtros = array_filter($request->validate([
            'processo' => ['nullable', Rule::in(array_keys($nomesProcessos))],
            'status' => ['nullable', Rule::in(array_keys($statusDoFiltro))],
            'sla' => ['nullable', Rule::in([...array_keys(CalculadoraSla::ROTULOS), 'semDados'])],
        ]));

        $agora = now('UTC')->toImmutable();
        $hoje = $agora->setTimezone(ExecucoesDiarias::FUSO);
        $this->execucoesDiarias->gerar($agora, $this->consultaRotinas->empresas($usuario));
        $tarefas = $this->execucoesMonitoradas($usuario, $agora);
        if ($verAcompanhamento) {
            $tarefas = $tarefas->concat($this->acompanhamentosMonitorados($usuario, $agora));
        }

        $processoSelecionado = $filtros['processo'] ?? null;
        $statusSelecionado = $filtros['status'] ?? 'todas';
        $slaSelecionado = $filtros['sla'] ?? null;
        $tarefasDoStatus = $tarefas
            ->when($processoSelecionado, fn (Collection $itens) => $itens->where('processo', $processoSelecionado))
            ->when($statusSelecionado !== 'todas', fn (Collection $itens) => $itens
                ->filter(fn (stdClass $item): bool => $this->statusDoPainel($item) === $statusSelecionado));
        $situacoes = $tarefasDoStatus->countBy('situacao_sla');
        $execucoes = $tarefasDoStatus->when($slaSelecionado, fn (Collection $itens) => $itens->where('situacao_sla', $slaSelecionado))
            ->sortBy('previsto_em')->values();
        $produtividades = $this->produtividades($execucoes);

        $tarefasPorProcesso = $tarefas->when($slaSelecionado, fn (Collection $itens) => $itens->where('situacao_sla', $slaSelecionado))->groupBy('processo');
        $statusPorProcesso = collect($nomesProcessos)->map(fn (string $nome, string $processo): array => $processo === ExecucoesDiarias::TIPO_DIARIA ? $rotulosStatus : $statusResumidos);
        $processos = $statusPorProcesso->map(function (array $status, string $processo) use ($tarefasPorProcesso): Collection {
            $tarefas = $tarefasPorProcesso->get($processo, collect());

            return $tarefas->countBy($this->statusDoPainel(...))->only(array_keys($status))->put('todas', $tarefas->count());
        })->when($slaSelecionado, fn (Collection $processos) => $processos->filter(fn (Collection $status): bool => $status->get('todas') > 0));

        $data = $hoje->format('d/m/Y');

        return view('sla.centro-monitoramento', compact(
            'produtividades', 'execucoes', 'situacoes', 'data',
            'filtros', 'nomesProcessos', 'rotulosStatus', 'statusPorProcesso', 'processos',
        ));
    }

    public function processo(Request $request, string $processo): View
    {
        abort_unless(isset(self::PROCESSOS[$processo]), 404);

        $usuario = $request->user();
        $atribuiResponsavel = in_array($processo, self::PROCESSOS_ATRIBUIVEIS, true);
        $agora = now('UTC')->toImmutable();
        abort_unless($atribuiResponsavel || $usuario->can('visualizar-modulo', 'acompanhamento'), 403);

        $tarefas = ($atribuiResponsavel ? $this->execucoesMonitoradas($usuario, $agora)->where('pode_atribuir', true)
            : $this->acompanhamentosMonitorados($usuario, $agora)->where('status', 'pendente'))
            ->where('processo', $processo)->sortBy('previsto_em')->values();
        $rotulosStatus = $atribuiResponsavel ? ['pendente', 'em_andamento', 'todas'] : ['pendente', 'todas'];

        return view('sla.partials.atribuicao-processo', [
            'processo' => $processo,
            'nomeDoProcesso' => self::PROCESSOS[$processo],
            'atribuiResponsavel' => $atribuiResponsavel,
            'rotulosStatus' => array_intersect_key(self::STATUS, array_flip($rotulosStatus)),
            'tarefas' => $tarefas,
            'totais' => $tarefas->countBy('status')->put('todas', $tarefas->count()),
            'colaboradores' => $atribuiResponsavel && $tarefas->isNotEmpty() ? $this->consultaRotinas->colaboradores($usuario)->get() : collect(),
        ]);
    }

    private function statusDoPainel(stdClass $item): string
    {
        return $item->processo === ExecucoesDiarias::TIPO_EMERGENCIAL && $item->status === 'em_andamento' ? 'pendente' : $item->status;
    }

    /** @return Collection<int, stdClass> */
    private function execucoesMonitoradas(User $usuario, CarbonImmutable $agora): Collection
    {
        [$inicio, $fim] = ExecucoesDiarias::janela($agora);
        $hoje = $agora->setTimezone(ExecucoesDiarias::FUSO);
        $campos = ['t.icone', 't.cor_icone'];
        $encerradas = $this->consultaRotinas->execucoes($usuario)
            ->whereIn('e.status', ['concluida', 'nao_realizada'])
            ->where('e.concluido_em', '>=', $inicio)->where('e.concluido_em', '<', $fim)
            ->addSelect($campos);
        $execucoes = $this->consultaRotinas->execucoesDaAgenda($usuario, $agora, incluirAbertasAnteriores: true)
            ->whereIn('e.status', self::STATUS_EM_ABERTO)
            ->addSelect($campos)->unionAll($encerradas)->get();

        foreach ($execucoes as $execucao) {
            $previsto = CarbonImmutable::parse($execucao->previsto_em, 'UTC')->timezone(ExecucoesDiarias::FUSO);
            $execucao->processo = $execucao->tipo_rotina === ExecucoesDiarias::TIPO_EMERGENCIAL
                ? ExecucoesDiarias::TIPO_EMERGENCIAL : ExecucoesDiarias::TIPO_DIARIA;
            $execucao->horario = $this->horarioResumido($previsto, $hoje);
            $execucao->pode_atribuir = in_array($execucao->status, self::STATUS_EM_ABERTO, true)
                && $execucao->rotina_ativa && $execucao->concluido_em === null;
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

        return $execucoes;
    }

    /** @return Collection<int, stdClass> */
    private function acompanhamentosMonitorados(User $usuario, CarbonImmutable $agora): Collection
    {
        [$inicio, $fim] = ExecucoesDiarias::janela($agora);
        $hoje = $agora->timezone(ExecucoesDiarias::FUSO);
        $caixa = $usuario->isAdmin() ? null : $this->escalonamento->caixaDoUsuario($usuario);

        return $this->consultaRotinas->filaDeAcompanhamento($usuario, $agora)
            ->leftJoin('empresas as unidade', 'fila.id_empresa', '=', 'unidade.id_empresa')
            ->select('fila.*', 'unidade.empresa')
            ->when($this->visibilidade->colaboradores($usuario), fn (Builder $consulta, Builder $visiveis) => $consulta->whereIn('fila.id_usuario_responsavel', $visiveis))
            ->where(fn (Builder $fila) => $fila->whereNull('fila.verificado_em')
                ->orWhere(fn (Builder $verificadas) => $verificadas->where('fila.verificado_em', '>=', $inicio)->where('fila.verificado_em', '<', $fim)))
            ->cursor()->map(function (stdClass $item) use ($usuario, $agora, $hoje, $caixa): ?stdClass {
                $verificado = $item->verificado_em !== null;
                if ($verificado && ! $this->escalonamento->podeConsultarVerificacao($item, $caixa, $usuario)) {
                    return null;
                }

                $referencia = $verificado ? CarbonImmutable::parse($item->verificado_em, 'UTC') : $agora;
                try {
                    $recebimento = $this->escalonamento->recebimentoDoItem($item, $caixa, $referencia);
                } catch (InvalidArgumentException) {
                    $recebimento = null;
                }
                if (! $verificado && $recebimento === null) {
                    return null;
                }

                $auxilio = $item->tipo === 'auxilio';
                $item->processo = match ($item->tipo) {
                    'auxilio' => 'auxilio',
                    'nao_realizada' => 'nao_realizada',
                    default => 'acompanhamento',
                };
                $item->status = $verificado ? 'concluida' : 'pendente';
                $item->situacao_sla = $recebimento ? $this->escalonamento->classificarSla($recebimento, $referencia, $auxilio) : 'semDados';
                $item->limite = $recebimento ? $this->escalonamento->limite($recebimento, $auxilio)->timezone(ExecucoesDiarias::FUSO)->format('d/m H:i:s') : null;
                $item->horario = $this->horarioResumido(CarbonImmutable::parse($item->previsto_em, 'UTC')->timezone(ExecucoesDiarias::FUSO), $hoje);
                $item->responsavel = $item->colaborador;
                $item->pode_atribuir = false;

                return $item;
            })->filter()->collect();
    }

    private function horarioResumido(CarbonImmutable $data, CarbonImmutable $hoje): string
    {
        return $data->format($data->isSameDay($hoje) ? 'H:i' : 'H:i · d/m');
    }

    /** @param Collection<int, stdClass> $execucoes */
    private function produtividades(Collection $execucoes): Collection
    {
        return $execucoes->groupBy('id_usuario_responsavel')->map(function (Collection $tarefas): stdClass {
            $concluidas = $tarefas->where('status', 'concluida')->count();
            $primeira = $tarefas->first();

            return (object) [
                'colaborador_id' => $primeira->id_usuario_responsavel,
                'colaborador_nome' => $primeira->responsavel ?? 'Colaborador não localizado',
                'unidades' => $tarefas->pluck('empresa')->filter()->unique()->implode(' · '),
                'situacoes_sla' => $tarefas->countBy('situacao_sla'),
                'total_tarefas' => $tarefas->count(),
                'concluidas' => $concluidas,
                'percentual' => (int) round(100 * $concluidas / $tarefas->count()),
            ];
        })->sortBy('colaborador_nome', SORT_NATURAL | SORT_FLAG_CASE);
    }

    public function atribuir(Request $request): RedirectResponse|JsonResponse
    {
        $dados = $request->validate([
            'rotinas_ids' => 'required_without:execucoes_ids|prohibits:execucoes_ids|array|min:1',
            'rotinas_ids.*' => 'required|integer|distinct',
            'execucoes_ids' => 'sometimes|required|array|list|min:1',
            'execucoes_ids.*' => 'required|integer|min:1|distinct',
            'novo_colaborador_id' => 'required|integer|exists:usuarios,id_usuario',
            'processo' => ['required_with:execucoes_ids', 'filled', Rule::in(self::PROCESSOS_ATRIBUIVEIS)],
        ]);

        DB::transaction(function () use ($request, $dados) {
            $usuario = $request->user();
            $processo = $dados['processo'] ?? null;
            $agora = now('UTC')->toImmutable();
            $novoResponsavel = (int) $dados['novo_colaborador_id'];

            if (isset($dados['execucoes_ids'])) {
                $this->transferirExecucoes($usuario, $dados['execucoes_ids'], $processo, $novoResponsavel, $agora);

                return;
            }

            $rotinas = $this->consultaRotinas->cadastrosDeRotinas($usuario)
                ->whereIn('id_rotina', $dados['rotinas_ids'])
                ->when($processo, fn (Builder $consulta) => $consulta->where('r.tipo_rotina', $processo)->where('r.status', true))
                ->orderBy('id_rotina')
                ->lockForUpdate()
                ->get(['id_rotina', 'id_empresa', 'id_usuario', 'grupo_rotina']);

            abort_unless($rotinas->count() === count($dados['rotinas_ids']), 404);

            if ($processo !== null) {
                $pendentes = $this->execucoesDiarias->pendentesParaReatribuicao($rotinas->pluck('id_rotina')->all(), $agora)
                    ->whereIn('id_empresa', $this->consultaRotinas->empresas($usuario))
                    ->when($this->visibilidade->colaboradores($usuario), fn (Builder $consulta, Builder $visiveis) => $consulta->whereIn('id_usuario_responsavel', $visiveis))
                    ->lockForUpdate()->pluck('id_rotina')->unique();

                if ($pendentes->count() !== $rotinas->count()) {
                    throw ValidationException::withMessages(['rotinas_ids' => 'Uma das tarefas selecionadas não está mais disponível para atribuição.']);
                }
            }

            $this->validarDestino($usuario, $novoResponsavel, $rotinas->pluck('id_empresa')->unique());
            $alteradas = $rotinas->reject(fn (object $rotina): bool => (int) $rotina->id_usuario === $novoResponsavel);

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

        $mensagem = 'Tarefas atribuídas com sucesso!';
        if ($request->expectsJson()) {
            $request->session()->flash('success', $mensagem);

            return response()->json(['message' => $mensagem]);
        }

        return back()->with('success', $mensagem);
    }

    /** @param list<int|string> $idsExecucoes */
    private function transferirExecucoes(User $usuario, array $idsExecucoes, string $processo, int $novoResponsavel, CarbonImmutable $agora): void
    {
        $execucoes = $this->consultaRotinas->execucoesAbertasDoProcesso($usuario, $agora, $processo)
            ->whereIntegerInRaw('e.id_execucao', $idsExecucoes)
            ->orderBy('e.id_execucao')->lockForUpdate()
            ->select('e.id_execucao', 'e.id_usuario_responsavel', 'e.id_empresa', 'e.id_rotina')->get();

        if ($execucoes->count() !== count($idsExecucoes)) {
            throw ValidationException::withMessages([
                'execucoes_ids' => 'Uma das tarefas selecionadas não está mais disponível para atribuição.',
            ]);
        }

        $this->validarDestino($usuario, $novoResponsavel, $execucoes->pluck('id_empresa')->unique());
        $alteradas = $execucoes->reject(fn (stdClass $execucao): bool => (int) $execucao->id_usuario_responsavel === $novoResponsavel);

        if ($alteradas->isEmpty()) {
            return;
        }

        DB::table('rotinas_execucoes')->whereIntegerInRaw('id_execucao', $alteradas->pluck('id_execucao')->all())
            ->update(['id_usuario_responsavel' => $novoResponsavel, 'updated_at' => $agora]);

        foreach ($alteradas as $execucao) {
            AuditoriaService::registrar(
                'UPDATE', 'rotinas_execucoes', (string) $execucao->id_execucao,
                ['id_usuario_responsavel' => $execucao->id_usuario_responsavel], ['id_usuario_responsavel' => $novoResponsavel],
                'Responsável da execução alterado',
                idEmpresa: (int) $execucao->id_empresa,
                idRotina: (int) $execucao->id_rotina,
            );
        }
    }

    /** @param Collection<int, int> $empresas */
    private function validarDestino(User $usuario, int $novoResponsavel, Collection $empresas): void
    {
        $totalVinculos = DB::table('empresa_usuario')->where('id_usuario', $novoResponsavel)
            ->when($this->visibilidade->colaboradores($usuario), fn (Builder $vinculos, Builder $visiveis) => $vinculos->whereIn('id_usuario', $visiveis))
            ->whereIn('id_empresa', $empresas)->count();

        if ($totalVinculos !== $empresas->count()) {
            throw ValidationException::withMessages([
                'novo_colaborador_id' => 'O colaborador deve estar vinculado às unidades das rotinas selecionadas.',
            ]);
        }
    }
}
