<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TarefasController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'id' => 'nullable|integer|exists:tarefas,id_tarefa',
            'nome_tarefa' => 'required|string|max:100',
            'tempo_tarefa' => 'required|numeric|min:0.01|max:1439.99',
            'status_tarefa' => 'required|in:Ativa,Inativa',
            'id_ferramenta' => 'nullable|integer|exists:ferramentas,id_ferramenta',
            'descricao_tarefa' => 'nullable|string',
            'manual_tarefa' => 'nullable|file|max:2048|mimes:pdf,doc,docx,odt,txt,png,jpg,jpeg,webp,xls,xlsx,csv,ods,ppt,pptx',
        ]);

        $segundos = (int) round($dados['tempo_tarefa'] * 60);
        $caminho = $request->file('manual_tarefa')?->store('arquivos_tarefas', 'public');
        if ($caminho === false) {
            throw ValidationException::withMessages(['manual_tarefa' => 'O arquivo não pôde ser salvo.']);
        }

        $registro = [
            'tarefa' => $dados['nome_tarefa'],
            'tempo_realizar_tarefa' => sprintf('%02d:%02d:%02d', intdiv($segundos, 3600), intdiv($segundos % 3600, 60), $segundos % 60),
            'status' => $dados['status_tarefa'] === 'Ativa',
            'id_ferramenta' => $dados['id_ferramenta'] ?? null,
            'descricao' => $dados['descricao_tarefa'] ?? null,
            'updated_at' => now(),
        ];

        if ($caminho) {
            $registro['documentacao'] = $caminho;
        }

        try {
            if (isset($dados['id'])) {
                DB::table('tarefas')->where('id_tarefa', $dados['id'])->update($registro);
            } else {
                DB::table('tarefas')->insert([...$registro, 'created_at' => now()]);
            }
        } catch (Throwable $erro) {
            if ($caminho) {
                Storage::disk('public')->delete($caminho);
            }

            throw $erro;
        }

        return back()->with('success', 'Tarefa salva com sucesso.');
    }
}
