<?php

namespace App\Http\Controllers;

use App\Services\CadastroCatalogo;
use App\Services\TextoEmTitulo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class TarefasController extends Controller
{
    public function store(Request $request, CadastroCatalogo $catalogo): RedirectResponse
    {
        TextoEmTitulo::naRequisicao($request, 'nome_tarefa', 'descricao_tarefa');
        $dados = $request->validate([
            'id' => 'nullable|integer|min:1',
            'nome_tarefa' => 'required|string|max:100',
            'icone' => 'nullable|string|max:32',
            'cor_icone' => 'nullable|string|max:20',
            'tempo_tarefa' => 'required|numeric|min:0.01|max:1439.99',
            'status_tarefa' => 'required|in:Ativa,Inativa',
            'id_ferramenta' => 'nullable|integer|min:1',
            'id_empresas' => ['required', 'array', 'list', 'min:1'],
            'id_empresas.*' => ['required', 'integer', 'min:1', 'distinct'],
            'descricao_tarefa' => 'nullable|string',
            'manual_tarefa' => 'nullable|file|max:2048|mimes:pdf,doc,docx,odt,txt,png,jpg,jpeg,webp,xls,xlsx,csv,ods,ppt,pptx',
        ], ['id_empresas.required' => 'Selecione pelo menos uma unidade.']);

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
        ];

        if (empty($dados['id']) || array_key_exists('icone', $dados)) {
            $registro['icone'] = trim($dados['icone'] ?? '') ?: '📋';
        }

        if (empty($dados['id']) || array_key_exists('cor_icone', $dados)) {
            $registro['cor_icone'] = $dados['cor_icone'] ?? 'original';
        }

        if ($caminho) {
            $registro['documentacao'] = $caminho;
        }

        try {
            $catalogo->salvar($request->user(), 'tarefas', $dados['id'] ?? null, $registro, $dados['id_empresas'], 'tarefa');
        } catch (Throwable $erro) {
            if ($caminho) {
                Storage::disk('public')->delete($caminho);
            }

            throw $erro;
        }

        return back()->with('success', 'Tarefa salva com sucesso.');
    }
}
