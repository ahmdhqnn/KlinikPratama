<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(
            $request->user()?->is_active && in_array($request->user()->role, $roles, true),
            403,
            'Akun Anda tidak memiliki akses ke layanan ini.',
        );

        return $next($request);
    }
}
