<?php

use App\Http\Middleware\VerificarUsuarioAtivo;
use App\Services\AuditoriaService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [VerificarUsuarioAtivo::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request): Response {
            $resultado = match (true) {
                $exception instanceof ValidationException => 'invalido',
                $exception instanceof AuthenticationException => 'recusado',
                default => null,
            };

            if ($resultado !== null) {
                $request->attributes->set(AuditoriaService::RESULTADO_HTTP, $resultado);
            }

            return $response;
        });
    })->create();
