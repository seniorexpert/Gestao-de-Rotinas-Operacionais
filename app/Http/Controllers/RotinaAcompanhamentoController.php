<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\ConsultaRotinas;
use App\Services\EscalonamentoAcompanhamento;
use App\Services\ExecucoesDiarias;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
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
        'pendentes' => 'Pendentes',
        'verificadas' => 'Verificadas',
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
        $caixa = $this->escalonamento->caixaDoUsuario($usuario);
        abort_if($caixa === null, 403);
        $this->execucoesDiarias->gerar($agora, $this->rotinas->empresas($usuario));

        $consulta = $this->itens($usuario);

        if (isset($filtros['item'])) {
            $item = $consulta
                ->leftJoin('usuarios as gerente', 'v.id_usuario_verificacao', '=', 'gerente.id_usuario')
                ->addSelect('v.acao', 'gerente.usuario as verificado_por')
                ->where('e.id_execucao', (int) $filtros['item'])
                ->first();

            abort_unless($item && $this->prepararItem($item, $agora, $caixa, $usuario), 404);

            $request->attributes->set(AuditoriaService::UNIDADE_HTTP, (int) $item->id_empresa);
            $request->attributes->set(AuditoriaService::ROTINA_HTTP, (int) $item->id_rotina);

            $auxiliares = DB::table('rotina_auxiliares as a')
                ->join('usuarios as u', 'u.id_usuario', '=', 'a.id_usuario')
                ->where('a.id_rotina', $item->id_rotina)
                ->where('a.id_usuario', '<>', $item->id_usuario_responsavel)
                ->orderBy('u.usuario')
                ->pluck('u.usuario');

            return view('processos.acompanhamento.detalhes', [
                'item' => $this->formatarItem($item),
                'auxiliares' => $auxiliares,
                'caixa' => $caixa,
                'filtros' => [
                    'aba' => $filtros['aba'] ?? $this->abaDoItem($item),
                    'busca' => $filtros['busca'] ?? null,
                    'page' => $filtros['page'] ?? null,
                ],
            ]);
        }

        $aba = $filtros['aba'] ?? 'pendentes';
        $parametros = ['aba' => $aba, 'busca' => $filtros['busca'] ?? null];
        $execucoes = $consulta
            ->where(function (Builder $query) use ($agora) {
                $query->whereNotNull('v.id_execucao')
                    ->orWhere(fn (Builder $pendentes) => $pendentes
                        ->whereIn('e.status', ['pendente', 'em_andamento', 'concluida'])
                        ->where(fn (Builder $horarios) => $horarios
                            ->where('e.previsto_em', '<=', $agora)
                            ->orWhere('e.iniciado_em', '<=', $agora)));
            })
            ->orderByDesc('e.previsto_em')
            ->orderByDesc('e.id_execucao')
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

        return view('processos.acompanhamento.index', [
            'abas' => self::ABAS,
            'aba' => $aba,
            'caixa' => $caixa,
            'busca' => $busca,
            'totais' => $totais,
            'itens' => $itens,
            'parametros' => $parametros,
        ]);
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        $dados = $request->validate([
            ...$this->regrasDosFiltros(),
            'acao' => ['required', 'string', 'max:2000'],
        ], [
            'acao.required' => 'Descreva a ação realizada para concluir.',
            'acao.string' => 'A ação realizada deve ser informada em texto.',
            'acao.max' => 'A ação realizada deve ter até 2.000 caracteres.',
        ]);
        $usuario = $request->user();
        $caixa = $this->escalonamento->caixaDoUsuario($usuario);
        abort_if($caixa === null, 403);

        DB::transaction(function () use ($usuario, $caixa, $id, $dados) {
            $execucao = $this->execucoesDaUnidade($usuario)
                ->where('e.id_execucao', $id)
                ->lockForUpdate()
                ->first();

            abort_unless($execucao, 404);

            $verificacao = DB::table('rotinas_verificacoes')->where('id_execucao', $id)->first();

            if ($verificacao) {
                abort_unless($this->podeConsultarVerificacao($verificacao, $caixa, $usuario), 404);

                return;
            }

            $agora = now()->toImmutable();
            $envio = $this->escalonamento->encaminhar($execucao, $agora);
            abort_unless(isset($envio['recebimentos'][$caixa]), 404);

            DB::table('rotinas_verificacoes')->insert([
                'id_execucao' => $id,
                'id_usuario_verificacao' => $usuario->getKey(),
                'acao' => $dados['acao'],
                'verificado_em' => $agora,
                'encaminhamentos' => json_encode(array_map(
                    fn (CarbonImmutable $recebidoEm): string => $recebidoEm->toISOString(),
                    $envio['recebimentos'],
                ), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);

            AuditoriaService::registrar(
                'VERIFICAR', 'rotinas_verificacoes', (string) $id,
                dadosNovos: ['id_execucao' => $id, 'caixa' => $caixa, 'verificado_em' => $agora],
                descricao: 'Verificação concluída no acompanhamento',
                idEmpresa: (int) $execucao->id_empresa,
                idRotina: (int) $execucao->id_rotina,
            );
        }, 3);

        return to_route('acompanhamento.index', [
            'aba' => $dados['aba'] ?? 'pendentes',
            'busca' => $dados['busca'] ?? null,
            'page' => $dados['page'] ?? null,
        ])->with('success', 'Verificação concluída.');
    }

    /** @return array{aba: array, busca: array, page: array} */
    private function regrasDosFiltros(): array
    {
        return [
            'aba' => ['nullable', Rule::in(array_keys(self::ABAS))],
            'busca' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function execucoesDaUnidade(User $usuario): Builder
    {
        return DB::table('rotinas_execucoes as e')
            ->join('rotinas as r', fn (JoinClause $join) => $join
                ->on('e.id_rotina', '=', 'r.id_rotina')
                ->on('e.id_empresa', '=', 'r.id_empresa'))
            ->leftJoin('usuarios as u', 'e.id_usuario_responsavel', '=', 'u.id_usuario')
            ->leftJoin('grupos_acesso as g', 'u.id_grupo_acesso', '=', 'g.id_grupo_acesso')
            ->whereIn('e.id_empresa', DB::table('empresa_usuario')
                ->select('id_empresa')
                ->where('id_usuario', $usuario->getKey()))
            ->select([
                'e.id_execucao',
                'e.id_rotina',
                'e.id_empresa',
                'e.id_usuario_responsavel',
                'e.previsto_em',
                'e.prazo_em',
                'e.iniciado_em',
                'e.concluido_em',
                'e.duracao_prevista_segundos',
                'e.tempo_limite_minutos',
                'e.status',
                'u.usuario as colaborador',
                'g.grupo_acesso as grupo_origem',
            ]);
    }

    private function itens(User $usuario): Builder
    {
        return $this->execucoesDaUnidade($usuario)
            ->leftJoin('tarefas as t', 'r.id_tarefa', '=', 't.id_tarefa')
            ->leftJoin('locais as l', 'r.id_local', '=', 'l.id_local')
            ->leftJoin('rotinas_verificacoes as v', 'e.id_execucao', '=', 'v.id_execucao')
            ->addSelect([
                't.tarefa',
                'r.morador',
                'r.local as local_rotina',
                'l.local as local_cadastrado',
                'r.auxilio',
                'v.verificado_em',
                'v.id_usuario_verificacao',
                'v.encaminhamentos',
            ]);
    }

    private function prepararItem(stdClass $item, CarbonImmutable $agora, string $caixa, User $usuario): bool
    {
        $item->atraso_segundos = null;

        if ($item->verificado_em !== null) {
            return $this->podeConsultarVerificacao($item, $caixa, $usuario);
        }

        $envio = $this->escalonamento->encaminhar($item, $agora);
        $recebidoEm = $envio['recebimentos'][$caixa] ?? null;

        if ($recebidoEm === null) {
            return false;
        }

        $item->atraso_segundos = $envio['atraso_segundos'];
        $item->recebido_em = $recebidoEm->timezone(ExecucoesDiarias::FUSO);
        $item->limite_acompanhamento = $this->escalonamento->limite($recebidoEm)->timezone(ExecucoesDiarias::FUSO);
        $item->sla_acompanhamento = $this->escalonamento->situacao($recebidoEm, $agora);

        return true;
    }

    private function podeConsultarVerificacao(stdClass $verificacao, string $caixa, User $usuario): bool
    {
        if ((string) $verificacao->id_usuario_verificacao === (string) $usuario->getKey()) {
            return true;
        }

        if ($verificacao->encaminhamentos === null) {
            return in_array($caixa, [
                EscalonamentoAcompanhamento::GERENTE_DA_UNIDADE,
                EscalonamentoAcompanhamento::GERENTE_GERAL,
            ], true);
        }

        $recebimentos = json_decode($verificacao->encaminhamentos, true);

        return isset($recebimentos[$caixa]);
    }

    private function abaDoItem(stdClass $item): string
    {
        return $item->verificado_em === null ? 'pendentes' : 'verificadas';
    }

    private function correspondeBusca(stdClass $item, string $termo): bool
    {
        $texto = implode(' ', [
            $item->colaborador,
            $item->tarefa,
            $item->morador,
            $this->local($item),
            $this->processo($item),
        ]);

        return str_contains(Str::lower(Str::ascii($texto)), $termo);
    }

    private function local(stdClass $item): string
    {
        return trim($item->local_rotina ?? '') ?: (trim($item->local_cadastrado ?? '') ?: 'Não informado');
    }

    private function processo(stdClass $item): string
    {
        return $item->auxilio ? 'Solicitação de Auxílio' : 'Rotinas Diárias';
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
