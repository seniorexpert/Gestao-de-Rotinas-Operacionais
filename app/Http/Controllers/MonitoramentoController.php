<?php 

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MonitoramentoController extends Controller
{
    public function index()
    {
        $produtividades = DB::table('gr_rotinas')
            ->join('gr_usuarios', 'gr_rotinas.id_usuario', '=', 'gr_usuarios.id')
            ->select(
                'gr_usuarios.name as colaborador_nome',
                'gr_usuarios.id as colaborador_id',
                DB::raw('count(gr_rotinas.id_rotina) as total_tarefas'),
                DB::raw('sum(case when gr_rotinas.status = 1 then 1 else 0 end) as ativas')
            )
            ->groupBy('gr_usuarios.id', 'gr_usuarios.name')
            ->get();

        return view('sla.centro-monitoramento', compact('produtividades'));
    }

    public function atribuir(Request $request)
    {
        $request->validate([
            'rotinas_ids' => 'required|array',
            'novo_colaborador_id' => 'required'
        ]);

        DB::table('gr_rotinas')
            ->whereIn('id_rotina', $request->rotinas_ids)
            ->update(['id_usuario' => $request->novo_colaborador_id]);

        return redirect()->back()->with('success', 'Tarefas atribuídas com sucesso!');
    }
}