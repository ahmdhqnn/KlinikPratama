<?php

namespace App\Http\Middleware;

use App\PracticeScheduleAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRegistrationRole
{
    public function __construct(private PracticeScheduleAccess $schedules) {}

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

        if ($request->user()->role === 'dokter' && $request->isMethod('GET') && $request->routeIs('pendaftaran.jadwal-praktik')) {
            return $next($request);
        }

        if ($request->user()->role === 'perawat'
            && $request->routeIs('pendaftaran.store-jadwal-praktik', 'pendaftaran.update-jadwal-praktik', 'pendaftaran.destroy-jadwal-praktik', 'pendaftaran.jadwal-praktik.asn', 'pendaftaran.jadwal-praktik.pengecualian.*')
            && $this->schedules->canManage($request->user())) {
            return $next($request);
        }

        if (! in_array($request->user()->role, ['admin', 'pendaftaran'], true)) {
            abort(403, 'Unauthorized access.');
        }

        return $next($request);
    }
}
