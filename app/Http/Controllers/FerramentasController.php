<?php

namespace App\Http\Controllers;

use App\Services\CadastroCatalogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FerramentasController extends Controller
{
    public function store(Request $request, CadastroCatalogo $catalogo): RedirectResponse
    {
        $dados = $request->validate([
            'id' => 'nullable|integer|min:1',
            'nome_ferramenta' => 'required|string|max:100',
            'id_empresas' => ['required', 'array', 'list', 'min:1'],
            'id_empresas.*' => ['required', 'integer', 'min:1', 'distinct'],
            'status' => ['sometimes', 'boolean'],
        ], [
            'id_empresas.required' => 'Selecione pelo menos uma unidade.',
        ]);

        $catalogo->salvar($request->user(), 'ferramentas', $dados['id'] ?? null, [
            'ferramenta' => $dados['nome_ferramenta'],
            ...isset($dados['status']) ? ['status' => (bool) $dados['status']] : [],
        ], $dados['id_empresas'], 'ferramenta');

        return back()->with('success', 'Ferramenta salva com sucesso.');
    }
}
