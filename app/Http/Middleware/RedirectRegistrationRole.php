<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectRegistrationRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === 'pendaftaran' && $request->routeIs('pelayanan.pasien.rekam-medis')) {
            abort(403, 'Unauthorized access.');
        }

        if ($request->user()?->role === 'pendaftaran'
            && ! $request->is('pendaftaran', 'pendaftaran/*')) {
            return redirect()->route('pendaftaran.dashboard');
        }

        if ($request->user()?->role === 'perawat' && ! $this->isAllowedForNurse($request)) {
            if ($request->routeIs('dashboard')) {
                return redirect()->route('perawat.dashboard');
            }

            abort(403, 'Unauthorized access.');
        }

        if ($request->user()?->role === 'dokter' && $request->routeIs('dashboard')) {
            return redirect()->route('dokter.dashboard');
        }

        if ($request->user()?->role === 'dokter' && ! $this->isAllowedForDoctor($request)) {
            abort(403, 'Unauthorized access.');
        }

        if ($request->user()?->role === 'farmasi' && ! $this->isAllowedForPharmacy($request)) {
            if ($request->routeIs('dashboard')) {
                return redirect()->route('farmasi.dashboard');
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

    private function isAllowedForDoctor(Request $request): bool
    {
        return $request->routeIs(
            'dokter.*',
            'persuratan.*',
            'pelayanan.pemeriksaan.*',
            'pelayanan.icd10.search',
            'pelayanan.pasien.index',
            'pelayanan.pasien.show',
            'pelayanan.pasien.rekam-medis',
            'logout',
        );
    }

    private function isAllowedForPharmacy(Request $request): bool
    {
        return $request->routeIs('farmasi.dashboard', 'pelayanan.farmasi.*', 'stok.persediaan.*', 'stok.batch.*', 'stok.mutasi.*', 'stok.purchase-order.*', 'logout');
    }
}
