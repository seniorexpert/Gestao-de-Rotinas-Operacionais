<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ProcessoController;
use App\Http\Controllers\LocaisController;
use App\Http\Controllers\FerramentasController;
use App\Http\Controllers\TarefasController;
use Illuminate\Support\Facades\DB;

Route::get('/api/usuarios-por-unidade', function (Request $request) {
    $unitName = $request->query('unit');
    $usuarios = DB::table('gr_usuarios')->where('unit', $unitName)->orderBy('name')->get();
    return response()->json($usuarios);
});

Route::post('/ferramentas', [FerramentasController::class, 'store'])->name('ferramentas.store');
Route::post('/tarefas', [TarefasController::class, 'store'])->name('tarefas.store');
Route::post('/locais', [LocaisController::class, 'store'])->name('locais.store');

Route::get('/iniciar-processos', [ProcessoController::class, 'index'])->name('processos.index');
Route::get('/cadastros-gestao-rotinas', [ProcessoController::class, 'gestaoRotinas'])->name('processos.gestao-rotinas');

Route::middleware(['auth'])->group(function () {
    Route::get('/mudar-senha', [ForgotPasswordController::class, 'edit'])->name('password.edit.custom');
    Route::post('/mudar-senha', [ForgotPasswordController::class, 'update'])->name('password.update.custom');
    
    Route::get('/usuarios', [AuthController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios', [AuthController::class, 'store'])->name('users.store');
    Route::put('/usuarios/{id}', [AuthController::class, 'update'])->name('users.update');

    Route::get('/home', function () {
        return view('home');
    })->name('home');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login');
})->name('logout');

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);

Route::get('/dashboard', function () {
    return "Bem-vindo ao painel!";
})->middleware('auth');

Route::get('/', function () {
    return view('welcome');
});