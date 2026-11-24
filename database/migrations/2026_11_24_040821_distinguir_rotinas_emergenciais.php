<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rotinas', function (Blueprint $table) {
            $table->string('tipo_rotina', 20)->default('diaria');
            $table->dateTime('agendada_em')->nullable();
            $table->index(['tipo_rotina', 'agendada_em'], 'rotinas_tipo_agendamento_index');
        });

        $historico = DB::table('logs_auditoria')
            ->where('tabela', 'rotinas')
            ->where('acao', 'CREATE')
            ->where('descricao', 'Tarefa emergencial cadastrada')
            ->whereNotNull('id_rotina')
            ->groupBy('id_rotina')
            ->select('id_rotina')
            ->selectRaw('MIN(created_at) as registrada_em');

        $rotinas = DB::table('rotinas as r')
            ->joinSub($historico, 'historico', 'historico.id_rotina', '=', 'r.id_rotina')
            ->select('r.id_rotina', 'r.created_at', 'r.horario', 'r.dias_semanas', 'historico.registrada_em')
            ->selectSub(DB::table('rotinas_execucoes')->whereColumn('id_rotina', 'r.id_rotina')->selectRaw('MIN(previsto_em)'), 'primeira_execucao')
            ->lazyById(200, 'r.id_rotina', 'id_rotina');

        foreach ($rotinas as $rotina) {
            DB::table('rotinas')->where('id_rotina', $rotina->id_rotina)->update([
                'tipo_rotina' => 'emergencial',
                'agendada_em' => $rotina->primeira_execucao ?? $this->primeiroAgendamento($rotina),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('rotinas', function (Blueprint $table) {
            $table->dropIndex('rotinas_tipo_agendamento_index');
            $table->dropColumn(['tipo_rotina', 'agendada_em']);
        });
    }

    private function primeiroAgendamento(stdClass $rotina): CarbonImmutable
    {
        $dia = CarbonImmutable::parse($rotina->created_at ?? $rotina->registrada_em, 'UTC')
            ->setTimezone('America/Sao_Paulo')->startOfDay();
        $diasDaSemana = [
            ['dom', 'domingo'], ['seg', 'segunda'], ['ter', 'terca'], ['qua', 'quarta'],
            ['qui', 'quinta'], ['sex', 'sexta'], ['sab', 'sabado'],
        ];
        $dias = preg_split('/[^a-z]+/', Str::lower(Str::ascii($rotina->dias_semanas)), -1, PREG_SPLIT_NO_EMPTY);
        $horario = substr($rotina->horario, 0, 8);

        for ($offset = 0; $offset < 7; $offset++) {
            $data = $dia->addDays($offset);
            if (array_intersect($diasDaSemana[$data->dayOfWeek], $dias) !== []) {
                return $data->setTimeFromTimeString($horario)->utc();
            }
        }

        return $dia->setTimeFromTimeString($horario)->utc();
    }
};
