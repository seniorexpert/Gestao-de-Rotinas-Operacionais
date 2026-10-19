<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.required' => 'Insira a sua palavra-passe atual.',
            'password.required' => 'Insira a nova palavra-passe.',
            'password.min' => 'A nova palavra-passe deve ter pelo menos 8 caracteres.',
            'password.confirmed' => 'A confirmação da nova palavra-passe não coincide.',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'A palavra-passe atual está incorreta.'
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password)
        ]);

        return back()->with('status', 'Palavra-passe alterada com sucesso!');
    }
}