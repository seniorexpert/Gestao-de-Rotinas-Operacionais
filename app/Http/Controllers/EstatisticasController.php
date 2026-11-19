<?php

namespace App\Http\Controllers;

use App\Services\CalculadoraSla;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EstatisticasController extends Controller
{
    public function index(Request $request)
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
            'aba' => ['nullable', Rule::in(['usuarios', 'grupos'])],
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
                'regex:/\A(?:usuario|grupo)-(?:[0-9]+|sem)\z/',
            ],
            'busca_rotinas' => ['nullable', 'string', 'max:150'],
        ]);

        $finalizados = $request->boolean('finalizados');
        $periodos = [];

        foreach (['inicio', 'fim'] as $etapa) {
            $de = $etapa.'_de';
            $ate = $etapa.'_ate';
            $periodos[$etapa] = $filtros['periodo_'.$etapa]
                ?? (!empty($filtros[$de]) || !empty($filtros[$ate]) ? 'personalizado' : 'todos');

            if (!$finalizados || $periodos[$etapa] !== 'personalizado') {
                unset($filtros[$de], $filtros[$ate]);
                continue;
            }

            if (
                !empty($filtros[$de])
                && !empty($filtros[$ate])
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

        $empresas = DB::table('empresa_usuario')
            ->where('id_usuario', $request->user()->getAuthIdentifier())
            ->pluck('id_empresa');

        $semEmpresas = $empresas->isEmpty();
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

        $consulta = DB::table('rotinas_execucoes as e')
            ->leftJoin('usuarios as u', 'e.id_usuario_responsavel', '=', 'u.id_usuario')
            ->leftJoin('grupos_acesso as g', 'u.id_grupo_acesso', '=', 'g.id_grupo_acesso')
            ->leftJoin('rotinas as r', function ($join) {
                $join->on('e.id_rotina', '=', 'r.id_rotina')
                    ->on('e.id_empresa', '=', 'r.id_empresa');
            })
            ->leftJoin('tarefas as t', 'r.id_tarefa', '=', 't.id_tarefa')
            ->leftJoin('locais as l', 'r.id_local', '=', 'l.id_local')
            ->whereIn('e.id_empresa', $empresas)
            ->whereIn('e.status', $status)
            ->select([
                'e.id_execucao',
                'e.id_rotina',
                'e.id_usuario_responsavel',
                'e.previsto_em',
                'e.prazo_em',
                'e.iniciado_em',
                'e.concluido_em',
                'e.tempo_limite_minutos',
                'e.duracao_prevista_segundos',
                'e.status',
                'u.usuario as responsavel',
                'u.id_grupo_acesso',
                'g.grupo_acesso',
                't.tarefa',
                'r.morador',
                'r.local as local_rotina',
                'l.local as local_cadastrado',
            ])
            ->orderBy('e.previsto_em', 'desc');

        if ($finalizados) {
            $hoje = CarbonImmutable::today('America/Sao_Paulo');

            foreach (['inicio' => 'iniciado_em', 'fim' => 'concluido_em'] as $etapa => $coluna) {
                [$de, $ate] = $this->intervalo(
                    $periodos[$etapa],
                    $hoje,
                    $filtros[$etapa.'_de'] ?? null,
                    $filtros[$etapa.'_ate'] ?? null
                );

                if ($de) {
                    $consulta->where("e.$coluna", '>=', $de->startOfDay()->utc());
                }

                if ($ate) {
                    $consulta->where("e.$coluna", '<', $ate->addDay()->startOfDay()->utc());
                }
            }
        }

        $agora = now();
        $calculadora = new CalculadoraSla;
        $inconsistentes = 0;

        $execucoes = $consulta->get()
            ->map(function ($execucao) use ($agora, $calculadora, &$inconsistentes) {
                try {
                    $resultado = $calculadora->calcular($execucao, $agora);

                    $execucao->situacao = $resultado['sla_atual'];
                    $execucao->sla_inicio = $resultado['sla_inicio'];
                    $execucao->sla_conclusao = $resultado['sla_conclusao'];

                    $execucao->prazo_em = $execucao->status === 'pendente'
                        ? Carbon::parse($execucao->previsto_em)
                            ->addMinute()->format('Y-m-d H:i:s')
                        : Carbon::parse($execucao->iniciado_em)
                            ->addSeconds((int) $execucao->duracao_prevista_segundos)
                            ->format('Y-m-d H:i:s');

                    $execucao->tarefa = trim($execucao->tarefa ?? '')
                        ?: 'Tarefa não localizada';
                    $execucao->morador = trim($execucao->morador ?? '') ?: 'Não informado';
                    $execucao->local = trim($execucao->local_rotina ?? '')
                        ?: (trim($execucao->local_cadastrado ?? '') ?: 'Não informado');

                    return $execucao;
                } catch (\InvalidArgumentException $erro) {
                    $inconsistentes++;

                    return null;
                }
            })
            ->filter()
            ->values();

        $resultados = $execucoes
            ->groupBy(function ($execucao) use ($aba) {
                return $aba === 'usuarios'
                    ? 'usuario-'.($execucao->id_usuario_responsavel ?? 'sem')
                    : 'grupo-'.($execucao->id_grupo_acesso ?? 'sem');
            })
            ->map(function ($itens, $chave) use ($aba) {
                $primeiro = $itens->first();
                $nome = $aba === 'usuarios'
                    ? ($primeiro->responsavel ?? 'Usuário não localizado')
                    : ($primeiro->grupo_acesso ?? 'Sem grupo');

                $total = $itens->count();
                $indicadores = [];

                foreach (['atrasado', 'expirando', 'noPrazo'] as $situacao) {
                    $quantidade = $itens->where('situacao', $situacao)->count();

                    $indicadores[$situacao] = [
                        'quantidade' => $quantidade,
                        'percentual' => round($quantidade * 100 / $total, 1),
                    ];
                }

                return [
                    'chave' => $chave,
                    'nome' => $nome,
                    'total' => $total,
                    'indicadores' => $indicadores,
                    'execucoes' => $itens,
                ];
            })
            ->filter(fn ($item) => $busca === ''
                || mb_stripos($item['nome'], $busca) !== false)
            ->sortBy(fn ($item) => mb_strtolower(Str::ascii($item['nome'])));

        $grupos = $resultados->groupBy(function ($item) {
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
                        ->setTimezone('America/Sao_Paulo')
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
