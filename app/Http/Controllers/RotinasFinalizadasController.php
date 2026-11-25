<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\CalculadoraSla;
use App\Services\ConsultaRotinas;
use App\Services\ExecucoesDiarias;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RotinasFinalizadasController extends Controller
{
    public function __construct(
        private ConsultaRotinas $rotinas,
        private CalculadoraSla $sla,
    ) {}

    public function index(Request $request): View
    {
        $filtros = $this->filtros($request);
        $consulta = $this->consulta($request->user());
        $finalizadores = (clone $consulta)->whereNotNull('conclusao.id_usuario')
            ->select('conclusao.usuario', 'grupo_conclusao.id_grupo_acesso', 'grupo_conclusao.grupo_acesso')
            ->distinct()->orderBy('conclusao.usuario')->get();
        $registros = $this->filtrar($consulta, $filtros)->paginate(10)->appends($filtros)->through($this->apresentar(...));

        return view('relatorios.rotinas-finalizadas', [
            'registros' => $registros,
            'filtros' => $filtros,
            'colaboradores' => $finalizadores->groupBy('usuario'),
            'grupos' => $finalizadores->whereNotNull('id_grupo_acesso')->unique('id_grupo_acesso')->sortBy('grupo_acesso'),
        ]);
    }

    public function detalhes(Request $request, int $id): View
    {
        $registro = $this->apresentar($this->buscarRegistro($request, $id));
        $registro->programado_em = $this->dataLocal($registro->previsto_em);
        $voltar = route('relatorios.rotinas-finalizadas', $this->filtros($request) + $request->only('page'));
        $foto = $registro->foto_conclusao && Storage::disk('local')->exists($registro->foto_conclusao)
            ? route('relatorios.rotinas-finalizadas.foto', $id)
            : null;

        return view('relatorios.rotinas-finalizadas-detalhes', compact('registro', 'voltar', 'foto'));
    }

    public function foto(Request $request, int $id): StreamedResponse
    {
        $registro = $this->buscarRegistro($request, $id);
        $disco = Storage::disk('local');
        $caminho = $registro->foto_conclusao;
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
        $registros = $this->filtrar($this->consulta($request->user()), $this->filtros($request))
            ->cursor()->map($this->apresentar(...));

        return response()->streamDownload(function () use ($registros): void {
            echo view('relatorios.rotinas-finalizadas-exportacao', compact('registros'))->render();
        }, 'relatorio-rotinas-finalizadas-'.now(ExecucoesDiarias::FUSO)->toDateString().'.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
        ]);
    }

    private function consulta(User $usuario): Builder
    {
        return $this->rotinas->execucoes($usuario)
            ->where('r.tipo_rotina', ExecucoesDiarias::TIPO_DIARIA)
            ->where('e.status', 'concluida')->whereNotNull('e.concluido_em')
            ->leftJoin('usuarios as conclusao', 'e.id_usuario_conclusao', '=', 'conclusao.id_usuario')
            ->leftJoin('grupos_acesso as grupo_conclusao', 'conclusao.id_grupo_acesso', '=', 'grupo_conclusao.id_grupo_acesso')
            ->addSelect('conclusao.usuario as finalizador', 'grupo_conclusao.grupo_acesso as grupo_finalizador',
                'e.comentario_conclusao', 'e.justificativa', 't.descricao', 't.icone', 't.cor_icone');
    }

    private function buscarRegistro(Request $request, int $id): stdClass
    {
        $registro = $this->consulta($request->user())->where('e.id_execucao', $id)
            ->addSelect('e.foto_conclusao', 'r.obs as observacao')->first();
        abort_unless($registro, 404);

        $request->attributes->set(AuditoriaService::UNIDADE_HTTP, (int) $registro->id_empresa);
        $request->attributes->set(AuditoriaService::ROTINA_HTTP, (int) $registro->id_rotina);

        return $registro;
    }

    /** @return array{data_inicio?: string, data_fim?: string, grupo?: int, colaborador?: string} */
    private function filtros(Request $request): array
    {
        $dados = $request->validate([
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('data_inicio'), 'after_or_equal:data_inicio')],
            'grupo' => ['nullable', 'integer', 'min:1'],
            'colaborador' => ['nullable', 'string', 'max:255'],
        ], [
            'data_inicio.date_format' => 'Informe uma data inicial válida.',
            'data_fim.date_format' => 'Informe uma data final válida.',
            'data_fim.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
        ]);

        return array_filter([
            'data_inicio' => $dados['data_inicio'] ?? null,
            'data_fim' => $dados['data_fim'] ?? null,
            'grupo' => isset($dados['grupo']) ? (int) $dados['grupo'] : null,
            'colaborador' => ($dados['colaborador'] ?? null) === 'Todas' ? null : ($dados['colaborador'] ?? null),
        ], filled(...));
    }

    /** @param array{data_inicio?: string, data_fim?: string, grupo?: int, colaborador?: string} $filtros */
    private function filtrar(Builder $consulta, array $filtros): Builder
    {
        return $consulta
            ->when(isset($filtros['data_inicio']), fn (Builder $itens) => $itens->where('e.concluido_em', '>=',
                CarbonImmutable::parse($filtros['data_inicio'], ExecucoesDiarias::FUSO)->utc()))
            ->when(isset($filtros['data_fim']), fn (Builder $itens) => $itens->where('e.concluido_em', '<',
                CarbonImmutable::parse($filtros['data_fim'], ExecucoesDiarias::FUSO)->addDay()->utc()))
            ->when(isset($filtros['grupo']), fn (Builder $itens) => $itens->where('grupo_conclusao.id_grupo_acesso', $filtros['grupo']))
            ->when(isset($filtros['colaborador']), fn (Builder $itens) => $itens->where('conclusao.usuario', $filtros['colaborador']))
            ->orderByDesc('e.concluido_em')->orderByDesc('e.id_execucao');
    }

    private function apresentar(stdClass $registro): stdClass
    {
        $registro->finalizado_em = $this->dataLocal($registro->concluido_em);
        $inicio = $registro->iniciado_em ? $this->dataLocal($registro->iniciado_em) : null;
        $executado = $inicio && $inicio <= $registro->finalizado_em
            ? (int) $inicio->diffInSeconds($registro->finalizado_em)
            : null;
        $planejado = $this->sla->duracaoPrevista($registro);
        $registro->tempo_execucao = $executado === null ? null : $this->formatarDuracao($executado);
        $registro->tempo_planejado = $planejado > 0 ? $this->formatarDuracao($planejado) : null;
        $registro->acima_do_planejado = $executado !== null && $planejado > 0 && $executado > $planejado;
        $registro->tarefa = $registro->tarefa ?? $registro->descricao ?? 'Tarefa de rotina';
        $registro->local = trim((string) $registro->local_rotina) ?: (trim((string) $registro->local_cadastrado) ?: 'Não informado');
        $registro->morador = trim((string) $registro->morador) ?: 'Não informado';
        $registro->finalizador = $registro->finalizador ?? 'Não registrado';
        $registro->justificativa = filled($registro->comentario_conclusao) ? $registro->comentario_conclusao : $registro->justificativa;

        return $registro;
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
