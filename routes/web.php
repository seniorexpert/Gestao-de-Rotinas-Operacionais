<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ProcessoController;
use App\Http\Controllers\MonitoramentoController;
use App\Http\Controllers\LocaisController;
use App\Http\Controllers\FerramentasController;
use App\Http\Controllers\TarefasController;
use App\Http\Controllers\RotinasController;
use App\Http\Controllers\RotinasEmergenciaisController;
use App\Http\Controllers\RelatorioController;

use App\Http\Controllers\EstatisticasController;
use App\Http\Controllers\ExecucoesController;
use App\Http\Controllers\RotinaAcompanhamentoController;
use App\Http\Controllers\UsuarioController;

Route::middleware(['auth'])->group(function () {
    Route::get('/mudar-senha', [ForgotPasswordController::class, 'edit'])->name('password.edit.custom');
    Route::post('/mudar-senha', [ForgotPasswordController::class, 'update'])->name('password.update.custom');
    
    Route::get('/home', [HomeController::class, 'index'])->name('home');

    Route::prefix('relatorios')->name('relatorios.')->group(function () {
        Route::get('/rotinas', [RelatorioController::class, 'rotinas'])->name('rotinas');
        Route::get('/rotinas-excel', [RelatorioController::class, 'rotinasExcel'])->name('excel');
        Route::get('/rotinas-exportar-csv', [RelatorioController::class, 'exportarExcel'])->name('excel.exportar');
        Route::get('/tarefas', [RelatorioController::class, 'tarefas'])->name('tarefas');
        Route::get('/ferramentas', [RelatorioController::class, 'ferramentas'])->name('ferramentas');
        Route::get('/locais', [RelatorioController::class, 'locais'])->name('locais');
        Route::get('/rotinas-finalizadas', [RelatorioController::class, 'rotinasFinalizadas'])->name('rotinas-finalizadas');
        Route::get('/logs-rotinas', [RelatorioController::class, 'logsRotinas'])->name('logs-rotinas');
        Route::get('/estatisticas', [EstatisticasController::class, 'index'])->name('estatisticas');
        Route::get('/relatorios/api-log-detalhes/{id}', [RelatorioController::class, 'apiLogDetalhes'])->whereNumber('id');
        Route::get('/logs-rotinas-excel', [RelatorioController::class, 'exportarExcelLogs'])->name('logs.excel.exportar');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/iniciar-processos', [ProcessoController::class, 'index'])->name('processos.index');
    Route::match(['get', 'post'], '/cadastros-gestao-rotinas', [ProcessoController::class, 'gestaoRotinas'])->name('processos.gestao-rotinas');
    Route::get('/api/usuarios-por-unidade', [ProcessoController::class, 'usuariosPorUnidade'])->name('processos.usuarios-por-unidade');
    Route::get('/centro-monitoramento', [MonitoramentoController::class, 'index'])->name('monitoramento.index');
    Route::post('/rotinas/atribuir', [MonitoramentoController::class, 'atribuir'])->name('rotinas.atribuir');
    Route::post('/rotinas/salvar', [RotinasController::class, 'store'])->name('rotinas.store');
    Route::post('/rotinas-emergenciais', [RotinasEmergenciaisController::class, 'store'])->name('rotinas.emergenciais.store');
    Route::post('/ferramentas', [FerramentasController::class, 'store'])->name('ferramentas.store');
    Route::post('/tarefas', [TarefasController::class, 'store'])->name('tarefas.store');
    Route::post('/locais', [LocaisController::class, 'store'])->name('locais.store');
});

Route::middleware(['auth', 'can:gerenciar-usuarios'])->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->name('users.store');
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update'])->whereNumber('id')->name('users.update');
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

Route::middleware('auth')->group(function () {
    Route::post('/execucoes/{id}/iniciar', [ExecucoesController::class, 'iniciar'])
        ->whereNumber('id')
        ->name('execucoes.iniciar');

    Route::post('/execucoes/{id}/concluir', [ExecucoesController::class, 'concluir'])
        ->whereNumber('id')
        ->name('execucoes.concluir');
});

Route::middleware(['auth', 'can:acompanhar-rotinas'])
    ->prefix('rotina-acompanhamento')
    ->name('acompanhamento.')
    ->group(function () {
        Route::get('/', [RotinaAcompanhamentoController::class, 'index'])->name('index');
        Route::post('/{id}/verificar', [RotinaAcompanhamentoController::class, 'store'])
            ->whereNumber('id')
            ->name('store');
    });
