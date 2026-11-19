<?php

namespace App\Http\Controllers;

use App\Services\CadastroCatalogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaisController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'id' => 'nullable|integer|exists:locais,id_local',
            'nome_local' => 'required|string|max:100',
        ]);

        CadastroCatalogo::salvar('locais', 'id_local', $dados['id'] ?? null, [
            'local' => $dados['nome_local'],
        ], 'local');

        return back()->with('success', 'Local salvo com sucesso.');
    }
}
