<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\ConsultaRotinas;
use App\Services\EscalonamentoAcompanhamento;
use App\Services\ExecucoesDiarias;
use App\Services\TextoEmTitulo;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use stdClass;

class RotinaAcompanhamentoController extends Controller
{
    private const ABAS = [
        'auxilio' => 'Solicitação de Auxílio',
        'nao_realizadas' => 'Tarefas Não Realizadas',
        'pendentes' => 'Tarefas em Atraso',
        'verificadas' => 'Verificadas',
    ];

    private const ABAS_POR_TIPO = [
        'atraso' => 'pendentes',
        'nao_realizada' => 'nao_realizadas',
        'auxilio' => 'auxilio',
    ];

    private const ITENS_POR_PAGINA = 24;

    public function __construct(
        private EscalonamentoAcompanhamento $escalonamento,
        private ExecucoesDiarias $execucoesDiarias,
        private ConsultaRotinas $rotinas,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        $filtros = $request->validate([
            ...$this->regrasDosFiltros(),
            'item' => ['nullable', 'integer', 'min:1'],
        ]);

        $busca = $filtros['busca'] ?? '';
        $agora = now()->toImmutable();
        $usuario = $request->user();
        $caixa = $usuario->isAdmin() ? null : $this->escalonamento->caixaDoUsuario($usuario);
        $nomeCaixa = $caixa ?? ($usuario->isAdmin() ? 'Todas as caixas' : 'Caixas das suas unidades');
        $this->execucoesDiarias->gerar($agora, $this->rotinas->empresas($usuario));

        if (isset($filtros['item'])) {
            $tipo = $this->tipoDoItem($filtros);
            $item = $this->rotinas->itensDeAcompanhamento($usuario, $tipo)
                ->leftJoin('usuarios as gerente', 'v.id_usuario_verificacao', '=', 'gerente.id_usuario')
                ->addSelect('v.acao', 'gerente.usuario as verificado_por')
                ->where($tipo === 'auxilio' ? 'v.id_solicitacao' : 'e.id_execucao', (int) $filtros['item'])
                ->first();

            abort_unless($item && $this->prepararItem($item, $agora, $caixa, $usuario), 404);

            $request->attributes->set(AuditoriaService::UNIDADE_HTTP, (int) $item->id_empresa);
            $request->attributes->set(AuditoriaService::ROTINA_HTTP, (int) $item->id_rotina);

            $auxiliares = $this->rotinas->auxiliaresDeRotinas([$item->id_rotina])
                ->where('a.id_usuario', '<>', $item->id_usuario_responsavel)
                ->pluck('u.usuario');

            $filtros['aba'] ??= $this->abaDoItem($item);

            return view('processos.acompanhamento.detalhes', [
                'item' => $this->formatarItem($item),
                'auxiliares' => $auxiliares,
                'caixa' => $nomeCaixa,
                'filtros' => array_diff_key($filtros, ['item' => true, 'tipo' => true]),
                'voltar' => $this->urlDaLista($usuario, $filtros),
            ]);
        }

        $aba = $filtros['aba'] ?? 'auxilio';
        $parametros = ['aba' => $aba, 'busca' => $filtros['busca'] ?? null, 'origem' => $filtros['origem'] ?? null];
        [$inicioDoDia, $fimDoDia] = ExecucoesDiarias::janela($agora);
        $execucoes = $this->rotinas->filaDeAcompanhamento($usuario, $agora)
            ->where(fn (Builder $fila) => $fila->whereNull('fila.verificado_em')
                ->orWhere(fn (Builder $verificadas) => $verificadas
                    ->where('fila.verificado_em', '>=', $inicioDoDia)
                    ->where('fila.verificado_em', '<', $fimDoDia)))
            ->orderByDesc('entrada')
            ->orderByDesc('id_execucao')
            ->orderByDesc('id_item')
            ->orderBy('tipo')
            ->cursor();

        $totais = array_fill_keys(array_keys(self::ABAS), 0);
        $termo = Str::lower(Str::ascii($busca));
        $pagina = (int) ($filtros['page'] ?? 1);
        $encontrados = 0;
        $resultados = [];

        foreach ($execucoes as $item) {
            if (! $this->prepararItem($item, $agora, $caixa, $usuario)) {
                continue;
            }

            $abaDoItem = $this->abaDoItem($item);
            $totais[$abaDoItem]++;

            if ($abaDoItem !== $aba || ($termo !== '' && ! $this->correspondeBusca($item, $termo))) {
                continue;
            }

            $encontrados++;
            if (intdiv($encontrados - 1, self::ITENS_POR_PAGINA) + 1 === $pagina) {
                $resultados[] = $this->formatarItem($item);
            }
        }

        $itens = new LengthAwarePaginator(
            $resultados,
            $encontrados,
            self::ITENS_POR_PAGINA,
            $pagina,
            ['path' => route('acompanhamento.index'), 'query' => $parametros],
        );

        if ($pagina > $itens->lastPage()) {
            $request->session()->keep('success');

            return to_route('acompanhamento.index', [...$parametros, 'page' => $itens->lastPage()]);
        }

        $view = $request->header('X-Painel-Acompanhamento') === '1' ? 'painel' : 'index';

        return view('processos.acompanhamento.'.$view, [
            'abas' => self::ABAS,
            'aba' => $aba,
            'caixa' => $nomeCaixa,
            'busca' => $busca,
            'totais' => $totais,
            'itens' => $itens,
            'parametros' => $parametros,
        ]);
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        TextoEmTitulo::naRequisicao($request, 'acao');
        $dados = $request->validate([
            ...$this->regrasDosFiltros(),
            'acao' => ['required', 'string', 'max:2000'],
        ], [
            'acao.required' => 'Descreva a ação realizada para concluir.',
            'acao.string' => 'A ação realizada deve ser informada em texto.',
            'acao.max' => 'A ação realizada deve ter até 2.000 caracteres.',
        ]);
        $usuario = $request->user();
        $caixa = $usuario->isAdmin() ? null : $this->escalonamento->caixaDoUsuario($usuario);

        $tipo = $this->tipoDoItem($dados);
        DB::transaction(function () use ($usuario, $caixa, $id, $dados, $tipo) {
            $tabela = ConsultaRotinas::TABELAS_ACOMPANHAMENTO[$tipo];
            $solicitacao = $tipo === 'auxilio';
            $registroDoItem = DB::table($tabela)->where($solicitacao ? 'id_solicitacao' : 'id_execucao', $id);
            $idExecucao = $solicitacao ? (clone $registroDoItem)->value('id_execucao') : $id;
            abort_unless($idExecucao, 404);

            $execucao = $this->rotinas->execucoesParaAcompanhamento($usuario)
                ->where('e.id_execucao', $idExecucao)
                ->lockForUpdate()
                ->first();

            abort_unless($execucao, 404);

            $registro = (clone $registroDoItem)->first();
            abort_if($tipo !== 'atraso' && ! $registro, 404);
            abort_if($tipo === 'nao_realizada' && $execucao->status !== 'nao_realizada', 404);

            if ($registro?->verificado_em !== null) {
                abort_unless($this->escalonamento->podeConsultarVerificacao($registro, $caixa, $usuario), 404);

                return;
            }

            $execucao->tipo = $tipo;
            if ($tipo !== 'atraso') {
                $execucao->caixa_inicial = $registro->caixa;
                $execucao->entrada = $registro->recebido_em;
            }

            $agora = now()->toImmutable();
            $envio = $this->escalonamento->encaminharItem($execucao, $agora);
            $destino = $caixa ?? array_key_first($envio['recebimentos'] ?? []);
            abort_unless(isset($envio['recebimentos'][$destino]), 404);

            $verificacao = [
                'id_usuario_verificacao' => $usuario->getKey(),
                'acao' => $dados['acao'],
                'verificado_em' => $agora,
                'encaminhamentos' => json_encode(array_map(
                    fn (CarbonImmutable $recebidoEm): string => $recebidoEm->toISOString(),
                    $envio['recebimentos'],
                ), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ];

            if ($tipo === 'atraso') {
                DB::table($tabela)->insert(['id_execucao' => $idExecucao, ...$verificacao]);
            } else {
                $registroDoItem->update($verificacao);
            }

            AuditoriaService::registrar(
                'VERIFICAR', $tabela, (string) $id,
                dadosNovos: ['id_execucao' => $idExecucao, 'caixa' => $destino, 'verificado_em' => $agora],
                descricao: 'Verificação concluída no acompanhamento',
                idEmpresa: (int) $execucao->id_empresa,
                idRotina: (int) $execucao->id_rotina,
            );
        }, 3);

        $dados['aba'] ??= self::ABAS_POR_TIPO[$tipo];

        return redirect()->to($this->urlDaLista($usuario, $dados))->with('success', 'Verificação concluída.');
    }

    /** @return array<string, array> */
    private function regrasDosFiltros(): array
    {
        return [
            'aba' => ['nullable', Rule::in(array_keys(self::ABAS))],
            'busca' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
            'tipo' => ['nullable', Rule::in(array_keys(ConsultaRotinas::TABELAS_ACOMPANHAMENTO))],
            'origem' => ['nullable', Rule::in(['diarias'])],
            'aba_diaria' => ['nullable', 'string', 'max:30'],
        ];
    }

    private function urlDaLista(User $usuario, array $filtros): string
    {
        $parametros = [
            'aba' => $filtros['aba'],
            'busca' => $filtros['busca'] ?? null,
            'page' => $filtros['page'] ?? null,
        ];

        if (($filtros['origem'] ?? null) === 'diarias' && $usuario->can('visualizar-modulo', 'rotinas_diarias')) {
            return route('home', [
                'aba' => $filtros['aba_diaria'] ?? null,
                'acomp_aba' => $parametros['aba'],
                'acomp_busca' => $parametros['busca'],
                'acomp_page' => $parametros['page'],
            ]);
        }

        return route('acompanhamento.index', $parametros);
    }

    private function tipoDoItem(array $filtros): string
    {
        return $filtros['tipo'] ?? match ($filtros['aba'] ?? null) {
            'auxilio' => 'auxilio',
            'nao_realizadas' => 'nao_realizada',
            default => 'atraso',
        };
    }

    private function prepararItem(stdClass $item, CarbonImmutable $agora, ?string $caixa, User $usuario): bool
    {
        $item->atraso_segundos = null;

        if ($item->verificado_em !== null) {
            return $this->escalonamento->podeConsultarVerificacao($item, $caixa, $usuario);
        }

        $envio = $this->escalonamento->encaminharItem($item, $agora);
        $recebimentos = $envio['recebimentos'] ?? [];
        $destino = $caixa ?? array_key_first($recebimentos);
        $recebidoEm = $recebimentos[$destino] ?? null;

        if ($recebidoEm === null) {
            return false;
        }

        $item->atraso_segundos = $envio['atraso_segundos'];
        $item->caixa_responsavel = $destino;
        $item->recebido_em = $recebidoEm->timezone(ExecucoesDiarias::FUSO);
        $auxilio = $item->tipo === 'auxilio';
        $item->limite_acompanhamento = $this->escalonamento->limite($recebidoEm, $auxilio)->timezone(ExecucoesDiarias::FUSO);
        $item->sla_acompanhamento = $this->escalonamento->situacao($recebidoEm, $agora, $auxilio);

        return true;
    }

    private function abaDoItem(stdClass $item): string
    {
        return $item->verificado_em !== null ? 'verificadas' : self::ABAS_POR_TIPO[$item->tipo];
    }

    private function correspondeBusca(stdClass $item, string $termo): bool
    {
        $texto = implode(' ', [
            $item->colaborador,
            $item->tarefa,
            $item->morador,
            $this->local($item),
            $this->processo($item),
            $item->justificativa,
        ]);

        return str_contains(Str::lower(Str::ascii($texto)), $termo);
    }

    private function local(stdClass $item): string
    {
        return trim($item->local_rotina ?? '') ?: (trim($item->local_cadastrado ?? '') ?: 'Não informado');
    }

    private function processo(stdClass $item): string
    {
        return match (true) {
            $item->tipo === 'auxilio' => 'Solicitação de Auxílio',
            $item->tipo_rotina === ExecucoesDiarias::TIPO_EMERGENCIAL => 'Tarefa Emergencial',
            $item->tipo === 'atraso' && $item->auxilio => 'Auxílio previsto',
            default => 'Rotinas Diárias',
        };
    }

    private function formatarItem(stdClass $item): stdClass
    {
        $item->local = $this->local($item);
        $item->processo = $this->processo($item);
        $item->tarefa = trim($item->tarefa ?? '') ?: 'Tarefa não localizada';
        $item->morador = trim($item->morador ?? '') ?: 'Não informado';
        $item->colaborador = trim($item->colaborador ?? '') ?: 'Colaborador não localizado';
        $item->horario = CarbonImmutable::parse($item->previsto_em, 'UTC')
            ->timezone(ExecucoesDiarias::FUSO)->locale('pt_BR');

        if ($item->atraso_segundos !== null) {
            $item->atraso = $item->atraso_segundos === 0
                ? 'menos de 1 segundo'
                : CarbonInterval::seconds($item->atraso_segundos)->cascade()->locale('pt_BR')
                    ->forHumans(['short' => true, 'parts' => 2]);
        }

        if ($item->verificado_em !== null) {
            $item->verificado_em = CarbonImmutable::parse($item->verificado_em, 'UTC')
                ->timezone(ExecucoesDiarias::FUSO);
        }

        return $item;
    }
}
