<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocaisController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'id' => 'nullable|integer|exists:locais,id_local',
            'nome_local' => 'required|string|max:100',
        ]);

        $registro = [
            'local' => $dados['nome_local'],
            'updated_at' => now(),
        ];

        if (isset($dados['id'])) {
            DB::table('locais')->where('id_local', $dados['id'])->update($registro);
        } else {
            DB::table('locais')->insert([...$registro, 'status' => true, 'created_at' => now()]);
        }

        return back()->with('success', 'Local salvo com sucesso.');
    }
}
