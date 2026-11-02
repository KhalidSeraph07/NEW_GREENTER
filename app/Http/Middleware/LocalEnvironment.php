<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LocalEnvironment
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app()->environment(['local', 'testing']), 503, 'Solo ensayo local ficticio.');
        abort_unless(in_array($request->getHost(), ['127.0.0.1', 'localhost'], true), 400, 'Host local no permitido.');
        if (is_file(storage_path('framework/h72-state.json'))) {
            abort_unless($request->isMethodSafe() || in_array($request->path(), ['login', 'logout'], true),
                423, 'Restauración H7-2 solo consulta; transporte y escrituras bloqueados.');
        }
        return self::secure($next($request));
    }
    public static function secure(Response $response): Response
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        return $response;
    }
}
