<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use App\Services\CalculadoraSla;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExecucoesController extends Controller
{
    public function __construct(private CalculadoraSla $sla) {}

    public function iniciar(Request $request, int $id)
    {
        DB::transaction(function () use ($request, $id) {
            $execucao = $this->buscarExecucaoAutorizada($request, $id);

            // Uma repetição do clique não reinicia o cronômetro.
            if ($execucao->status === 'em_andamento') {
                return;
            }

            if ($execucao->status !== 'pendente') {
                throw ValidationException::withMessages([
                    'execucao' => 'Somente uma execução pendente pode ser iniciada.',
                ]);
            }

            if ($execucao->iniciado_em || $execucao->concluido_em) {
                throw ValidationException::withMessages([
                    'execucao' => 'Esta execução possui datas inconsistentes.',
                ]);
            }

            $duracao = (int) $execucao->duracao_prevista_segundos;

            // Para pendentes sem duração registrada, captura o tempo
            // da tarefa no primeiro início e o preserva na execução.
            if ($duracao <= 0) {
                $tempo = DB::table('rotinas as r')
                    ->join('tarefas as t', 'r.id_tarefa', '=', 't.id_tarefa')
                    ->where('r.id_rotina', $execucao->id_rotina)
                    ->value('t.tempo_realizar_tarefa');

                $duracao = $this->sla->segundosDaTarefa($tempo);
            }

            if ($duracao <= 0) {
                throw ValidationException::withMessages([
                    'execucao' => 'Cadastre um tempo de realização maior que zero para a tarefa.',
                ]);
            }

            $agora = now();

            $this->registrarMudanca($execucao, 'INICIAR', 'Execução iniciada', [
                'status' => 'em_andamento',
                'iniciado_em' => $agora,
                'duracao_prevista_segundos' => $duracao,
                'prazo_em' => $agora->copy()->addSeconds($duracao),
            ]);
        });

        return back()->with('success', 'Execução em andamento.');
    }

    public function concluir(Request $request, int $id)
    {
        DB::transaction(function () use ($request, $id) {
            $execucao = $this->buscarExecucaoAutorizada($request, $id);

            // Uma repetição não altera a conclusão já registrada.
            if ($execucao->status === 'concluida') {
                return;
            }

            if (
                $execucao->status !== 'em_andamento'
                || ! $execucao->iniciado_em
            ) {
                throw ValidationException::withMessages([
                    'execucao' => 'Inicie a execução antes de concluir.',
                ]);
            }

            if (
                $execucao->concluido_em
                || (int) $execucao->duracao_prevista_segundos <= 0
            ) {
                throw ValidationException::withMessages([
                    'execucao' => 'Revise a duração e as datas desta execução.',
                ]);
            }

            $agora = now();

            if ($agora->lessThan(Carbon::parse($execucao->iniciado_em))) {
                throw ValidationException::withMessages([
                    'execucao' => 'A conclusão não pode ser anterior ao início.',
                ]);
            }

            $this->registrarMudanca($execucao, 'CONCLUIR', 'Execução concluída', [
                'status' => 'concluida',
                'concluido_em' => $agora,
                'id_usuario_conclusao' => $request->user()->getAuthIdentifier(),
            ]);
        });

        return back()->with('success', 'Execução concluída com sucesso.');
    }

    /** @param array<string, mixed> $alteracoes */
    private function registrarMudanca(object $execucao, string $acao, string $descricao, array $alteracoes): void
    {
        DB::table('rotinas_execucoes')->where('id_execucao', $execucao->id_execucao)
            ->update([...$alteracoes, 'updated_at' => now()]);

        AuditoriaService::registrar(
            $acao, 'rotinas_execucoes', (string) $execucao->id_execucao,
            dadosAnteriores: array_intersect_key((array) $execucao, $alteracoes),
            dadosNovos: $alteracoes,
            descricao: $descricao,
            idEmpresa: (int) $execucao->id_empresa,
            idRotina: (int) $execucao->id_rotina,
        );
    }

    private function buscarExecucaoAutorizada(
        Request $request,
        int $id
    ): object {
        $usuarioId = $request->user()->getAuthIdentifier();

        $execucao = DB::table('rotinas_execucoes')
            ->where('id_execucao', $id)
            ->where('id_usuario_responsavel', $usuarioId)
            ->whereIn(
                'id_empresa',
                DB::table('empresa_usuario')
                    ->select('id_empresa')
                    ->where('id_usuario', $usuarioId)
            )
            ->lockForUpdate()
            ->first();

        abort_if(
            ! $execucao,
            404,
            'Execução não encontrada ou indisponível para este usuário.'
        );

        return $execucao;
    }
}
