<?php

namespace App\Http\Controllers;

use App\Services\CalculadoraSla;
use App\Services\ConsultaRotinas;
use App\Services\ExecucoesDiarias;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

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

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $agora = now()->toImmutable();
        $abas = self::ABAS;
        $aba = $request->query('aba');
        $aba = is_string($aba) && isset($abas[$aba]) ? $aba : 'pendente';

        $this->execucoesDiarias->gerar($agora, $this->consultaRotinas->empresas($usuario));
        $rotinas = $this->consultaRotinas->execucoesPessoais($usuario, $agora)
            ->whereIn('e.status', ['pendente', 'em_andamento', 'concluida', 'nao_realizada'])
            ->orderBy('e.previsto_em')->orderBy('e.id_execucao')->get();

        foreach ($rotinas as $rotina) {
            $rotina->local = trim($rotina->local_rotina ?? '') ?: (trim($rotina->local_cadastrado ?? '') ?: 'Não informado');
            $rotina->horario = $rotina->horario ? substr($rotina->horario, 0, 5) : 'Não informado';
            $rotina->aba = $rotina->status === 'nao_realizada' ? 'concluida' : $rotina->status;
            $rotina->url_documentacao = $rotina->documentacao ? Storage::disk('public')->url($rotina->documentacao) : null;
            $rotina->situacao_sla = null;
            $rotina->prazo_execucao = null;
            $rotina->acao_execucao = match (true) {
                $rotina->status === 'pendente' && (bool) $rotina->rotina_ativa => [
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

        return view('home', compact('rotinas', 'abas', 'aba'));
    }
}
