<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return redirect()->intended('home');
        }

        return back()->withErrors([
            'username' => 'As credenciais fornecidas não batem com os nossos registros.',
        ])->onlyInput('username');
    }

    public function index()
    {
        $users = User::all();
        return view('auth.user-management', compact('users'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:teste'],
            'password' => ['required', 'string'],
            'group' => ['nullable', 'string'],
            'profile' => ['nullable', 'string'],
            'unit' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('users', 'public');
        }

        User::create([
            'name' => $request->name,
            'username' => $request->username,
            'group' => $request->group,
            'profile' => $request->profile,
            'unit' => $request->unit,
            'photo' => $photoPath, 
            'password' => Hash::make($request->password),
        ]);

        return back()->with('status', 'Usuário criado com sucesso!');
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'group' => ['nullable', 'string'],
            'profile' => ['nullable', 'string'],
            'unit' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        $data = [
            'name' => $request->name,
            'group' => $request->group,
            'profile' => $request->profile,
            'unit' => $request->unit,
        ];

        if ($request->input('remove_photo') == '1') {
            $data['photo'] = null;
        } 
        elseif ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('users', 'public');
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return back()->with('status', 'Usuário atualizado com sucesso!');
    }
}