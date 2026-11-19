<?php

namespace App\Http\Controllers;

use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class ForgotPasswordController extends Controller
{
    public function edit()
    {
        return view('auth.forgot-password');
    }

    public function update(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string', 'confirmed'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $request) {
            $user->update(['password' => Hash::make($request->password)]);
            AuditoriaService::registrar(
                'ALTERAR_SENHA', 'usuarios', (string) $user->getKey(),
                descricao: 'Alteração da própria senha',
            );
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Senha alterada com sucesso!');
    }
}
