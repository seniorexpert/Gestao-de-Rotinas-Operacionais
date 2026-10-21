<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\ForgotPasswordController;

use App\Http\Controllers\UsuarioController;

Route::middleware(['auth'])->group(function () {
    Route::get('/mudar-senha', [ForgotPasswordController::class, 'edit'])->name('password.edit.custom');

    Route::post('/mudar-senha', [ForgotPasswordController::class, 'update'])->name('password.update.custom');
});

Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect('/login');
})->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/home', function () {
        return view('home');
    })->name('home');
});
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');

Route::post('/login', [AuthController::class, 'login']);

Route::get('/dashboard', function () {
    return "Bem-vindo ao painel!";
})->middleware('auth');

Route::get('/', function () {
    return view('welcome');
});


Route::post('/usuarios', [UsuarioController::class, 'store'])
    ->middleware('auth')
    ->name('usuarios.store');