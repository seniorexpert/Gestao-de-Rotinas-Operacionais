<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use App\Services\CalculadoraSla;
use App\Services\ConsultaRotinas;
use App\Services\EscalonamentoAcompanhamento;
use App\Services\ExecucoesDiarias;
use App\Services\VisibilidadeGrupos;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RotinasFinalizadasController extends Controller
{
    private const PROCESSOS = [
        ExecucoesDiarias::TIPO_DIARIA => 'Rotinas Diárias',
        ExecucoesDiarias::TIPO_EMERGENCIAL => 'Tarefas Emergenciais',
        'acompanhamento' => 'Rotinas de Acompanhamento',
        'auxilio' => 'Solicitações de Auxílio',
        'nao_realizada' => 'Tarefas Não Realizadas',
    ];

    private const TIPOS_VERIFICADOS = [
        'acompanhamento' => 'atraso',
        'auxilio' => 'auxilio',
        'nao_realizada' => 'nao_realizada',
    ];

    public function __construct(
        private ConsultaRotinas $rotinas,
        private CalculadoraSla $sla,
        private EscalonamentoAcompanhamento $escalonamento,
        private VisibilidadeGrupos $visibilidade,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->filtros($request);
        $processo = $filtros['processo'] ?? ExecucoesDiarias::TIPO_DIARIA;
        $consulta = $this->consulta($request, $processo);
        $finalizadores = (clone $consulta)->whereNotNull('id_finalizador')
            ->select('finalizador as usuario', 'id_grupo_finalizador as id_grupo_acesso', 'grupo_finalizador as grupo_acesso')
            ->distinct()->orderBy('usuario')->get();
        $registros = $this->filtrar($consulta, $filtros)->paginate(10)->appends($filtros)
            ->through(fn (stdClass $registro): stdClass => $this->apresentar($registro, $processo));

        return view('relatorios.rotinas-finalizadas', [
            'registros' => $registros,
            'filtros' => $filtros,
            'processo' => $processo,
            'rotuloResultado' => isset(self::TIPOS_VERIFICADOS[$processo]) ? 'Ação realizada' : 'Justificativa',
            'processos' => $request->user()->can('visualizar-modulo', 'acompanhamento')
                ? self::PROCESSOS : array_diff_key(self::PROCESSOS, self::TIPOS_VERIFICADOS),
            'colaboradores' => $finalizadores->groupBy('usuario'),
            'grupos' => $finalizadores->whereNotNull('id_grupo_acesso')->unique('id_grupo_acesso')->sortBy('grupo_acesso'),
        ]);
    }

    public function detalhes(Request $request, int $id): View
    {
        $filtros = $this->filtros($request);
        $processo = $filtros['processo'] ?? ExecucoesDiarias::TIPO_DIARIA;
        $registro = $this->apresentar($this->buscarRegistro($request, $id, $processo), $processo);
        $registro->programado_em = $this->dataLocal($registro->previsto_em);
        $auxiliares = $registro->auxilio
            ? $this->rotinas->auxiliaresDeRotinas([$registro->id_rotina])
                ->when($this->visibilidade->grupos($request->user()), fn (Builder $consulta, array $grupos) => $consulta->whereIntegerInRaw('u.id_grupo_acesso', $grupos))
                ->where('a.id_usuario', '<>', $registro->id_usuario_responsavel)->pluck('u.usuario')
            : collect();
        $voltar = route('relatorios.rotinas-finalizadas', $filtros + $request->only('page'));
        $caminhoFoto = $registro->foto_conclusao ?? null;
        $foto = $caminhoFoto && Storage::disk('local')->exists($caminhoFoto)
            ? route('relatorios.rotinas-finalizadas.foto', ['id' => $id] + array_intersect_key($filtros, ['processo' => true]))
            : null;

        return view('relatorios.rotinas-finalizadas-detalhes', compact('registro', 'voltar', 'foto', 'processo', 'auxiliares'));
    }

    public function foto(Request $request, int $id): StreamedResponse
    {
        $processo = $this->filtros($request)['processo'] ?? ExecucoesDiarias::TIPO_DIARIA;
        $registro = $this->buscarRegistro($request, $id, $processo);
        $disco = Storage::disk('local');
        $caminho = $registro->foto_conclusao ?? null;
        abort_unless($caminho && $disco->exists($caminho), 404);

        $tipo = $disco->mimeType($caminho);
        abort_unless(in_array($tipo, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/bmp', 'image/x-ms-bmp'], true), 404);

        return $disco->response($caminho, headers: [
            'Content-Type' => $tipo,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $filtros = $this->filtros($request);
        $processo = $filtros['processo'] ?? ExecucoesDiarias::TIPO_DIARIA;
        $registros = $this->filtrar($this->consulta($request, $processo), $filtros)
            ->cursor()->map(fn (stdClass $registro): stdClass => $this->apresentar($registro, $processo));
        $rotuloResultado = isset(self::TIPOS_VERIFICADOS[$processo]) ? 'Ação realizada' : 'Justificativa';

        return response()->streamDownload(function () use ($registros, $rotuloResultado): void {
            echo view('relatorios.rotinas-finalizadas-exportacao', compact('registros', 'rotuloResultado'))->render();
        }, 'relatorio-rotinas-finalizadas-'.now(ExecucoesDiarias::FUSO)->toDateString().'.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
        ]);
    }

    private function consulta(Request $request, string $processo): Builder
    {
        $usuario = $request->user();
        $consulta = $this->rotinas->execucoes($usuario)->addSelect('t.descricao', 't.icone', 't.cor_icone', 'r.obs as observacao', 'r.auxilio');
        $tipoVerificacao = self::TIPOS_VERIFICADOS[$processo] ?? null;
        $encerradoEm = 'e.concluido_em';
        $idFinalizador = 'e.id_usuario_conclusao';
        $idItem = 'e.id_execucao';

        if ($tipoVerificacao !== null) {
            abort_unless($usuario->can('visualizar-modulo', 'acompanhamento'), 403);
            $tabela = ConsultaRotinas::TABELAS_ACOMPANHAMENTO[$tipoVerificacao];
            $request->attributes->set(AuditoriaService::TABELA_HTTP, $tabela);
            $consulta->join($tabela.' as v', 'e.id_execucao', '=', 'v.id_execucao')
                ->addSelect('v.acao as resultado_finalizacao', 'v.encaminhamentos');
            $this->escalonamento->filtrarVerificacoes($consulta, $usuario);
            $encerradoEm = 'v.verificado_em';
            $idFinalizador = 'v.id_usuario_verificacao';

            if ($tipoVerificacao === 'auxilio') {
                $idItem = 'v.id_solicitacao';
                $consulta->addSelect('v.recebido_em', 'v.justificativa as justificativa_origem');
            } elseif ($tipoVerificacao === 'nao_realizada') {
                $consulta->where('e.status', 'nao_realizada')
                    ->addSelect('v.recebido_em', 'e.justificativa_nao_realizada as justificativa_origem');
            }
        } else {
            $consulta->where('r.tipo_rotina', $processo)->where('e.status', 'concluida')
                ->addSelect('e.comentario_conclusao as resultado_finalizacao', 'e.justificativa as justificativa_legada', 'e.foto_conclusao');
        }

        $consulta->whereNotNull($encerradoEm)
            ->when($this->visibilidade->colaboradores($usuario), fn (Builder $consulta, Builder $visiveis) => $consulta
                ->where(fn (Builder $finalizadores) => $finalizadores->whereNull($idFinalizador)->orWhereIn($idFinalizador, $visiveis)))
            ->leftJoin('usuarios as conclusao', $idFinalizador, '=', 'conclusao.id_usuario')
            ->leftJoin('grupos_acesso as grupo_conclusao', 'conclusao.id_grupo_acesso', '=', 'grupo_conclusao.id_grupo_acesso')
            ->addSelect($encerradoEm.' as encerrado_em', $idItem.' as id_item', 'conclusao.id_usuario as id_finalizador',
                'conclusao.usuario as finalizador', 'grupo_conclusao.id_grupo_acesso as id_grupo_finalizador',
                'grupo_conclusao.grupo_acesso as grupo_finalizador');

        return DB::query()->fromSub($consulta, 'finalizadas');
    }

    private function buscarRegistro(Request $request, int $id, string $processo): stdClass
    {
        $registro = $this->consulta($request, $processo)->where('id_item', $id)->first();
        abort_unless($registro, 404);

        $request->attributes->set(AuditoriaService::UNIDADE_HTTP, (int) $registro->id_empresa);
        $request->attributes->set(AuditoriaService::ROTINA_HTTP, (int) $registro->id_rotina);

        return $registro;
    }

    /** @return array{processo?: string, data_inicio?: string, data_fim?: string, grupo?: int, colaborador?: string} */
    private function filtros(Request $request): array
    {
        $dados = $request->validate([
            'processo' => ['nullable', 'string', Rule::in(array_keys(self::PROCESSOS))],
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('data_inicio'), 'after_or_equal:data_inicio')],
            'grupo' => ['nullable', 'integer', 'min:1'],
            'colaborador' => ['nullable', 'string', 'max:255'],
        ], [
            'processo.in' => 'Selecione um tipo de processo válido.',
            'data_inicio.date_format' => 'Informe uma data inicial válida.',
            'data_fim.date_format' => 'Informe uma data final válida.',
            'data_fim.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
        ]);

        return array_filter([
            'processo' => ($dados['processo'] ?? ExecucoesDiarias::TIPO_DIARIA) === ExecucoesDiarias::TIPO_DIARIA ? null : $dados['processo'],
            'data_inicio' => $dados['data_inicio'] ?? null,
            'data_fim' => $dados['data_fim'] ?? null,
            'grupo' => isset($dados['grupo']) ? (int) $dados['grupo'] : null,
            'colaborador' => ($dados['colaborador'] ?? null) === 'Todas' ? null : ($dados['colaborador'] ?? null),
        ], filled(...));
    }

    /** @param array{processo?: string, data_inicio?: string, data_fim?: string, grupo?: int, colaborador?: string} $filtros */
    private function filtrar(Builder $consulta, array $filtros): Builder
    {
        return $consulta
            ->when(isset($filtros['data_inicio']), fn (Builder $itens) => $itens->where('encerrado_em', '>=',
                CarbonImmutable::parse($filtros['data_inicio'], ExecucoesDiarias::FUSO)->utc()))
            ->when(isset($filtros['data_fim']), fn (Builder $itens) => $itens->where('encerrado_em', '<',
                CarbonImmutable::parse($filtros['data_fim'], ExecucoesDiarias::FUSO)->addDay()->utc()))
            ->when(isset($filtros['grupo']), fn (Builder $itens) => $itens->where('id_grupo_finalizador', $filtros['grupo']))
            ->when(isset($filtros['colaborador']), fn (Builder $itens) => $itens->where('finalizador', $filtros['colaborador']))
            ->orderByDesc('encerrado_em')->orderByDesc('id_item');
    }

    private function apresentar(stdClass $registro, string $processo): stdClass
    {
        $registro->processo_nome = self::PROCESSOS[$processo];
        $registro->verificado = isset(self::TIPOS_VERIFICADOS[$processo]);
        $registro->situacao = $registro->verificado ? 'Verificada' : 'Concluída';
        $registro->finalizado_em = $this->dataLocal($registro->encerrado_em);
        if ($registro->verificado) {
            $inicio = $this->recebimento($registro);
            $referencia = $inicio ?? $registro->finalizado_em;
            $planejado = (int) $referencia->diffInSeconds($this->escalonamento->limite($referencia, $processo === 'auxilio'));
        } else {
            $inicio = $registro->iniciado_em ? $this->dataLocal($registro->iniciado_em) : null;
            $planejado = $this->sla->duracaoPrevista($registro);
        }
        $executado = $inicio && $inicio <= $registro->finalizado_em
            ? (int) $inicio->diffInSeconds($registro->finalizado_em)
            : null;
        $registro->tempo_execucao = $executado === null ? null : $this->formatarDuracao($executado);
        $registro->tempo_planejado = $planejado > 0 ? $this->formatarDuracao($planejado) : null;
        $registro->acima_do_planejado = $executado !== null && $planejado > 0 && $executado > $planejado;
        $registro->tarefa = $registro->tarefa ?? $registro->descricao ?? 'Tarefa de rotina';
        $registro->local = trim((string) $registro->local_rotina) ?: (trim((string) $registro->local_cadastrado) ?: 'Não informado');
        $registro->morador = trim((string) $registro->morador) ?: 'Não informado';
        $registro->finalizador = $registro->finalizador ?? 'Não registrado';
        $registro->justificativa = filled($registro->resultado_finalizacao)
            ? $registro->resultado_finalizacao : ($registro->justificativa_legada ?? null);

        return $registro;
    }

    private function recebimento(stdClass $registro): ?CarbonImmutable
    {
        $recebimentos = json_decode($registro->encaminhamentos ?? '[]', true);
        $recebidoEm = $registro->recebido_em ?? (is_array($recebimentos) ? collect($recebimentos)->min() : null);

        try {
            return is_string($recebidoEm) && filled($recebidoEm) ? $this->dataLocal($recebidoEm) : null;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function dataLocal(string $data): CarbonImmutable
    {
        return CarbonImmutable::parse($data, 'UTC')->setTimezone(ExecucoesDiarias::FUSO)->locale('pt_BR');
    }

    private function formatarDuracao(int $segundos): string
    {
        return sprintf('%02d:%02d:%02d', intdiv($segundos, 3600), intdiv($segundos % 3600, 60), $segundos % 60);
    }
}
