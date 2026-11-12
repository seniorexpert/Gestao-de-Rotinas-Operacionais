<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ProcessoController;
use App\Http\Controllers\MonitoramentoController;
use App\Http\Controllers\LocaisController;
use App\Http\Controllers\FerramentasController;
use App\Http\Controllers\TarefasController;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\RotinasController;
use App\Http\Controllers\RotinasEmergenciaisController;
use App\Http\Controllers\RelatorioController;

Route::middleware(['auth'])->group(function () {
    Route::get('/mudar-senha', [ForgotPasswordController::class, 'edit'])->name('password.edit.custom');
    Route::post('/mudar-senha', [ForgotPasswordController::class, 'update'])->name('password.update.custom');
    
    Route::get('/usuarios', [AuthController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios', [AuthController::class, 'store'])->name('users.store');
    Route::put('/usuarios/{id}', [AuthController::class, 'update'])->name('users.update');

    Route::get('/home', function () {
        $rotinas = DB::table('gr_rotinas')->orderBy('horario', 'asc')->get();
        return view('home', compact('rotinas'));
    })->name('home');

    Route::prefix('relatorios')->name('relatorios.')->group(function () {
        Route::get('/rotinas', [RelatorioController::class, 'rotinas'])->name('rotinas');
        Route::get('/rotinas-excel', [RelatorioController::class, 'rotinasExcel'])->name('excel');
        Route::get('/rotinas-exportar-csv', [RelatorioController::class, 'exportarExcel'])->name('excel.exportar');
        Route::get('/tarefas', [RelatorioController::class, 'tarefas'])->name('tarefas');
        Route::get('/ferramentas', [RelatorioController::class, 'ferramentas'])->name('ferramentas');
        Route::get('/locais', [RelatorioController::class, 'locais'])->name('locais');
        Route::get('/rotinas-finalizadas', [RelatorioController::class, 'rotinasFinalizadas'])->name('rotinas-finalizadas');
        Route::get('/logs-rotinas', [RelatorioController::class, 'logsRotinas'])->name('logs-rotinas');
        Route::get('/estatisticas', [RelatorioController::class, 'estatisticas'])->name('relatorios.estatisticas');
        Route::get('/relatorios/api-log-detalhes/{id}', [RelatorioController::class, 'apiLogDetalhes']);
        Route::get('/logs-rotinas-excel', [RelatorioController::class, 'exportarExcelLogs'])->name('logs.excel.exportar');
    });
});

Route::post('/rotinas/atribuir', [MonitoramentoController::class, 'atribuir'])->name('rotinas.atribuir');

Route::get('/centro-monitoramento', [MonitoramentoController::class, 'centroMonitoramento']);

Route::get('/api/usuarios-por-unidade', function (Request $request) {
    $unitName = $request->query('unit');
    $usuarios = DB::table('gr_usuarios')->where('unit', $unitName)->orderBy('name')->get();
    return response()->json($usuarios);
});

Route::post('/rotinas/salvar', [RotinasController::class, 'store'])->name('rotinas.store');
Route::post('/rotinas-emergenciais', [RotinasEmergenciaisController::class, 'store'])->name('rotinas.emergenciais.store');
Route::post('/ferramentas', [FerramentasController::class, 'store'])->name('ferramentas.store');
Route::post('/tarefas', [TarefasController::class, 'store'])->name('tarefas.store');
Route::post('/locais', [LocaisController::class, 'store'])->name('locais.store');

Route::get('/iniciar-processos', [ProcessoController::class, 'index'])->name('processos.index');
Route::get('/centro-monitoramento', [MonitoramentoController::class, 'index'])->name('monitoramento.index');
Route::match(['get', 'post'], '/cadastros-gestao-rotinas', [ProcessoController::class, 'gestaoRotinas'])->name('processos.gestao-rotinas');

Route::middleware(['auth'])->group(function () {
    Route::get('/mudar-senha', [ForgotPasswordController::class, 'edit'])->name('password.edit.custom');
    Route::post('/mudar-senha', [ForgotPasswordController::class, 'update'])->name('password.update.custom');
    
    Route::get('/usuarios', [AuthController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios', [AuthController::class, 'store'])->name('users.store');
    Route::put('/usuarios/{id}', [AuthController::class, 'update'])->name('users.update');

    Route::get('/home', function () {
        $rotinas = DB::table('gr_rotinas')->orderBy('horario', 'asc')->get();
        return view('home', compact('rotinas'));
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