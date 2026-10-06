<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (! in_array($request->user()->role, ['admin', 'pendaftaran'], true)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}
