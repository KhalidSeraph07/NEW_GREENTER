<?php
namespace App\Http\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LocalOperator
{
    public function handle(Request $request, Closure $next, ?string $role = null)
    {
        // Leer el estado actual incluso con una sesión anterior a la desactivación.
        $user = $request->user()?->fresh();
        if (!$user || !$user->is_active || !in_array($user->role, User::ROLES, true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($request->expectsJson()) { abort(401, 'Inicia sesión con una cuenta activa.'); }
            return redirect()->route('login');
        }
        Auth::setUser($user);
        abort_if($role !== null && $user->role !== $role, 403, 'Operación reservada a ADMIN.');
        return $next($request);
    }
}
