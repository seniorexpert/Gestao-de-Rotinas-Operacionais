<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FerramentasController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'id' => 'nullable|integer|exists:ferramentas,id_ferramenta',
            'nome_ferramenta' => 'required|string|max:100',
        ]);

        $registro = [
            'ferramenta' => $dados['nome_ferramenta'],
            'updated_at' => now(),
        ];

        if (isset($dados['id'])) {
            DB::table('ferramentas')->where('id_ferramenta', $dados['id'])->update($registro);
        } else {
            DB::table('ferramentas')->insert([...$registro, 'status' => true, 'created_at' => now()]);
        }

        return back()->with('success', 'Ferramenta salva com sucesso.');
    }
}
