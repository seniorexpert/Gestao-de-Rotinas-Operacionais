<?php

namespace App\Http\Controllers;

use App\Services\CalculadoraSla;
use App\Services\ConsultaRotinas;
use App\Services\ExecucoesDiarias;
use App\Services\PermissoesAcesso;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use stdClass;

class HomeController extends Controller
{
    private const ABAS = [
        'pendente' => 'Pendentes',
        'em_andamento' => 'Em andamento',
        'concluida' => 'Concluídas',
        'todas' => 'Todas',
    ];

    public function __construct(
        private ConsultaRotinas $consultaRotinas,
        private ExecucoesDiarias $execucoesDiarias,
        private CalculadoraSla $sla,
    ) {}

    public function entrada(Request $request): View|RedirectResponse
    {
        foreach (PermissoesAcesso::MODULOS as $modulo => $dados) {
            if ($request->user()->can('visualizar-modulo', $modulo)) {
                return to_route($dados['rota']);
            }
        }

        return view('auth.sem-modulos');
    }

    public function index(Request $request): View|string
    {
        $usuario = $request->user();
        $editar = $usuario->can('editar-modulo', 'rotinas_diarias');
        $agora = now()->toImmutable();
        $abas = self::ABAS;
        $aba = $request->query('aba');
        $aba = is_string($aba) && isset($abas[$aba]) ? $aba : 'pendente';

        $this->execucoesDiarias->gerar(
            $agora,
            $this->consultaRotinas->empresas($usuario),
            idUsuario: $usuario->isAdmin() ? null : (int) $usuario->getKey(),
        );
        $rotinas = $this->consultaRotinas->execucoesPessoais($usuario, $agora)
            ->whereIn('e.status', ['pendente', 'em_andamento', 'nao_realizada'])
            ->get()
            ->merge($this->consultaRotinas->concluidasNaJornada($usuario, $agora))
            ->keyBy('id_execucao')
            ->sortBy([['previsto_em', 'asc'], ['id_execucao', 'asc']])
            ->sortByDesc(fn (stdClass $rotina): int => $rotina->status === 'pendente' && $rotina->tipo_rotina === ExecucoesDiarias::TIPO_EMERGENCIAL
                ? (int) $rotina->id_execucao
                : 0)
            ->values();

        foreach ($rotinas as $rotina) {
            $rotina->local = trim($rotina->local_rotina ?? '') ?: (trim($rotina->local_cadastrado ?? '') ?: 'Não informado');
            $previsto = CarbonImmutable::parse($rotina->previsto_em, 'UTC')->setTimezone(ExecucoesDiarias::FUSO);
            $horario = $rotina->horario ? substr($rotina->horario, 0, 5) : $previsto->format('H:i');
            $rotina->horario = $horario.' · '.$previsto->format('d/m/Y');
            $rotina->data_conclusao = $rotina->status === 'concluida'
                ? CarbonImmutable::parse($rotina->concluido_em, 'UTC')->setTimezone(ExecucoesDiarias::FUSO)
                : null;
            $rotina->url_documentacao = $rotina->documentacao ? Storage::disk('public')->url($rotina->documentacao) : null;
            $rotina->situacao_sla = null;
            $rotina->prazo_execucao = null;
            $rotina->acao_execucao = match (true) {
                $editar && $rotina->status === 'pendente' && (bool) $rotina->rotina_ativa => [
                    'url' => route('execucoes.iniciar', $rotina->id_execucao), 'rotulo' => 'Iniciar tarefa',
                ],
                default => null,
            };

            if ($rotina->status === 'nao_realizada') {
                continue;
            }

            try {
                $resultado = $this->sla->calcular($rotina, $agora);
                $rotina->situacao_sla = $resultado['sla_atual'];
                $rotina->prazo_execucao = $resultado['prazo_em']->setTimezone(ExecucoesDiarias::FUSO)->format('d/m H:i:s');
            } catch (InvalidArgumentException) {
                // As execuções permanecem visíveis quando há dados incompletos de SLA.
            }
        }

        $view = view('home', compact('rotinas', 'abas', 'aba'));

        return $request->header('X-Painel-Rotinas-Diarias') === '1' ? $view->fragment('lista-rotinas') : $view;
    }
}
