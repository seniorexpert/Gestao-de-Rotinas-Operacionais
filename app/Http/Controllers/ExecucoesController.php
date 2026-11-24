<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\CalculadoraSla;
use App\Services\ConsultaRotinas;
use App\Services\EscalonamentoAcompanhamento;
use App\Services\ExecucoesDiarias;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ExecucoesController extends Controller
{
    public function __construct(
        private CalculadoraSla $sla,
        private ConsultaRotinas $rotinas,
        private EscalonamentoAcompanhamento $escalonamento,
    ) {}

    public function realizando(Request $request, int $id): View
    {
        $execucao = $this->consultarExecucao($request, $id);
        abort_unless($execucao->rotina_ativa && $execucao->status === 'em_andamento' && $execucao->iniciado_em, 404);
        $limite = $execucao->tipo_rotina === ExecucoesDiarias::TIPO_EMERGENCIAL
            ? $this->sla->calcular($execucao)['prazo_em']
            : $execucao->prazo_em;
        $prazo = filled($limite)
            ? Carbon::parse($limite, 'UTC')->setTimezone(ExecucoesDiarias::FUSO)->format('d/m H:i:s')
            : null;
        $documentacao = $execucao->documentacao ? Storage::disk('public')->url($execucao->documentacao) : null;

        return view('execucoes.realizando', compact('execucao', 'prazo', 'documentacao'));
    }

    public function formulario(Request $request, int $id, string $acao): View
    {
        $execucao = $this->consultarExecucao($request, $id);
        abort_unless($execucao->rotina_ativa && $execucao->status === 'em_andamento' && $execucao->iniciado_em, 404);

        $protocolo = $acao === 'auxilio' ? (string) Str::uuid() : null;

        return view('execucoes.formulario', compact('execucao', 'acao', 'protocolo'));
    }

    public function foto(Request $request, int $id): StreamedResponse
    {
        $execucao = $this->consultarExecucao($request, $id);
        $disco = Storage::disk('local');
        abort_unless($execucao->foto_conclusao && $disco->exists($execucao->foto_conclusao), 404);

        return $disco->download($execucao->foto_conclusao);
    }

    public function iniciar(Request $request, int $id): RedirectResponse
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

            $emergencial = $execucao->tipo_rotina === ExecucoesDiarias::TIPO_EMERGENCIAL;
            $duracao = $emergencial ? CalculadoraSla::LIMITE_EMERGENCIAL_MINUTOS * 60 : (int) $execucao->duracao_prevista_segundos;

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

            $agora = now('UTC');

            $this->registrarMudanca($execucao, 'INICIAR', 'Execução iniciada', [
                'status' => 'em_andamento',
                'iniciado_em' => $agora,
                'duracao_prevista_segundos' => $duracao,
                'prazo_em' => $emergencial ? $this->sla->calcular($execucao, $agora)['prazo_em'] : $agora->copy()->addSeconds($duracao),
            ]);
        });

        return $request->boolean('fluxo_diario')
            ? to_route('execucoes.realizando', $id)
            : back()->with('success', 'Execução em andamento.');
    }

    public function concluir(Request $request, int $id): RedirectResponse
    {
        $foto = null;
        try {
            DB::transaction(function () use ($request, $id, &$foto): void {
                $execucao = $this->buscarExecucaoAutorizada($request, $id);

                if ($execucao->status === 'concluida') {
                    return;
                }

                $encerramento = $this->encerramento($request, $execucao);
                $dados = $request->validate([
                    'comentario' => ['required', 'string', 'max:2000'],
                    'foto' => ['nullable', 'image', 'max:2048'],
                ], [
                    'comentario.required' => 'Informe um comentário sobre a execução.',
                    'comentario.string' => 'O comentário deve ser informado em texto.',
                    'comentario.max' => 'Escreva um comentário de até 2.000 caracteres.',
                    'foto.image' => 'Selecione um arquivo de imagem.',
                    'foto.max' => 'A foto deve ter até 2 MB.',
                    'foto.uploaded' => 'Não foi possível receber a foto. Envie uma imagem de até 2 MB.',
                ]);

                $foto = ($dados['foto'] ?? null)?->store('fotos-execucoes', 'local');
                if ($foto === false) {
                    throw ValidationException::withMessages(['foto' => 'A foto não pôde ser salva.']);
                }

                $this->registrarMudanca($execucao, 'CONCLUIR', 'Execução concluída', [
                    ...$encerramento,
                    'status' => 'concluida',
                    'comentario_conclusao' => $dados['comentario'],
                    'foto_conclusao' => $foto,
                ]);
            });
        } catch (Throwable $erro) {
            if ($foto) {
                Storage::disk('local')->delete($foto);
            }

            throw $erro;
        }

        $destino = $request->boolean('fluxo_diario') ? to_route('home', ['aba' => 'concluida']) : back();

        return $destino->with('success', 'Execução concluída com sucesso.');
    }

    public function naoRealizada(Request $request, int $id): RedirectResponse
    {
        DB::transaction(function () use ($request, $id): void {
            $execucao = $this->buscarExecucaoAutorizada($request, $id);
            if ($execucao->status === 'nao_realizada') {
                return;
            }

            $encerramento = $this->encerramento($request, $execucao);
            $this->registrarMudanca($execucao, 'NAO_REALIZAR', 'Execução encerrada como não realizada', [
                ...$encerramento,
                'status' => 'nao_realizada',
                'justificativa_nao_realizada' => $this->justificativa($request),
            ]);

            $caixa = $this->caixaDoResponsavel($request->user(), $execucao);
            if ($caixa !== null) {
                DB::table('rotinas_acomp_nao_realizadas')->insert([
                    'id_execucao' => $id,
                    'caixa' => $caixa,
                    'recebido_em' => $encerramento['concluido_em'],
                ]);
            }
        });

        return to_route('home', ['aba' => 'todas'])->with('success', 'Justificativa registrada. Tarefa encerrada como não realizada.');
    }

    public function auxilio(Request $request, int $id): RedirectResponse
    {
        $caixa = DB::transaction(function () use ($request, $id): ?string {
            $execucao = $this->buscarExecucaoAutorizada($request, $id);
            $this->validarEmAndamento($execucao);
            $justificativa = $this->justificativa($request);
            $dados = $request->validate(['protocolo' => ['nullable', 'uuid']]);
            $protocolo = Str::lower($dados['protocolo'] ?? (string) Str::uuid());
            $caixa = $this->caixaDoResponsavel($request->user(), $execucao);
            $registro = ['justificativa' => $justificativa];

            if ($caixa !== null) {
                $criada = DB::table('rotinas_acomp_auxilios')->where('id_execucao', $id)->where('protocolo', $protocolo)->value('caixa');
                if ($criada !== null) {
                    return $criada;
                }

                $registro['id_solicitacao'] = DB::table('rotinas_acomp_auxilios')->insertGetId([
                    'id_execucao' => $id,
                    'protocolo' => $protocolo,
                    'caixa' => $caixa,
                    'justificativa' => $justificativa,
                    'recebido_em' => now('UTC'),
                ], 'id_solicitacao');
                $registro['caixa_destino'] = $caixa;
            }

            AuditoriaService::registrar(
                'SOLICITAR_AUXILIO', 'rotinas_execucoes', (string) $id,
                dadosNovos: $registro,
                descricao: 'Solicitação de auxílio',
                idEmpresa: (int) $execucao->id_empresa,
                idRotina: (int) $execucao->id_rotina,
            );

            return $caixa;
        });

        $mensagem = $caixa !== null
            ? "Solicitação de auxílio enviada à caixa de {$caixa} na Rotina de Acompanhamento."
            : 'Solicitação de auxílio registrada nos Logs de Rotinas.';

        return to_route('execucoes.realizando', $id)->with('success', $mensagem);
    }

    private function justificativa(Request $request): string
    {
        return $request->validate(['justificativa' => ['required', 'string', 'max:2000']], [
            'justificativa.required' => 'Informe a justificativa.',
            'justificativa.max' => 'Escreva uma justificativa de até 2.000 caracteres.',
        ])['justificativa'];
    }

    private function consultarExecucao(Request $request, int $id): object
    {
        $execucao = $this->rotinas->execucoesPessoais($request->user())->where('e.id_execucao', $id)->first();
        abort_unless($execucao, 404);

        $execucao->horario = Carbon::parse($execucao->previsto_em, 'UTC')->setTimezone(ExecucoesDiarias::FUSO)->format('H:i');
        $execucao->local = trim($execucao->local_rotina ?? '') ?: (trim($execucao->local_cadastrado ?? '') ?: 'Não informado');

        return $execucao;
    }

    private function validarEmAndamento(object $execucao): void
    {
        if ($execucao->status !== 'em_andamento' || ! $execucao->iniciado_em || $execucao->concluido_em) {
            throw ValidationException::withMessages(['execucao' => 'Esta ação exige uma tarefa em andamento.']);
        }
    }

    /** @return array{concluido_em: Carbon, id_usuario_conclusao: int} */
    private function encerramento(Request $request, object $execucao): array
    {
        $this->validarEmAndamento($execucao);
        $agora = now('UTC');
        if (
            ($execucao->tipo_rotina !== ExecucoesDiarias::TIPO_EMERGENCIAL && (int) $execucao->duracao_prevista_segundos <= 0)
            || $agora->lessThan(Carbon::parse($execucao->iniciado_em, 'UTC'))
        ) {
            throw ValidationException::withMessages(['execucao' => 'Revise a duração e as datas desta execução.']);
        }

        return ['concluido_em' => $agora, 'id_usuario_conclusao' => (int) $request->user()->getAuthIdentifier()];
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
        $usuario = $request->user();

        $consulta = DB::table('rotinas_execucoes as e')
            ->join('rotinas as r', fn ($vinculo) => $vinculo
                ->on('r.id_rotina', '=', 'e.id_rotina')->on('r.id_empresa', '=', 'e.id_empresa'))
            ->where('e.id_execucao', $id)
            ->where('r.status', true)->whereNull('r.excluida_em')
            ->when(! $usuario->isAdmin(), fn ($consulta) => $consulta->where('e.id_usuario_responsavel', $usuario->getKey()))
            ->whereIn('e.id_empresa', $this->rotinas->empresas($usuario));
        $execucao = $this->rotinas->filtrarEmergenciaisDisponiveis($consulta)->lockForUpdate()->first(['e.*', 'r.tipo_rotina']);

        abort_if(
            ! $execucao,
            404,
            'Execução não encontrada ou indisponível para este usuário.'
        );

        return $execucao;
    }

    private function caixaDoResponsavel(User $usuario, object $execucao): ?string
    {
        $responsavel = (int) $usuario->getKey() === (int) $execucao->id_usuario_responsavel
            ? $usuario
            : User::with('grupoAcesso')->find($execucao->id_usuario_responsavel);

        return $this->escalonamento->superiorDoGrupo($responsavel?->grupoAcesso?->grupo_acesso);
    }
}
