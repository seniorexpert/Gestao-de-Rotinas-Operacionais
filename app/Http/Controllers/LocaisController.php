<?php

namespace App\Http\Controllers;

use App\Services\CadastroCatalogo;
use App\Services\TextoEmTitulo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaisController extends Controller
{
    public function store(Request $request, CadastroCatalogo $catalogo): RedirectResponse
    {
        TextoEmTitulo::naRequisicao($request, 'nome_local');
        $dados = $request->validate([
            'id' => 'nullable|integer|min:1',
            'nome_local' => 'required|string|max:100',
            'id_empresa' => ['required', 'integer', 'min:1'],
            'status' => ['sometimes', 'boolean'],
        ], [
            'id_empresa.required' => 'Selecione uma unidade.',
        ]);

        $catalogo->salvar($request->user(), 'locais', $dados['id'] ?? null, [
            'local' => $dados['nome_local'],
            ...isset($dados['status']) ? ['status' => (bool) $dados['status']] : [],
        ], [$dados['id_empresa']], 'local');

        return back()->with('success', 'Local salvo com sucesso.');
    }
}
