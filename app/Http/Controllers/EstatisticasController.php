<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CalculadoraSla;
use App\Services\ConsultaRotinas;
use App\Services\EscalonamentoAcompanhamento;
use App\Services\ExecucoesDiarias;
use App\Services\VisibilidadeGrupos;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use stdClass;

class EstatisticasController extends Controller
{
    private const PROCESSOS = [
        'processo-diarias' => 'Rotinas Diárias',
        'processo-acompanhamento' => 'Rotinas de Acompanhamento',
        'processo-auxilio' => 'Solicitações de Auxílio',
        'processo-emergenciais' => 'Tarefas Emergenciais',
    ];

    public function __construct(
        private ConsultaRotinas $rotinas,
        private ExecucoesDiarias $execucoesDiarias,
        private CalculadoraSla $sla,
        private EscalonamentoAcompanhamento $escalonamento,
        private VisibilidadeGrupos $visibilidade,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $opcoesPeriodo = [
            'todos' => 'Todos',
            'esta_semana' => 'Esta semana',
            'este_mes' => 'Este mês',
            'este_ano' => 'Este ano',
            'semana_passada' => 'Semana passada',
            'mes_passado' => 'Mês passado',
            'ultimos_7' => 'Últimos 7 dias',
            'ultimos_30' => 'Últimos 30 dias',
            'personalizado' => 'Personalizado',
        ];

        $filtros = $request->validate([
            'aba' => ['nullable', Rule::in(['usuarios', 'grupos', 'processos'])],
            'busca' => ['nullable', 'string', 'max:150'],
            'pendentes' => ['nullable', 'boolean'],
            'andamento' => ['nullable', 'boolean'],
            'finalizados' => ['nullable', 'boolean'],
            'periodo_inicio' => ['nullable', Rule::in(array_keys($opcoesPeriodo))],
            'periodo_fim' => ['nullable', Rule::in(array_keys($opcoesPeriodo))],
            'inicio_de' => ['nullable', 'date_format:Y-m-d'],
            'inicio_ate' => ['nullable', 'date_format:Y-m-d'],
            'fim_de' => ['nullable', 'date_format:Y-m-d'],
            'fim_ate' => ['nullable', 'date_format:Y-m-d'],
            'detalhe' => [
                'nullable',
                'string',
                'max:80',
                $request->input('aba') === 'processos'
                    ? Rule::in(array_keys(self::PROCESSOS))
                    : 'regex:/\A(?:usuario|grupo)-(?:[0-9]+|sem)\z/',
            ],
            'busca_rotinas' => ['nullable', 'string', 'max:150'],
        ]);

        $finalizados = $request->boolean('finalizados');
        $periodos = [];

        foreach (['inicio', 'fim'] as $etapa) {
            $de = $etapa.'_de';
            $ate = $etapa.'_ate';
            $periodos[$etapa] = $filtros['periodo_'.$etapa]
                ?? (! empty($filtros[$de]) || ! empty($filtros[$ate]) ? 'personalizado' : 'todos');

            if (! $finalizados || $periodos[$etapa] !== 'personalizado') {
                unset($filtros[$de], $filtros[$ate]);

                continue;
            }

            if (
                ! empty($filtros[$de])
                && ! empty($filtros[$ate])
                && $filtros[$de] > $filtros[$ate]
            ) {
                return back()->withErrors([
                    $ate => 'A data final deve ser igual ou posterior à inicial.',
                ]);
            }
        }

        $aba = $filtros['aba'] ?? 'usuarios';
        $busca = trim($filtros['busca'] ?? '');
        $pendentes = $request->boolean('pendentes', true);
        $andamento = $request->boolean('andamento', true);
        $chaveDetalhe = $filtros['detalhe'] ?? null;
        $buscaRotinas = trim($filtros['busca_rotinas'] ?? '');

        // Os mesmos filtros acompanham a abertura e a volta dos detalhes.
        $parametros = array_intersect_key($filtros, array_flip([
            'inicio_de', 'inicio_ate', 'fim_de', 'fim_ate',
        ]));

        $parametros = array_merge($parametros, [
            'periodo_inicio' => $periodos['inicio'],
            'periodo_fim' => $periodos['fim'],
            'aba' => $aba,
            'busca' => $busca,
            'pendentes' => (int) $pendentes,
            'andamento' => (int) $andamento,
            'finalizados' => (int) $finalizados,
        ]);

        $usuario = $request->user();
        $empresas = $this->rotinas->empresas($usuario);
        $semEmpresas = ! $empresas->exists();
        $agora = now()->toImmutable();
        if (! $semEmpresas) {
            $this->execucoesDiarias->gerar($agora, $empresas);
        }

        $status = [];

        if ($pendentes) {
            $status[] = 'pendente';
        }

        if ($andamento) {
            $status[] = 'em_andamento';
        }

        if ($finalizados) {
            $status[] = 'concluida';
        }

        $consulta = $this->rotinas->execucoes($usuario)
            ->whereIn('e.status', $status)
            ->addSelect('t.icone', 't.cor_icone')
            ->orderBy('e.previsto_em', 'desc');

        $faixas = [];
        if ($finalizados) {
            $hoje = $agora->setTimezone(ExecucoesDiarias::FUSO)->startOfDay();

            foreach (['inicio' => 'iniciado_em', 'fim' => 'concluido_em'] as $etapa => $coluna) {
                [$de, $ate] = $this->intervalo(
                    $periodos[$etapa],
                    $hoje,
                    $filtros[$etapa.'_de'] ?? null,
                    $filtros[$etapa.'_ate'] ?? null
                );

                if ($de) {
                    $faixas[] = ["e.$coluna", '>=', $de->startOfDay()->utc()];
                }

                if ($ate) {
                    $faixas[] = ["e.$coluna", '<', $ate->addDay()->startOfDay()->utc()];
                }
            }

            if ($faixas !== []) {
                $consulta->where(fn (Builder $estados) => $estados->where('e.status', '<>', 'concluida')
                    ->orWhere(fn (Builder $concluidas) => $concluidas->where('e.status', 'concluida')->where($faixas)));
            }
        }

        $inconsistentes = 0;
        $execucoes = $consulta->get()->filter(function (stdClass $execucao) use ($agora, &$inconsistentes): bool {
            try {
                $execucao->situacao = $this->sla->calcular($execucao, $agora)['sla_atual'];

                return true;
            } catch (InvalidArgumentException) {
                $inconsistentes++;

                return false;
            }
        })->map($this->formatarExecucao(...));

        if ($aba === 'processos') {
            $acompanhamentos = $this->agruparAcompanhamentos($usuario, $agora, $status, $faixas, $inconsistentes);
            $resultados = $this->agruparPorProcesso($execucoes, $acompanhamentos);
        } else {
            $resultados = $this->agruparPorResponsavel($execucoes, $aba);
        }

        $termo = Str::ascii($busca);
        $resultados = $resultados->filter(fn (array $item): bool => $termo === '' || mb_stripos(Str::ascii($item['nome']), $termo) !== false);
        $grupos = $aba === 'processos' ? $resultados : $resultados
            ->sortBy(fn (array $item): string => mb_strtolower(Str::ascii($item['nome'])))
            ->groupBy(function (array $item): string {
                $inicial = strtoupper(substr(Str::ascii($item['nome']), 0, 1));

                return preg_match('/^[A-Z]$/', $inicial) ? $inicial : '#';
            });

        // A chave só abre resultados já limitados às empresas autorizadas.
        $detalhe = $chaveDetalhe ? $resultados->get($chaveDetalhe) : null;
        $rotinasDetalhe = collect();

        if ($detalhe) {
            $termo = mb_strtolower(Str::ascii($buscaRotinas));

            $rotinasDetalhe = $detalhe['execucoes']
                ->filter(function ($execucao) use ($termo) {
                    $horario = Carbon::parse($execucao->previsto_em, 'UTC')
                        ->setTimezone(ExecucoesDiarias::FUSO)
                        ->format('H:i');

                    $texto = mb_strtolower(Str::ascii(implode(' ', [
                        $execucao->tarefa,
                        $execucao->morador,
                        $execucao->local,
                        $horario,
                    ])));

                    return $termo === '' || str_contains($texto, $termo);
                })
                ->sortBy('previsto_em')
                ->values();
        }

        return view('relatorios.estatisticas', compact(
            'aba',
            'busca',
            'pendentes',
            'andamento',
            'finalizados',
            'grupos',
            'semEmpresas',
            'inconsistentes',
            'parametros',
            'chaveDetalhe',
            'detalhe',
            'buscaRotinas',
            'rotinasDetalhe',
            'opcoesPeriodo',
            'periodos'
        ));
    }

    private function formatarExecucao(stdClass $execucao): stdClass
    {
        $execucao->tarefa = trim($execucao->tarefa ?? '') ?: 'Tarefa não localizada';
        $execucao->morador = trim($execucao->morador ?? '') ?: 'Não informado';
        $execucao->local = trim($execucao->local_rotina ?? '')
            ?: (trim($execucao->local_cadastrado ?? '') ?: 'Não informado');

        return $execucao;
    }

    private function agruparPorResponsavel(Collection $execucoes, string $aba): Collection
    {
        return $execucoes->groupBy(fn (stdClass $execucao): string => $aba === 'usuarios'
            ? 'usuario-'.($execucao->id_usuario_responsavel ?? 'sem')
            : 'grupo-'.($execucao->id_grupo_acesso ?? 'sem'))
            ->map(function (Collection $itens, string $chave) use ($aba): array {
                $primeiro = $itens->first();
                $nome = $aba === 'usuarios'
                    ? ($primeiro->responsavel ?? 'Usuário não localizado')
                    : ($primeiro->grupo_acesso ?? 'Sem grupo');

                return $this->resumirExecucoes($chave, $nome, $itens);
            });
    }

    private function agruparPorProcesso(Collection $execucoes, Collection $acompanhamentos): Collection
    {
        $porTipo = $execucoes->groupBy(fn (stdClass $execucao): string => $execucao->tipo_rotina === ExecucoesDiarias::TIPO_EMERGENCIAL
            ? 'processo-emergenciais'
            : 'processo-diarias')->merge($acompanhamentos);

        return collect(self::PROCESSOS)->map(fn (string $nome, string $chave): array => $this->resumirExecucoes(
            $chave,
            $nome,
            $porTipo->get($chave, collect()),
        ));
    }

    /** @return array{chave: string, nome: string, total: int, indicadores: array<string, array{quantidade: int, percentual: float}>, execucoes: Collection} */
    private function resumirExecucoes(string $chave, string $nome, Collection $execucoes): array
    {
        $total = $execucoes->count();
        $quantidades = $execucoes->countBy('situacao');
        $indicadores = [];

        foreach (['atrasado', 'expirando', 'noPrazo'] as $situacao) {
            $quantidade = $quantidades->get($situacao, 0);
            $indicadores[$situacao] = [
                'quantidade' => $quantidade,
                'percentual' => round($quantidade * 100 / max(1, $total), 1),
            ];
        }

        return [
            'chave' => $chave,
            'nome' => $nome,
            'total' => $total,
            'indicadores' => $indicadores,
            'execucoes' => $execucoes,
        ];
    }

    /**
     * @param  list<string>  $status
     * @param  list<array{string, string, CarbonImmutable}>  $faixas
     */
    private function agruparAcompanhamentos(
        User $usuario,
        CarbonImmutable $agora,
        array $status,
        array $faixas,
        int &$inconsistentes,
    ): Collection {
        $pendentes = in_array('pendente', $status, true);
        $concluidos = in_array('concluida', $status, true);
        if ((! $pendentes && ! $concluidos) || ! $usuario->can('visualizar-modulo', 'acompanhamento')) {
            return collect();
        }

        $caixa = $usuario->isAdmin() ? null : $this->escalonamento->caixaDoUsuario($usuario);
        $consulta = $this->rotinas->filaDeAcompanhamento($usuario, $agora)
            ->when($this->visibilidade->colaboradores($usuario), fn (Builder $consulta, Builder $visiveis) => $consulta->whereIn('fila.id_usuario_responsavel', $visiveis))
            ->when(! $pendentes, fn (Builder $itens) => $itens->whereNotNull('fila.verificado_em'))
            ->when(! $concluidos, fn (Builder $itens) => $itens->whereNull('fila.verificado_em'));

        if ($faixas !== []) {
            $faixasAcompanhamento = array_map(fn (array $faixa): array => [
                $faixa[0] === 'e.iniciado_em' ? 'fila.iniciado_em' : 'fila.verificado_em', $faixa[1], $faixa[2],
            ], $faixas);
            $consulta->where(fn (Builder $itens) => $itens->whereNull('fila.verificado_em')
                ->orWhere(fn (Builder $verificados) => $verificados->where($faixasAcompanhamento)));
        }

        return $consulta->cursor()->map(function (stdClass $item) use ($usuario, $caixa, $agora, &$inconsistentes): ?stdClass {
            $verificado = $item->verificado_em !== null;
            if ($verificado && ! $this->escalonamento->podeConsultarVerificacao($item, $caixa, $usuario)) {
                return null;
            }

            try {
                $referencia = $verificado ? CarbonImmutable::parse($item->verificado_em, 'UTC') : $agora;
                $item->situacao = $this->classificarAcompanhamento($item, $caixa, $referencia);
            } catch (InvalidArgumentException) {
                $inconsistentes++;

                return null;
            }

            if ($item->situacao === null) {
                $inconsistentes += (int) $verificado;

                return null;
            }

            $item->status = $verificado ? 'concluida' : 'pendente';
            $item->concluido_em = $item->verificado_em;
            $item->responsavel = $item->colaborador;

            return $this->formatarExecucao($item);
        })->filter()->collect()->groupBy(fn (stdClass $item): string => $item->tipo === 'auxilio' ? 'processo-auxilio' : 'processo-acompanhamento');
    }

    private function classificarAcompanhamento(stdClass $item, ?string $caixa, CarbonImmutable $referencia): ?string
    {
        $recebidoEm = $this->escalonamento->recebimentoDoItem($item, $caixa, $referencia);

        return $recebidoEm ? $this->escalonamento->classificarSla($recebidoEm, $referencia, $item->tipo === 'auxilio') : null;
    }

    private function intervalo(
        string $periodo,
        CarbonImmutable $hoje,
        ?string $de,
        ?string $ate
    ): array {
        $semana = $hoje->startOfWeek(CarbonImmutable::MONDAY);
        $mes = $hoje->startOfMonth();

        return match ($periodo) {
            'esta_semana' => [$semana, $semana->addDays(6)],
            'este_mes' => [$mes, $mes->endOfMonth()],
            'este_ano' => [$hoje->startOfYear(), $hoje->endOfYear()],
            'semana_passada' => [$semana->subWeek(), $semana->subDay()],
            'mes_passado' => [$mes->subMonth(), $mes->subDay()],
            'ultimos_7' => [$hoje->subDays(6), $hoje],
            'ultimos_30' => [$hoje->subDays(29), $hoje],
            'personalizado' => [
                $de ? CarbonImmutable::parse($de, $hoje->getTimezone()) : null,
                $ate ? CarbonImmutable::parse($ate, $hoje->getTimezone()) : null,
            ],
            default => [null, null],
        };
    }
}
