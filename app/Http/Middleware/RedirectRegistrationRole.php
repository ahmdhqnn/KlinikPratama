<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectRegistrationRole
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === 'pendaftaran' && $request->routeIs('pelayanan.kunjungan.show')) {
            return redirect()->route('pendaftaran.kunjungan.show', $request->route('kunjungan'));
        }

        if ($request->user()?->role === 'pendaftaran' && ($request->routeIs('pelayanan.pasien.rekam-medis') || $request->routeIs('pelayanan.screening.print'))) {
            abort(403, 'Unauthorized access.');
        }

        if ($request->user()?->role === 'pendaftaran' && $request->routeIs('pelayanan.pasien.index')) {
            return redirect()->route('pendaftaran.database-pasien');
        }
        if ($request->user()?->role === 'pendaftaran' && $request->routeIs('pelayanan.pasien.destroy', 'pelayanan.pasien.gabung')) {
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
            'perawat.laporan-pengkajian',
            'perawat.laporan-pengkajian.export',
            'perawat.laporan-tindakan*',
            'pelayanan.screening.*',
            'pelayanan.tindakan.*',
            'pelayanan.kunjungan.show',
            'pelayanan.kunjungan.index',
            'pelayanan.kunjungan.screening.store',
            'pelayanan.kunjungan.attachments.*',
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
            'pendaftaran.jadwal-praktik',
            'persuratan.*',
            'pelayanan.pemeriksaan.*',
            'pelayanan.icd10.search',
            'pelayanan.icd9cm.search',
            'pelayanan.kunjungan.attachments.show',
            'pelayanan.pasien.index',
            'pelayanan.pasien.show',
            'pelayanan.pasien.rekam-medis',
            'logout',
        );
    }

    private function isAllowedForPharmacy(Request $request): bool
    {
        return $request->routeIs('farmasi.*', 'pelayanan.farmasi.*', 'pelayanan.pasien.index', 'pelayanan.pasien.show', 'stok.persediaan.*', 'stok.batch.*', 'stok.mutasi.*', 'logout');
    }
}
