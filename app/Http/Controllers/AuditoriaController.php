<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\ConsultaRotinas;
use App\Services\VisibilidadeGrupos;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditoriaController extends Controller
{
    private const FUSO = 'America/Sao_Paulo';

    private const UNIDADE = 'COALESCE(a.id_empresa, r.id_empresa)';

    private const AUTOR = 'COALESCE(a.usuario_nome, executor.usuario)';

    private const LOGIN = 'COALESCE(a.usuario_login, executor.username)';

    private const EVENTO = 'COALESCE(a.descricao, a.acao)';

    private const GRUPO = 'COALESCE(grupo.grupo_acesso, colaborador.grupo_acesso_nome)';

    private const ROTINAS = ['rotinas', 'rotinas_execucoes', 'rotinas_verificacoes', 'rotinas_acomp_nao_realizadas', 'rotinas_acomp_auxilios'];

    private const RECURSOS = [
        'sessoes' => 'Sessões',
        'requisicoes' => 'Navegação',
        'usuarios' => 'Usuários',
        'empresa_usuario' => 'Vínculos de unidade',
        'permissoes_grupos_acesso' => 'Permissões de grupos',
        'permissoes_usuarios' => 'Permissões individuais',
        VisibilidadeGrupos::TABELA => 'Visibilidade entre grupos',
        'tarefas' => 'Tarefas',
        'locais' => 'Locais',
        'ferramentas' => 'Ferramentas',
        'rotinas' => 'Rotinas',
        'rotinas_execucoes' => 'Execuções',
        'rotinas_verificacoes' => 'Acompanhamento',
        'rotinas_acomp_nao_realizadas' => 'Acompanhamento de tarefas não realizadas',
        'rotinas_acomp_auxilios' => 'Acompanhamento de solicitações de auxílio',
        'logs_auditoria' => 'Auditoria',
    ];

    private const ACOES = [
        'ACESSAR' => 'Acesso',
        'CONSULTAR' => 'Consulta',
        'ATUALIZAR_PAINEL' => 'Atualização de painel',
        'EXPORTAR' => 'Exportação',
        'LOGIN' => 'Entrada',
        'LOGOUT' => 'Saída',
        'ALTERAR_SENHA' => 'Alteração de senha',
        'CREATE' => 'Cadastro',
        'UPDATE' => 'Edição',
        'DELETE' => 'Remoção',
        'INICIAR' => 'Início de execução',
        'CONCLUIR' => 'Conclusão de execução',
        'NAO_REALIZAR' => 'Tarefa não realizada',
        'SOLICITAR_AUXILIO' => 'Solicitação de auxílio',
        'VERIFICAR' => 'Verificação',
        'SALVAR' => 'Gravação',
        'OPERACAO' => 'Operação',
    ];

    private const ACOES_ANTIGAS = [
        'CREATE' => ['criado'],
        'UPDATE' => ['atualizado'],
        'DELETE' => ['removido', 'excluido'],
    ];

    private const RESULTADOS = [
        'sucesso' => 'Sucesso',
        'sem_alteracao' => 'Sem alteração',
        'invalido' => 'Dados inválidos',
        'recusado' => 'Recusado',
        'falha' => 'Falha',
        'erro' => 'Erro interno',
        'historico' => 'Registro anterior',
    ];

    public function __construct(private ConsultaRotinas $rotinas, private VisibilidadeGrupos $visibilidade) {}

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $visaoGeral = $this->visaoGeral($usuario);
        $filtros = $this->filtros($request);
        $logs = $this->projetar($this->filtrar($this->consulta($usuario, $visaoGeral), $filtros))
            ->orderByDesc('a.id_log')->paginate(25)->appends($filtros)
            ->through(fn (object $log): object => $this->formatar($log));

        return view('relatorios.logs-rotinas', [
            'logs' => $logs,
            'filtros' => $filtros,
            'titulo' => $visaoGeral ? 'Logs e Auditoria' : 'Logs de Rotinas',
            'recursos' => match (true) {
                $usuario->isAdmin() => self::RECURSOS,
                $visaoGeral => Arr::except(self::RECURSOS, VisibilidadeGrupos::TABELA),
                default => Arr::only(self::RECURSOS, self::ROTINAS),
            },
            'acoes' => self::ACOES,
            'resultados' => self::RESULTADOS,
            'unidades' => DB::table('empresas')
                ->when(! $visaoGeral, fn (Builder $consulta) => $consulta->whereIn('id_empresa', $this->rotinas->empresas($usuario)))
                ->orderBy('empresa')->pluck('empresa', 'id_empresa'),
        ]);
    }

    public function detalhes(Request $request, int $id): View
    {
        $usuario = $request->user();
        $visaoGeral = $this->visaoGeral($usuario);
        $log = $this->buscarLog($usuario, $visaoGeral, $id);
        abort_unless($log, 404);

        $antes = AuditoriaService::lerDados($log->dados_anteriores);
        $depois = AuditoriaService::lerDados($log->dados_novos);
        $campos = array_unique([...array_keys($antes), ...array_keys($depois)]);
        $alteracoes = [];
        foreach ($campos as $campo) {
            $anterior = $antes[$campo] ?? null;
            $novo = $depois[$campo] ?? null;
            if ($anterior !== $novo) {
                $alteracoes[$campo] = ['antes' => $this->formatarValor($anterior), 'depois' => $this->formatarValor($novo)];
            }
        }

        return view('relatorios.detalhes-auditoria', [
            'log' => $this->formatar($log),
            'visaoGeral' => $visaoGeral,
            'alteracoes' => $alteracoes,
            'filtros' => $this->filtros($request),
        ]);
    }

    public function apiDetalhes(Request $request, int $id): JsonResponse
    {
        $usuario = $request->user();
        $log = $this->buscarLog($usuario, $this->visaoGeral($usuario), $id);

        return $log ? response()->json(Arr::only((array) $log, [
            'id', 'created_at', 'data_evento', 'tarefa', 'detalhe_tarefa', 'colaborador_original',
            'executor', 'descricao_evento', 'grupo_acesso', 'situacao',
        ])) : response()->json(['error' => 'Log não encontrado'], 404);
    }

    public function exportar(Request $request): StreamedResponse
    {
        $usuario = $request->user();
        $filtros = $this->filtros($request);
        $ultimoId = DB::table('logs_auditoria')->max('id_log') ?? 0;
        $logs = $this->projetar($this->filtrar($this->consulta($usuario, $this->visaoGeral($usuario)), $filtros))
            ->where('a.id_log', '<=', $ultimoId)->lazyByIdDesc(250, 'a.id_log', 'id');

        return response()->streamDownload(function () use ($logs) {
            echo '<meta charset="utf-8"><table border="1"><thead><tr>'
                .'<th>ID</th><th>Data e hora (Brasília)</th><th>Usuário</th><th>Login</th><th>Ação</th><th>Evento</th>'
                .'<th>Recurso</th><th>Registro</th><th>Unidade</th><th>Tarefa</th><th>Colaborador responsável</th><th>Grupo do responsável</th>'
                .'<th>Resultado</th><th>Origem</th><th>Rota</th><th>HTTP</th>'
                .'</tr></thead><tbody>';
            foreach ($logs as $registro) {
                $log = $this->formatar($registro);
                $valores = [
                    '#'.$log->id, $log->data_local, $log->autor, $log->login, $log->acao_label, $log->descricao_evento,
                    $log->recurso, $log->registro_id, $log->empresa, $log->tarefa, $log->colaborador_original, $log->grupo_acesso,
                    $log->resultado_label, $log->origem, $log->rota, $log->status_http,
                ];
                echo '<tr>';
                foreach ($valores as $valor) {
                    $texto = (string) $valor;
                    echo '<td>'.e(preg_match('/^\s*[=+\-@]/u', $texto) ? "'".$texto : $texto).'</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table>';
        }, 'relatorio-logs-rotinas-'.now(self::FUSO)->format('Y-m-d').'.xls', ['Content-Type' => 'application/vnd.ms-excel; charset=utf-8']);
    }

    private function visaoGeral(User $usuario): bool
    {
        return $usuario->can('auditoria-geral') && $this->visibilidade->grupos($usuario) === null;
    }

    private function consulta(User $usuario, bool $visaoGeral): Builder
    {
        return DB::table('logs_auditoria as a')
            ->leftJoin('rotinas as r', fn (JoinClause $join) => $join
                ->on('a.id_rotina', '=', 'r.id_rotina')
                ->on('a.id_empresa', '=', 'r.id_empresa')
                ->orOn(fn (JoinClause $legado) => $legado->whereNull('a.id_rotina')->where('a.tabela', 'rotinas')
                    ->on('a.registro_id', '=', DB::raw('CAST(r.id_rotina AS VARCHAR(32))'))))
            ->leftJoin('empresas as empresa', 'empresa.id_empresa', '=', DB::raw(self::UNIDADE))
            ->leftJoin('tarefas as t', 'r.id_tarefa', '=', 't.id_tarefa')
            ->leftJoin('usuarios as colaborador', 'r.id_usuario', '=', 'colaborador.id_usuario')
            ->leftJoin('grupos_acesso as grupo', 'colaborador.id_grupo_acesso', '=', 'grupo.id_grupo_acesso')
            ->leftJoin('usuarios as executor', 'a.id_usuario', '=', 'executor.id_usuario')
            ->when(! $usuario->isAdmin(), fn (Builder $consulta) => $consulta->where('a.tabela', '<>', VisibilidadeGrupos::TABELA))
            ->when(! $visaoGeral, fn (Builder $consulta) => $consulta
                ->whereIn('a.tabela', self::ROTINAS)
                ->whereIn(DB::raw(self::UNIDADE), $this->rotinas->empresas($usuario)))
            ->when($this->visibilidade->colaboradores($usuario), fn (Builder $consulta, Builder $visiveis) => $consulta
                ->where(fn (Builder $responsaveis) => $responsaveis->whereNull('r.id_usuario')->orWhereIn('r.id_usuario', $visiveis))
                ->where(fn (Builder $autores) => $autores->whereIn('a.id_usuario', $visiveis)
                    ->orWhere(fn (Builder $sistema) => $sistema->whereNotNull('r.id_usuario')
                        ->whereNull('a.id_usuario')->whereNull('a.usuario_nome')->whereNull('a.usuario_login'))));
    }

    private function projetar(Builder $consulta): Builder
    {
        return $consulta->select(
            'a.id_log as id', 'a.created_at', 'a.created_at as data_evento', 'a.id_usuario', 'a.acao', 'a.tabela',
            'a.registro_id', 'a.origem', 'a.rota', 'a.metodo_http', 'a.status_http', 'a.resultado',
            't.tarefa', 'colaborador.usuario as colaborador_original', 'empresa.empresa',
        )->selectRaw(self::EVENTO.' as descricao_evento, '.self::GRUPO.' as grupo_acesso, '.self::AUTOR.' as executor, '.self::LOGIN.' as login')
            ->selectRaw("CASE WHEN r.status = 1 THEN 'Ativa' WHEN r.status = 0 THEN 'Inativa' END as situacao");
    }

    private function buscarLog(User $usuario, bool $visaoGeral, int $id): ?object
    {
        $consulta = $this->projetar($this->consulta($usuario, $visaoGeral))
            ->addSelect('a.dados_anteriores', 'a.dados_novos', 'r.obs as detalhe_tarefa')
            ->where('a.id_log', $id);

        if ($visaoGeral) {
            $consulta->addSelect('a.ip', 'a.user_agent');
        }

        return $consulta->first();
    }

    /** @return array<string, int|string|null> */
    private function filtros(Request $request): array
    {
        return $request->validate([
            'data_inicio' => ['nullable', 'date_format:Y-m-d'],
            'data_fim' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('data_inicio'), 'after_or_equal:data_inicio')],
            'data_criacao' => ['nullable', 'date_format:Y-m-d'],
            'data_evento' => ['nullable', 'date_format:Y-m-d'],
            'nome_executor' => ['nullable', 'string', 'max:255'],
            'id_empresa' => ['nullable', 'integer', 'min:1'],
            'tabela' => ['nullable', Rule::in(array_keys(self::RECURSOS))],
            'acao' => ['nullable', Rule::in(array_keys(self::ACOES))],
            'resultado' => ['nullable', Rule::in(array_keys(self::RESULTADOS))],
            'registro_id' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'tarefa' => ['nullable', 'string', 'max:255'],
            'descricao_evento' => ['nullable', 'string', 'max:255'],
            'grupo_acesso' => ['nullable', 'string', 'max:255'],
            'colaborador_original' => ['nullable', 'string', 'max:255'],
        ], [
            'data_fim.after_or_equal' => 'A data final deve ser igual ou posterior à inicial.',
        ]);
    }

    /** @param array<string, int|string|null> $filtros */
    private function filtrar(Builder $consulta, array $filtros): Builder
    {
        foreach (['data_criacao', 'data_evento'] as $campo) {
            if (isset($filtros[$campo])) {
                $consulta->whereDate('a.created_at', $filtros[$campo]);
            }
        }

        if (isset($filtros['data_inicio'])) {
            $consulta->where('a.created_at', '>=', CarbonImmutable::parse($filtros['data_inicio'], self::FUSO)->startOfDay()->utc());
        }
        if (isset($filtros['data_fim'])) {
            $consulta->where('a.created_at', '<', CarbonImmutable::parse($filtros['data_fim'], self::FUSO)->startOfDay()->addDay()->utc());
        }
        if (isset($filtros['nome_executor'])) {
            $termo = '%'.$filtros['nome_executor'].'%';
            $consulta->where(fn (Builder $autor) => $autor->where(DB::raw(self::AUTOR), 'like', $termo)->orWhere(DB::raw(self::LOGIN), 'like', $termo));
        }
        if (isset($filtros['acao'])) {
            $acao = $filtros['acao'];
            $consulta->whereIn('a.acao', [$acao, ...(self::ACOES_ANTIGAS[$acao] ?? [])]);
        }
        if (($filtros['resultado'] ?? null) === 'historico') {
            $consulta->whereNull('a.resultado');
        } elseif (isset($filtros['resultado'])) {
            $consulta->where('a.resultado', $filtros['resultado']);
        }
        if (isset($filtros['tarefa'])) {
            $consulta->where('t.tarefa', 'like', '%'.$filtros['tarefa'].'%');
        }

        foreach (['colaborador_original' => 'colaborador.usuario', 'grupo_acesso' => self::GRUPO] as $campo => $coluna) {
            if (isset($filtros[$campo]) && ! in_array($filtros[$campo], ['Todos', 'Todas'], true)) {
                $comparacao = $consulta->getConnection()->getDriverName() === 'sqlsrv'
                    ? "{$coluna} COLLATE Latin1_General_100_CI_AS = ?"
                    : "LOWER({$coluna}) = LOWER(?)";
                $consulta->whereRaw($comparacao, [$filtros[$campo]]);
            }
        }

        foreach ([
            'id_empresa' => DB::raw(self::UNIDADE), 'tabela' => 'a.tabela', 'registro_id' => 'a.registro_id',
            'descricao_evento' => DB::raw(self::EVENTO),
        ] as $campo => $coluna) {
            if (isset($filtros[$campo]) && ! in_array($filtros[$campo], ['Todos', 'Todas'], true)) {
                $consulta->where($coluna, $filtros[$campo]);
            }
        }

        return $consulta;
    }

    private function formatar(object $log): object
    {
        $acao = match (strtolower($log->acao)) {
            'criado' => 'CREATE',
            'atualizado' => 'UPDATE',
            'removido', 'excluido' => 'DELETE',
            default => strtoupper($log->acao),
        };
        $log->acao_label = self::ACOES[$acao] ?? $log->acao;
        $log->recurso = self::RECURSOS[$log->tabela] ?? $log->tabela;
        $log->resultado_label = self::RESULTADOS[$log->resultado ?? 'historico'] ?? $log->resultado;
        $log->data_local = CarbonImmutable::parse($log->created_at, 'UTC')->setTimezone(self::FUSO)->format('d/m/Y H:i:s');
        $log->autor = $log->executor ?? match (true) {
            $log->id_usuario !== null => 'Usuário #'.$log->id_usuario,
            $acao === 'LOGIN' => 'Visitante',
            default => 'Sistema',
        };

        return $log;
    }

    private function formatarValor(mixed $valor): string
    {
        return match (true) {
            $valor === null => '—',
            is_bool($valor) => $valor ? 'Sim' : 'Não',
            is_array($valor) => json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR),
            default => (string) $valor,
        };
    }
}
