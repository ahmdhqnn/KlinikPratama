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

        if ($request->user()->role === 'perawat'
            && $request->isMethod('GET')
            && $request->is('pendaftaran/database-pasien', 'pendaftaran/kunjungan-per-poli', 'pendaftaran/laporan-top-diagnosa', 'pendaftaran/jadwal-praktik')) {
            return $next($request);
        }

        if ($request->user()->hasRole('dokter') && $request->routeIs('pendaftaran.jadwal-praktik')) {
            return $next($request);
        }

        if (! in_array($request->user()->role, ['admin', 'pendaftaran'], true)) {
            abort(403, 'Akun Anda tidak memiliki akses ke layanan ini.');
        }

        return $next($request);
    }
}
