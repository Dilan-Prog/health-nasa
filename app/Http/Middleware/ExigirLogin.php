<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Exige sesión solo cuando `mision.exigir_login` está activo (si no, la demo funciona sin login). */
class ExigirLogin
{
    public function handle(Request $request, Closure $next)
    {
        if (config('mision.exigir_login', false) && ! $request->user()) {
            return redirect()->guest(route('login'));
        }

        return $next($request);
    }
}
