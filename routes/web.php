<?php

use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EstatisticasController;
use App\Http\Controllers\ExecucoesController;
use App\Http\Controllers\FerramentasController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaisController;
use App\Http\Controllers\MonitoramentoController;
use App\Http\Controllers\PermissaoAcessoController;
use App\Http\Controllers\ProcessoController;
use App\Http\Controllers\RelatorioController;
use App\Http\Controllers\RotinaAcompanhamentoController;
use App\Http\Controllers\RotinasController;
use App\Http\Controllers\RotinasEmergenciaisController;
use App\Http\Controllers\RotinasFinalizadasController;
use App\Http\Controllers\TarefasController;
use App\Http\Controllers\UsuarioController;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('/mudar-senha', [ForgotPasswordController::class, 'edit'])->name('password.edit.custom');
    Route::post('/mudar-senha', [ForgotPasswordController::class, 'update'])->name('password.update.custom');

    Route::get('/home', [HomeController::class, 'index'])->can('visualizar-modulo', "'rotinas_diarias'")->name('home');
    Route::get('/dashboard', [HomeController::class, 'entrada'])->name('dashboard');

    Route::prefix('relatorios')->name('relatorios.')->group(function () {
        Route::get('/rotinas', [RelatorioController::class, 'rotinas'])->can('visualizar-modulo', "'rotinas'")->name('rotinas');
        Route::get('/rotinas-excel', [RelatorioController::class, 'rotinasExcel'])->can('visualizar-modulo', "'rotinas_excel'")->name('excel');
        Route::get('/rotinas-exportar-csv', [RelatorioController::class, 'exportarExcel'])->can('visualizar-modulo', "'rotinas_excel'")->name('excel.exportar');
        Route::get('/tarefas', [RelatorioController::class, 'tarefas'])->can('visualizar-modulo', "'tarefas'")->name('tarefas');
        Route::get('/ferramentas', [RelatorioController::class, 'ferramentas'])->can('visualizar-modulo', "'ferramentas'")->name('ferramentas');
        Route::get('/locais', [RelatorioController::class, 'locais'])->can('visualizar-modulo', "'locais'")->name('locais');
        Route::middleware("can:visualizar-modulo,'rotinas_finalizadas'")->prefix('rotinas-finalizadas')->group(function () {
            Route::get('/', [RotinasFinalizadasController::class, 'index'])->name('rotinas-finalizadas');
            Route::get('/exportar', [RotinasFinalizadasController::class, 'exportar'])->name('rotinas-finalizadas.exportar');
            Route::get('/{id}', [RotinasFinalizadasController::class, 'detalhes'])->whereNumber('id')->name('rotinas-finalizadas.detalhes');
            Route::get('/{id}/foto', [RotinasFinalizadasController::class, 'foto'])->whereNumber('id')->name('rotinas-finalizadas.foto');
        });
        Route::get('/estatisticas', [EstatisticasController::class, 'index'])->can('visualizar-modulo', "'estatisticas'")->name('estatisticas');
        Route::middleware("can:visualizar-modulo,'auditoria'")->group(function () {
            Route::get('/logs-rotinas', [AuditoriaController::class, 'index'])->name('logs-rotinas');
            Route::get('/logs-rotinas/{id}', [AuditoriaController::class, 'detalhes'])->whereNumber('id')->name('logs.detalhes');
            Route::get('/relatorios/api-log-detalhes/{id}', [AuditoriaController::class, 'apiDetalhes'])->whereNumber('id')->name('logs.detalhes.legado');
            Route::get('/logs-rotinas-excel', [AuditoriaController::class, 'exportar'])->name('logs.excel.exportar');
        });
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/execucoes/{id}/realizando', [ExecucoesController::class, 'realizando'])->whereNumber('id')
        ->can('visualizar-modulo', "'rotinas_diarias'")->name('execucoes.realizando');
    Route::get('/execucoes/{id}/foto', [ExecucoesController::class, 'foto'])->whereNumber('id')
        ->can('visualizar-modulo', "'rotinas_diarias'")->name('execucoes.foto');
    Route::get('/execucoes/{id}/{acao}', [ExecucoesController::class, 'formulario'])
        ->whereNumber('id')->whereIn('acao', ['finalizar', 'nao-realizada', 'auxilio'])
        ->can('editar-modulo', "'rotinas_diarias'")->name('execucoes.formulario');

    Route::get('/iniciar-processos', [ProcessoController::class, 'index'])->can('visualizar-modulo', "'processos'")->name('processos.index');
    Route::match(['get', 'post'], '/cadastros-gestao-rotinas', [ProcessoController::class, 'gestaoRotinas'])
        ->can('visualizar-modulo', "'processos'")->name('processos.gestao-rotinas');
    Route::get('/api/usuarios-por-unidade', [ProcessoController::class, 'usuariosPorUnidade'])->can('consultar-colaboradores')->name('processos.usuarios-por-unidade');
    Route::get('/centro-monitoramento', [MonitoramentoController::class, 'index'])->can('visualizar-modulo', "'monitoramento'")->name('monitoramento.index');
    Route::post('/rotinas/atribuir', [MonitoramentoController::class, 'atribuir'])->can('editar-modulo', "'monitoramento'")->name('rotinas.atribuir');
    Route::post('/rotinas/salvar', [RotinasController::class, 'store'])->can('editar-modulo', "'rotinas'")->name('rotinas.store');
    Route::put('/rotinas/{grupo}', [RotinasController::class, 'update'])->can('editar-modulo', "'rotinas'")->name('rotinas.update');
    Route::post('/rotinas/{grupo}/tarefas', [RotinasController::class, 'adicionarTarefa'])
        ->can('editar-modulo', "'rotinas'")->name('rotinas.tarefas.store');
    Route::put('/rotinas/{grupo}/tarefas/{tarefa}', [RotinasController::class, 'atualizarTarefa'])->whereNumber('tarefa')
        ->can('editar-modulo', "'rotinas'")->name('rotinas.tarefas.update');
    Route::delete('/rotinas/{grupo}', [RotinasController::class, 'excluir'])->can('editar-modulo', "'rotinas'")->name('rotinas.destroy');
    Route::delete('/rotinas/{grupo}/tarefas/{tarefa}', [RotinasController::class, 'excluir'])->whereNumber('tarefa')
        ->can('editar-modulo', "'rotinas'")->name('rotinas.tarefas.destroy');
    Route::post('/rotinas-emergenciais', [RotinasEmergenciaisController::class, 'store'])
        ->can('editar-modulo', "'processos'")->can('editar-modulo', "'rotinas'")->name('rotinas.emergenciais.store');
    Route::post('/ferramentas', [FerramentasController::class, 'store'])->can('editar-modulo', "'ferramentas'")->name('ferramentas.store');
    Route::post('/tarefas', [TarefasController::class, 'store'])->can('editar-modulo', "'tarefas'")->name('tarefas.store');
    Route::post('/locais', [LocaisController::class, 'store'])->can('editar-modulo', "'locais'")->name('locais.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/usuarios', [UsuarioController::class, 'index'])->can('visualizar-modulo', "'usuarios'")->name('usuarios.index');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->can('editar-modulo', "'usuarios'")->name('users.store');
    Route::put('/usuarios/{id}', [UsuarioController::class, 'update'])->whereNumber('id')->can('editar-modulo', "'usuarios'")->name('users.update');
});

Route::middleware(['auth', 'can:administrar-permissoes'])->prefix('permissoes-acesso')->name('permissoes.')->group(function () {
    Route::get('/', [PermissaoAcessoController::class, 'index'])->name('index');
    Route::put('/{tipo}/{id}', [PermissaoAcessoController::class, 'update'])->whereIn('tipo', ['grupo', 'usuario'])->whereNumber('id')->name('update');
});

Route::post('/logout', function (Request $request) {
    $request->attributes->set(AuditoriaService::AUTOR_HTTP, $request->user());
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect('/login');
})->name('logout');

Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.autenticar');

Route::get('/', function () {
    return view('welcome');
})->name('inicio');

Route::middleware(['auth', "can:editar-modulo,'rotinas_diarias'"])->group(function () {
    Route::post('/execucoes/{id}/iniciar', [ExecucoesController::class, 'iniciar'])
        ->whereNumber('id')
        ->name('execucoes.iniciar');

    Route::post('/execucoes/{id}/concluir', [ExecucoesController::class, 'concluir'])
        ->whereNumber('id')
        ->name('execucoes.concluir');

    Route::post('/execucoes/{id}/nao-realizada', [ExecucoesController::class, 'naoRealizada'])->whereNumber('id')->name('execucoes.nao-realizada');
    Route::post('/execucoes/{id}/auxilio', [ExecucoesController::class, 'auxilio'])->whereNumber('id')->name('execucoes.auxilio');
});

Route::middleware('auth')
    ->prefix('rotina-acompanhamento')
    ->name('acompanhamento.')
    ->group(function () {
        Route::get('/', [RotinaAcompanhamentoController::class, 'index'])->can('visualizar-modulo', "'acompanhamento'")->name('index');
        Route::post('/{id}/verificar', [RotinaAcompanhamentoController::class, 'store'])
            ->whereNumber('id')
            ->can('editar-modulo', "'acompanhamento'")
            ->name('store');
    });
