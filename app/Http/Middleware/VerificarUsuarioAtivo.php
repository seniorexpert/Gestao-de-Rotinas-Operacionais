<?php

namespace App\Http\Middleware;

use App\Services\AuditoriaService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerificarUsuarioAtivo
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();
        if ($usuario === null || $usuario->status || $request->routeIs('logout')) {
            return $next($request);
        }

        $request->attributes->set(AuditoriaService::AUTOR_HTTP, $usuario);
        $request->attributes->set(AuditoriaService::RESULTADO_HTTP, 'recusado');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $mensagem = 'Sua conta está inativa.';

        return $request->expectsJson() || $request->is('api/*')
            ? response()->json(['message' => $mensagem], 403)
            : to_route('login')->withErrors(['conta' => $mensagem]);
    }
}
