<?php

namespace App\Providers;

use App\Models\User;
use App\Services\PermissoesAcesso;
use Illuminate\Database\Connectors\SqlServerConnector;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(PermissoesAcesso::class);

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

    public function boot(): void
    {
        Gate::before(fn (User $usuario): ?bool => ! $usuario->status ? false : ($usuario->isAdmin() ? true : null));
        Gate::define('administrar-permissoes', fn (User $usuario): bool => $usuario->isAdmin());
        Gate::define('auditoria-geral', fn (User $usuario): bool => app(PermissoesAcesso::class)->gerenciaGeral($usuario));
        Gate::define('visualizar-modulo', fn (User $usuario, string $modulo): bool => app(PermissoesAcesso::class)->nivel($usuario, $modulo) >= PermissoesAcesso::VISUALIZAR);
        Gate::define('editar-modulo', fn (User $usuario, string $modulo): bool => app(PermissoesAcesso::class)->nivel($usuario, $modulo) >= PermissoesAcesso::EDITAR);
        Gate::define('consultar-colaboradores', fn (User $usuario): bool => $usuario->can('editar-modulo', 'rotinas')
            || $usuario->can('editar-modulo', 'monitoramento'));
    }
}
