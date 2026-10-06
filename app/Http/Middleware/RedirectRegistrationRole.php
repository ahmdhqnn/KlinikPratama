<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectRegistrationRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === 'pendaftaran' && ! $request->is('pendaftaran', 'pendaftaran/*')) {
            return redirect()->route('pendaftaran.dashboard');
        }

        return $next($request);
    }
}
