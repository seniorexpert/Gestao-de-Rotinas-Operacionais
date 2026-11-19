<?php

namespace App\Providers;

use App\Models\User;
use App\Services\EscalonamentoAcompanhamento;
use Illuminate\Database\Connectors\SqlServerConnector;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('db.connector.sqlsrv', function () {
            return new class extends SqlServerConnector
            {
                public function connect(array $config)
                {
                    $pdo = parent::connect($config);

                    $pdo->exec('SET DATEFORMAT ymd');

                    return $pdo;
                }
            };
        });
    }

    public function boot(EscalonamentoAcompanhamento $escalonamento): void
    {
        Gate::define('acompanhar-rotinas', fn (User $usuario): bool => $escalonamento->caixaDoUsuario($usuario) !== null);

        Gate::define('gerenciar-usuarios', function (User $usuario): bool {
            $grupo = $usuario->grupoAcesso;

            return $usuario->status && $grupo?->status
                && strcasecmp(trim($grupo->grupo_acesso), EscalonamentoAcompanhamento::GERENTE_GERAL) === 0;
        });
    }
}
