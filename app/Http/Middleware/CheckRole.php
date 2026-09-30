<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Middleware RBAC directo por perfil.
     * Uso en rutas: middleware('role:Administrador,Bodeguero')
     *
     * @param string ...$roles Nombres de rol permitidos (separados por coma en la ruta)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $rolUsuario = auth()->user()->rol->nombre_rol;

        if (!in_array($rolUsuario, $roles)) {
            abort(403, 'Acceso denegado. Su rol (' . $rolUsuario . ') no tiene permisos para esta sección.');
        }

        return $next($request);
    }
}
