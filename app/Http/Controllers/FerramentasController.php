<?php

namespace App\Http\Controllers;

use App\Services\CadastroCatalogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FerramentasController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'id' => 'nullable|integer|exists:ferramentas,id_ferramenta',
            'nome_ferramenta' => 'required|string|max:100',
        ]);

        CadastroCatalogo::salvar('ferramentas', 'id_ferramenta', $dados['id'] ?? null, [
            'ferramenta' => $dados['nome_ferramenta'],
        ], 'ferramenta');

        return back()->with('success', 'Ferramenta salva com sucesso.');
    }
}
