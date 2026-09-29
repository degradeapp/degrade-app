<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Cabeçalhos de segurança HTTP. Os 5 primeiros são sempre seguros e não
     * quebram nada. O CSP só é aplicado em produção — em dev o Vite injeta
     * scripts/HMR de localhost:5173 que um CSP estrito bloquearia.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // HSTS: depois da 1ª visita o navegador só fala HTTPS com o domínio (barra
        // downgrade/SSL-strip em Wi-Fi público). Só em resposta HTTPS, senão não vale.
        if ($request->isSecure() && app()->environment(['production', 'staging'])) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // Staging espelha a produção: um CSP que quebre alguma tela aparece lá primeiro.
        if (app()->environment(['production', 'staging'])) {
            // O build de produção serve JS/CSS externos do próprio domínio (sem
            // scripts inline — ver app.blade.php). Vue pode injetar estilos inline.
            $response->headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: https:",
                "font-src 'self' data:",
                "connect-src 'self'",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "form-action 'self'",
                "object-src 'none'",
            ]));
        }

        return $response;
    }
}
