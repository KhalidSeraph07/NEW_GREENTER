<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

final class LocalOperator
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app()->environment(['local', 'testing']), 503, 'H2 solo funciona en ensayo local.');
        abort_unless(in_array($request->getHost(), ['127.0.0.1', 'localhost'], true), 400, 'Host local no permitido.');
        $key = 'h2-basic:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            abort(429, 'Demasiados intentos de acceso. Espera un minuto.');
        }
        try {
            Auth::onceBasic();
        } catch (UnauthorizedHttpException $exception) {
            RateLimiter::hit($key, 60);
            throw $exception;
        }
        RateLimiter::clear($key);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; style-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
        return $response;
    }
}
