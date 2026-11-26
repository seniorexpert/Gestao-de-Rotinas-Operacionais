<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;

class ExecucoesDiarias
{
    public const FUSO = 'America/Sao_Paulo';

    public const ANTECEDENCIA_MINUTOS = 60;

    public const TIPO_DIARIA = 'diaria';

    public const TIPO_EMERGENCIAL = 'emergencial';

    public const DIAS_DA_SEMANA = [
        'Domingo', 'Segunda-feira', 'Terça-feira', 'Quarta-feira', 'Quinta-feira', 'Sexta-feira', 'Sábado',
    ];

    private const DIAS = [
        ['dom', 'domingo'],
        ['seg', 'segunda'],
        ['ter', 'terca'],
        ['qua', 'quarta'],
        ['qui', 'quinta'],
        ['sex', 'sexta'],
        ['sab', 'sabado'],
    ];

    public function __construct(private CalculadoraSla $sla) {}

    /** @return array{CarbonImmutable, CarbonImmutable} */
    public static function janela(CarbonImmutable $referencia): array
    {
        $dia = $referencia->setTimezone(self::FUSO)->startOfDay();

        return [$dia->utc(), $dia->addDay()->utc()];
    }

    /** @param list<int>|null $idsRotinas */
    public function gerar(CarbonImmutable $agora, ?Builder $empresas = null, ?array $idsRotinas = null, ?int $idUsuario = null): int
    {
        $agora = $agora->utc();
        $hoje = $agora->setTimezone(self::FUSO)->startOfDay();

        return DB::transaction(function () use ($agora, $empresas, $idsRotinas, $idUsuario, $hoje): int {
            $rotinasAtivas = DB::table('rotinas as r')
                ->where('r.status', true)
                ->whereNull('r.excluida_em')
                ->when($empresas !== null, fn (Builder $consulta) => $consulta->whereIn('r.id_empresa', $empresas))
                ->when($idUsuario !== null, fn (Builder $consulta) => $consulta->where('r.id_usuario', $idUsuario));
            $emergenciais = $this->gerarEmergenciais(clone $rotinasAtivas, $agora, $idsRotinas);

            $rotinas = $rotinasAtivas
                ->join('tarefas as t', 'r.id_tarefa', '=', 't.id_tarefa')
                ->where('r.tipo_rotina', self::TIPO_DIARIA)
                ->when($idsRotinas !== null, fn (Builder $consulta) => $consulta->whereExists(fn (Builder $alvo) => $alvo
                    ->from('rotinas as alvo')
                    ->whereIntegerInRaw('alvo.id_rotina', $idsRotinas)
                    ->where('alvo.tipo_rotina', self::TIPO_DIARIA)
                    ->whereColumn('alvo.id_empresa', 'r.id_empresa')
                    ->whereColumn('alvo.id_usuario', 'r.id_usuario')))
                ->orderBy('r.id_rotina')
                ->lockForUpdate()
                ->get(['r.id_rotina', 'r.id_empresa', 'r.id_usuario', 'r.dias_semanas', 'r.horario', 'r.created_at', 't.tempo_realizar_tarefa'])
                ->map(function (stdClass $rotina): stdClass {
                    $horario = substr($rotina->horario, 0, 8);
                    $rotina->segundos = $this->sla->segundosDaTarefa(strlen($horario) === 5 ? $horario.':00' : $horario);
                    $rotina->duracao = $this->sla->segundosDaTarefa($rotina->tempo_realizar_tarefa);
                    $rotina->dias = $this->diasDaSemana($rotina->dias_semanas);

                    return $rotina;
                })
                ->filter(fn (stdClass $rotina): bool => $rotina->duracao > 0 && $rotina->dias !== []);

            $agendas = $rotinas->groupBy(fn (stdClass $rotina): string => $rotina->id_empresa.':'.$rotina->id_usuario)
                ->flatMap(fn (Collection $grupo): array => $this->agendasLiberadas($grupo, $hoje, $agora));

            if ($agendas->isEmpty()) {
                return $emergenciais;
            }

            $jaGeradas = DB::table('rotinas_execucoes')
                ->whereIntegerInRaw('id_rotina', $rotinas->pluck('id_rotina')->all())
                ->where('previsto_em', '>=', $hoje->subDay()->utc())
                ->where('previsto_em', '<', $hoje->addDays(3)->utc())
                ->get(['id_execucao', 'id_rotina', 'previsto_em', 'agenda_ate'])
                ->keyBy(fn (stdClass $execucao): string => $execucao->id_rotina.':'.CarbonImmutable::parse($execucao->previsto_em, 'UTC')
                    ->setTimezone(self::FUSO)->toDateString());

            $slas = [];
            $registros = [];
            foreach ($agendas as $agenda) {
                $ate = $agenda['ate']->toDateTimeString();
                $atualizar = [];

                foreach ($agenda['tarefas'] as [$rotina, $previsto]) {
                    $diaPrevisto = $previsto->setTimezone(self::FUSO)->toDateString();
                    $existente = $jaGeradas->get($rotina->id_rotina.':'.$diaPrevisto);
                    if ($existente) {
                        if (substr($existente->agenda_ate ?? '', 0, 19) !== $ate) {
                            $atualizar[] = $existente->id_execucao;
                        }

                        continue;
                    }

                    $minutos = intdiv($rotina->duracao + 59, 60);
                    $slas[$minutos] ??= $this->slaParaDuracao($minutos, $agora);
                    $registros[] = [
                        'id_rotina' => $rotina->id_rotina,
                        'id_sla' => $slas[$minutos],
                        'id_empresa' => $rotina->id_empresa,
                        'id_usuario_responsavel' => $rotina->id_usuario,
                        'previsto_em' => $previsto,
                        'prazo_em' => $this->sla->prazoInicio($previsto),
                        'agenda_ate' => $ate,
                        'tempo_limite_minutos' => $minutos,
                        'duracao_prevista_segundos' => $rotina->duracao,
                        'status' => 'pendente',
                        'created_at' => $agora,
                        'updated_at' => $agora,
                    ];
                }

                if ($atualizar !== []) {
                    DB::table('rotinas_execucoes')->whereIntegerInRaw('id_execucao', $atualizar)->update(['agenda_ate' => $ate]);
                }
            }

            foreach (array_chunk($registros, 100) as $lote) {
                DB::table('rotinas_execucoes')->insert($lote);
            }

            return $emergenciais + count($registros);
        }, 3);
    }

    /** @param list<int> $idsRotinas */
    public function reatribuirPendentes(array $idsRotinas, int $responsavel, CarbonImmutable $agora): void
    {
        $this->pendentesParaReatribuicao($idsRotinas, $agora)
            ->update(['id_usuario_responsavel' => $responsavel, 'updated_at' => $agora->utc()]);
    }

    /** @param list<int> $idsRotinas */
    public function pendentesParaReatribuicao(array $idsRotinas, CarbonImmutable $agora): Builder
    {
        [$inicioDoDia] = self::janela($agora);

        return DB::table('rotinas_execucoes')->whereIntegerInRaw('id_rotina', $idsRotinas)
            ->where(fn (Builder $consulta) => $consulta->where('previsto_em', '>=', $inicioDoDia)
                ->orWhere('agenda_ate', '>', $agora->utc()))
            ->where('status', 'pendente')->whereNull('iniciado_em')->whereNull('concluido_em');
    }

    /** @param list<int>|null $idsRotinas */
    private function gerarEmergenciais(Builder $consulta, CarbonImmutable $agora, ?array $idsRotinas): int
    {
        $rotinas = $consulta->where('r.tipo_rotina', self::TIPO_EMERGENCIAL)
            ->where('r.agendada_em', '<=', $agora)
            ->when($idsRotinas !== null, fn (Builder $consulta) => $consulta->whereIntegerInRaw('r.id_rotina', $idsRotinas))
            ->whereNotExists(fn (Builder $execucao) => $execucao->from('rotinas_execucoes as e')
                ->whereColumn('e.id_rotina', 'r.id_rotina'))
            ->orderBy('r.id_rotina')->lockForUpdate()
            ->get(['r.id_rotina', 'r.id_empresa', 'r.id_usuario', 'r.agendada_em']);

        if ($rotinas->isEmpty()) {
            return 0;
        }

        $jaGeradas = DB::table('rotinas_execucoes')->whereIntegerInRaw('id_rotina', $rotinas->pluck('id_rotina')->all())
            ->pluck('id_rotina')->flip();
        $aGerar = $rotinas->reject(fn (stdClass $rotina): bool => $jaGeradas->has($rotina->id_rotina));
        if ($aGerar->isEmpty()) {
            return 0;
        }

        $minutos = CalculadoraSla::LIMITE_EMERGENCIAL_MINUTOS;
        $idSla = $this->slaParaDuracao($minutos, $agora);
        [, $fimDoDia] = self::janela($agora);
        $prazo = $this->sla->prazoEmergencial($agora);

        foreach ($aGerar->chunk(100) as $lote) {
            DB::table('rotinas_execucoes')->insert($lote->map(fn (stdClass $rotina): array => [
                'id_rotina' => $rotina->id_rotina,
                'id_sla' => $idSla,
                'id_empresa' => $rotina->id_empresa,
                'id_usuario_responsavel' => $rotina->id_usuario,
                'previsto_em' => $rotina->agendada_em,
                'prazo_em' => $prazo,
                'agenda_ate' => $fimDoDia,
                'tempo_limite_minutos' => $minutos,
                'duracao_prevista_segundos' => $minutos * 60,
                'status' => 'pendente',
                'created_at' => $agora,
                'updated_at' => $agora,
            ])->values()->all());
        }

        return $aGerar->count();
    }

    /**
     * @param  Collection<int, stdClass>  $rotinas
     * @return list<array{tarefas: list<array{stdClass, CarbonImmutable}>, ate: CarbonImmutable}>
     */
    private function agendasLiberadas(Collection $rotinas, CarbonImmutable $hoje, CarbonImmutable $agora): array
    {
        $inicio = $this->horarioAposMaiorPausa($rotinas);
        $diasDosInicios = [];
        $programacoesDosInicios = [];

        foreach ($rotinas as $rotina) {
            if ($rotina->segundos >= $inicio) {
                $diasDosInicios += array_fill_keys($rotina->dias, true);
                $programacoesDosInicios[implode(',', $rotina->dias)] = true;
            }
        }

        $agendas = [];
        for ($deslocamento = -1; $deslocamento <= 2; $deslocamento++) {
            $dia = $hoje->addDays($deslocamento);
            $ontem = $dia->subDay();

            foreach ($rotinas as $rotina) {
                if (! in_array($dia->dayOfWeek, $rotina->dias, true)) {
                    continue;
                }

                $dataAgenda = $dia;
                $dataPrevista = $dia;

                if ($rotina->segundos < $inicio) {
                    // Dias iguais representam a data do plantão. Dias distintos podem já indicar a data civil da madrugada.
                    if (isset($programacoesDosInicios[implode(',', $rotina->dias)])) {
                        $dataPrevista = $dia->addDay();
                    } elseif (isset($diasDosInicios[$ontem->dayOfWeek])) {
                        $dataAgenda = $ontem;
                    }
                }

                if ($rotina->created_at && CarbonImmutable::parse($rotina->created_at, 'UTC') >= $dataPrevista->addDay()) {
                    continue;
                }

                $previsto = $dataPrevista->setTimeFromTimeString(substr($rotina->horario, 0, 8))->utc();
                $agendas[$dataAgenda->toDateString()][] = [$rotina, $previsto];
            }
        }

        krsort($agendas);
        $proximaLiberacao = null;
        $liberadas = [];

        foreach ($agendas as $tarefas) {
            $horarios = array_column($tarefas, 1);
            $liberacao = min($horarios)->subMinutes(self::ANTECEDENCIA_MINUTOS);
            $ultimo = max($horarios);
            $ate = $ultimo->setTimezone(self::FUSO)->startOfDay()->addDay()->utc();
            if ($proximaLiberacao !== null && $proximaLiberacao > $ultimo && $proximaLiberacao < $ate) {
                $ate = $proximaLiberacao;
            }

            $proximaLiberacao = $liberacao;
            if ($liberacao <= $agora && $ate > $agora) {
                $liberadas[] = ['tarefas' => $tarefas, 'ate' => $ate];
            }
        }

        return $liberadas;
    }

    /** @param Collection<int, stdClass> $rotinas */
    private function horarioAposMaiorPausa(Collection $rotinas): int
    {
        $horarios = $rotinas->pluck('segundos')->unique()->sort()->values()->all();
        $inicio = $horarios[0];
        $maiorPausa = 86400 + $inicio - end($horarios);

        foreach (array_slice($horarios, 1) as $indice => $horario) {
            $pausa = $horario - $horarios[$indice];
            if ($pausa > $maiorPausa) {
                $maiorPausa = $pausa;
                $inicio = $horario;
            }
        }

        return $inicio;
    }

    /** @return list<int> */
    public function diasDaSemana(string $dias): array
    {
        $nomes = Str::lower(Str::ascii(trim($dias)));
        if (in_array($nomes, ['todos', 'todos os dias', 'diariamente'], true)) {
            return range(0, 6);
        }

        $selecionados = preg_split('/[^a-z]+/', $nomes, -1, PREG_SPLIT_NO_EMPTY);

        return array_keys(array_filter(self::DIAS, fn (array $nomes): bool => array_intersect($nomes, $selecionados) !== []));
    }

    private function slaParaDuracao(int $minutos, CarbonImmutable $agora): int
    {
        $id = DB::table('slas')->where('tempo_limite_minutos', $minutos)->where('status', true)
            ->orderBy('id_sla')->lockForUpdate()->value('id_sla');

        return (int) ($id ?? DB::table('slas')->insertGetId([
            'sla' => 'Conclusão em '.$minutos.($minutos === 1 ? ' minuto' : ' minutos'),
            'tempo_limite_minutos' => $minutos,
            'status' => true,
            'created_at' => $agora->utc(),
            'updated_at' => $agora->utc(),
        ], 'id_sla'));
    }
}
