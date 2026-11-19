<?php

namespace App\Http\Controllers;

use App\Services\CalculadoraSla;
use App\Services\ConsultaRotinas;
use App\Services\ExecucoesDiarias;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HomeController extends Controller
{
    public function __construct(
        private ConsultaRotinas $consultaRotinas,
        private ExecucoesDiarias $execucoesDiarias,
        private CalculadoraSla $sla,
    ) {}

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $agora = now()->toImmutable();
        $empresas = $this->consultaRotinas->empresas($usuario);
        $this->execucoesDiarias->gerar($agora, $empresas);
        $execucoes = $this->consultaRotinas->execucoesDoDia($usuario, $agora)
            ->whereIn('e.status', ['pendente', 'em_andamento', 'concluida'])
            ->orderBy('e.previsto_em')->get()->groupBy('id_rotina');

        $rotinas = DB::table('rotinas as r')
            ->leftJoin('tarefas as t', 'r.id_tarefa', '=', 't.id_tarefa')
            ->leftJoin('locais as l', 'r.id_local', '=', 'l.id_local')
            ->whereIn('r.id_empresa', $empresas)
            ->orderBy('r.horario')
            ->orderBy('r.id_rotina')
            ->get([
                'r.id_rotina',
                't.tarefa',
                'r.horario',
                'r.morador',
                'r.local as local_rotina',
                'l.local as local_cadastrado',
                'r.auxilio',
                'r.obs',
                'r.status',
            ]);

        foreach ($rotinas as $rotina) {
            $rotina->local = trim($rotina->local_rotina ?? '') ?: (trim($rotina->local_cadastrado ?? '') ?: 'Não informado');
            $rotina->horario = substr($rotina->horario, 0, 5);
            $itens = $execucoes->get($rotina->id_rotina);
            $execucao = $itens?->firstWhere('status', 'em_andamento') ?? $itens?->firstWhere('status', 'pendente') ?? $itens?->first();
            $rotina->estado = $execucao?->status;
            $rotina->acao_execucao = null;
            $rotina->sla_execucao = null;
            $rotina->prazo_execucao = null;

            if ($execucao === null) {
                continue;
            }

            $rotina->horario = CarbonImmutable::parse($execucao->previsto_em, 'UTC')->setTimezone(ExecucoesDiarias::FUSO)->format('H:i');
            if ((int) $execucao->id_usuario_responsavel === (int) $usuario->getKey()) {
                $rotina->acao_execucao = match (true) {
                    $execucao->status === 'pendente' && (bool) $rotina->status => [
                        'url' => route('execucoes.iniciar', $execucao->id_execucao), 'rotulo' => 'Iniciar tarefa',
                    ],
                    $execucao->status === 'em_andamento' => [
                        'url' => route('execucoes.concluir', $execucao->id_execucao), 'rotulo' => 'Finalizar tarefa',
                    ],
                    default => null,
                };
            }

            try {
                $resultado = $this->sla->calcular($execucao, $agora);
                $rotina->sla_execucao = CalculadoraSla::ROTULOS[$resultado['sla_atual']];
                $rotina->prazo_execucao = $resultado['prazo_em']->setTimezone(ExecucoesDiarias::FUSO)->format('d/m H:i:s');
            } catch (InvalidArgumentException) {
                $rotina->sla_execucao = 'Sem dados de SLA';
            }
        }

        return view('home', compact('rotinas'));
    }
}
