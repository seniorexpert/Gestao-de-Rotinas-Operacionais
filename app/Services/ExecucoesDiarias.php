<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExecucoesDiarias
{
    public const FUSO = 'America/Sao_Paulo';

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
    public function gerar(CarbonImmutable $agora, ?Builder $empresas = null, ?array $idsRotinas = null): int
    {
        [$inicio, $fim] = self::janela($agora);
        $dia = $inicio->setTimezone(self::FUSO);

        return DB::transaction(function () use ($agora, $empresas, $idsRotinas, $inicio, $fim, $dia): int {
            $rotinas = DB::table('rotinas as r')
                ->join('tarefas as t', 'r.id_tarefa', '=', 't.id_tarefa')
                ->where('r.status', true)
                ->where(fn (Builder $consulta) => $consulta->whereNull('r.created_at')->orWhere('r.created_at', '<', $fim))
                ->when($empresas !== null, fn (Builder $consulta) => $consulta->whereIn('r.id_empresa', $empresas))
                ->when($idsRotinas !== null, fn (Builder $consulta) => $consulta->whereIntegerInRaw('r.id_rotina', $idsRotinas))
                ->whereNotExists(fn (Builder $consulta) => $consulta->from('rotinas_execucoes as e')
                    ->whereColumn('e.id_rotina', 'r.id_rotina')
                    ->where('e.previsto_em', '>=', $inicio)->where('e.previsto_em', '<', $fim))
                ->orderBy('r.id_rotina')->lockForUpdate()
                ->get(['r.id_rotina', 'r.id_empresa', 'r.id_usuario', 'r.dias_semanas', 'r.horario', 't.tempo_realizar_tarefa'])
                ->filter(fn (object $rotina): bool => $this->ocorreNoDia($rotina->dias_semanas, $dia));

            if ($rotinas->isEmpty()) {
                return 0;
            }

            $jaGeradas = DB::table('rotinas_execucoes')
                ->whereIntegerInRaw('id_rotina', $rotinas->pluck('id_rotina')->all())
                ->where('previsto_em', '>=', $inicio)->where('previsto_em', '<', $fim)
                ->pluck('id_rotina')->flip();

            $slas = [];
            $registros = [];
            foreach ($rotinas as $rotina) {
                $duracao = $this->sla->segundosDaTarefa($rotina->tempo_realizar_tarefa);
                if ($duracao <= 0 || $jaGeradas->has($rotina->id_rotina)) {
                    continue;
                }

                $minutos = intdiv($duracao + 59, 60);
                $slas[$minutos] ??= $this->slaParaDuracao($minutos, $agora);
                $previsto = $dia->setTimeFromTimeString(substr($rotina->horario, 0, 8))->utc();
                $registros[] = [
                    'id_rotina' => $rotina->id_rotina,
                    'id_sla' => $slas[$minutos],
                    'id_empresa' => $rotina->id_empresa,
                    'id_usuario_responsavel' => $rotina->id_usuario,
                    'previsto_em' => $previsto,
                    'prazo_em' => $this->sla->prazoInicio($previsto),
                    'tempo_limite_minutos' => $minutos,
                    'duracao_prevista_segundos' => $duracao,
                    'status' => 'pendente',
                    'created_at' => $agora->utc(),
                    'updated_at' => $agora->utc(),
                ];
            }

            foreach (array_chunk($registros, 100) as $lote) {
                DB::table('rotinas_execucoes')->insert($lote);
            }

            return count($registros);
        }, 3);
    }

    private function ocorreNoDia(string $dias, CarbonImmutable $dia): bool
    {
        $nomes = Str::lower(Str::ascii(trim($dias)));
        if (in_array($nomes, ['todos', 'todos os dias', 'diariamente'], true)) {
            return true;
        }

        $selecionados = preg_split('/[^a-z]+/', $nomes, -1, PREG_SPLIT_NO_EMPTY);

        return array_intersect(self::DIAS[$dia->dayOfWeek], $selecionados) !== [];
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
