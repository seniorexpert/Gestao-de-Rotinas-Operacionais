<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use App\Services\ConsultaRotinas;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MonitoramentoController extends Controller
{
    public function __construct(private ConsultaRotinas $consultaRotinas) {}

    public function index(Request $request): View
    {
        $usuario = $request->user();
        $rotinas = $this->consultaRotinas->rotinas($usuario)->orderBy('r.horario')->get([
            'r.id_rotina', 'r.id_usuario', 'r.horario', 'r.morador', 'r.status',
            'r.local', 'l.local as local_cadastrado', 'u.usuario', 't.tarefa',
        ]);
        $produtividades = $rotinas->groupBy('id_usuario')->map(fn ($tarefas) => (object) [
            'colaborador_id' => $tarefas->first()->id_usuario,
            'colaborador_nome' => $tarefas->first()->usuario,
            'total_tarefas' => $tarefas->count(),
            'ativas' => $tarefas->where('status', 1)->count(),
        ]);
        $colaboradores = $this->consultaRotinas->colaboradores($usuario)->get();

        return view('sla.centro-monitoramento', compact('produtividades', 'rotinas', 'colaboradores'));
    }

    public function atribuir(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'rotinas_ids' => 'required|array|min:1',
            'rotinas_ids.*' => 'required|integer|distinct',
            'novo_colaborador_id' => 'required|integer|exists:usuarios,id_usuario',
        ]);

        DB::transaction(function () use ($request, $dados) {
            $rotinas = DB::table('rotinas')
                ->whereIn('id_empresa', $this->consultaRotinas->empresas($request->user()))
                ->whereIn('id_rotina', $dados['rotinas_ids'])
                ->lockForUpdate()
                ->get(['id_rotina', 'id_empresa', 'id_usuario']);

            abort_unless($rotinas->count() === count($dados['rotinas_ids']), 404);

            $empresas = $rotinas->pluck('id_empresa')->unique();
            $totalVinculos = DB::table('empresa_usuario')
                ->where('id_usuario', $dados['novo_colaborador_id'])
                ->whereIn('id_empresa', $empresas)
                ->count();

            if ($totalVinculos !== $empresas->count()) {
                throw ValidationException::withMessages([
                    'novo_colaborador_id' => 'O colaborador deve estar vinculado às unidades das rotinas selecionadas.',
                ]);
            }

            DB::table('rotinas')->whereIn('id_rotina', $rotinas->pluck('id_rotina'))
                ->update(['id_usuario' => $dados['novo_colaborador_id'], 'updated_at' => now()]);

            foreach ($rotinas as $rotina) {
                if ((int) $rotina->id_usuario !== (int) $dados['novo_colaborador_id']) {
                    AuditoriaService::registrar('UPDATE', 'rotinas', (string) $rotina->id_rotina, [
                        'id_usuario' => $rotina->id_usuario,
                    ], ['id_usuario' => $dados['novo_colaborador_id']], 'Responsável da rotina alterado');
                }
            }
        });

        return back()->with('success', 'Tarefas atribuídas com sucesso!');
    }
}
