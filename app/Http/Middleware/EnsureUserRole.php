<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if ($user && in_array($user->role instanceof \BackedEnum ? $user->role->value : $user->role, $roles, true)) {
            return $next($request);
        }

        // API/XHR (fetch com Accept: json) → 403 cru, pra o front tratar.
        // Navegação de tela (Inertia/browser) → manda pro /403 limpo, sem página quebrada.
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Você não tem permissão para esta ação.'], 403);
        }

        return redirect('/403');
    }
}
