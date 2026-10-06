<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectRegistrationRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === 'pendaftaran'
            && ! $request->is('pendaftaran', 'pendaftaran/*', 'pelayanan/screening', 'pelayanan/screening/*')) {
            return redirect()->route('pendaftaran.dashboard');
        }

        if ($request->user()?->role === 'perawat' && ! $this->isAllowedForNurse($request)) {
            if ($request->routeIs('dashboard')) {
                return redirect()->route('perawat.dashboard');
            }

            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }

    private function isAllowedForNurse(Request $request): bool
    {
        return $request->routeIs(
            'perawat.dashboard',
            'pelayanan.screening.*',
            'pelayanan.kunjungan.show',
            'pelayanan.kunjungan.screening.store',
            'pelayanan.pasien.index',
            'pelayanan.pasien.show',
            'pelayanan.pasien.rekam-medis',
            'pendaftaran.database-pasien',
            'pendaftaran.kunjungan-per-poli',
            'pendaftaran.laporan-top-diagnosa',
            'pendaftaran.jadwal-praktik',
            'logout',
        );
    }
}
