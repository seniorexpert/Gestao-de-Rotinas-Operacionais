<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TarefasController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'nome_tarefa' => 'required|string|max:100',
            'tempo_tarefa' => 'required',
            'status_tarefa' => 'required|string|max:10',
            'ferramentas_tarefa' => 'nullable|array',
            'descricao_tarefa' => 'nullable|string',
            'manual_tarefa' => 'nullable|file|max:2048',
        ]);

        $pathArquivo = null;
        if ($request->hasFile('manual_tarefa')) {
            $pathArquivo = $request->file('manual_tarefa')->store('arquivos_tarefas', 'public');
        }

        $ferramentasStr = $request->has('ferramentas_tarefa') ? implode(', ', $request->ferramentas_tarefa) : null;

        DB::table('gr_tarefas')->insert([
            'nome_tarefa' => $request->nome_tarefa,
            'tempo_tarefa' => $request->tempo_tarefa,
            'status_tarefa' => $request->status_tarefa,
            'ferramentas_tarefa' => $ferramentasStr,
            'descricao_tarefa' => $request->descricao_tarefa,
            'manual_tarefa' => $pathArquivo,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()->back();
    }
}