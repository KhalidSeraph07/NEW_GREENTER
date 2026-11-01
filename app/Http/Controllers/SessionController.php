<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\Validation\ValidationException;

final class SessionController
{
    public function create() { return view('auth.login'); }
    public function store(Request $request)
    {
        $ipKey = 'h6-login-ip:'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($ipKey, 10), 429, 'Demasiados intentos. Espera un minuto.');
        $v = $request->validate(['email'=>'required|string|max:254', 'password'=>'required|string|max:256']);
        $v['email'] = strtolower(trim($v['email']));
        $key = 'h6-login:'.hash('sha256', $request->ip().'|'.$v['email']);
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Demasiados intentos. Espera un minuto.');
        if (!Auth::attempt(['password'=>$v['password'], 'is_active'=>true,
            fn ($q) => $q->whereIn('role', \App\Models\User::ROLES)->whereRaw('lower(email) = ?', [$v['email']])], false)) {
            RateLimiter::hit($key, 60); RateLimiter::hit($ipKey, 60);
            throw ValidationException::withMessages(['email'=>'Credenciales inválidas o cuenta no disponible.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        return redirect()->route($request->user()->role === 'RECEPTOR' ? 'recipient.index' : 'invoices.index');
    }
    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
