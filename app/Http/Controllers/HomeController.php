<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index(Request $request): View
    {
        $empresas = DB::table('empresa_usuario')
            ->select('id_empresa')
            ->where('id_usuario', $request->user()->getAuthIdentifier());

        $rotinas = DB::table('rotinas as r')
            ->leftJoin('tarefas as t', 'r.id_tarefa', '=', 't.id_tarefa')
            ->leftJoin('locais as l', 'r.id_local', '=', 'l.id_local')
            ->whereIn('r.id_empresa', $empresas)
            ->orderBy('r.horario')
            ->orderBy('r.id_rotina')
            ->get([
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
        }

        return view('home', compact('rotinas'));
    }
}
