<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\GrupoAcesso;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioController extends Controller
{
    public function store(Request $request)
    {
        $dados = $request->validate([
            'usuario' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:100', 'unique:usuarios,username'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'id_grupo_acesso' => ['nullable', 'integer', 'exists:grupos_acesso,id_grupo_acesso'],
            'empresas' => ['required', 'array', 'min:1'],
            'empresas.*' => ['required', 'integer', 'distinct', 'exists:empresas,id_empresa'],
        ]);

        DB::transaction(function () use ($dados) {
            $grupo = !empty($dados['id_grupo_acesso'])
                ? GrupoAcesso::findOrFail($dados['id_grupo_acesso'])
                : null;

            $usuario = User::create([
                'usuario' => $dados['usuario'],
                'username' => $dados['username'],
                'password' => $dados['password'],
                'id_grupo_acesso' => $grupo?->id_grupo_acesso,
                'grupo_acesso_nome' => $grupo?->grupo_acesso,
                'status' => true,
            ]);

            $empresas = Empresa::whereIn('id_empresa', $dados['empresas'])->get();

            $vinculos = [];

            foreach ($empresas as $empresa) {
                $vinculos[$empresa->id_empresa] = [
                    'empresa_nome' => $empresa->empresa,
                ];
            }

            $usuario->empresas()->sync($vinculos);
        });

        return back()->with('success', 'Usuário cadastrado com sucesso.');
    }
}