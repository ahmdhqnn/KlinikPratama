<?php

namespace App\Http\Middleware;

use App\ClinicalAuditRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceClinicalSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $session = $request->session();
            $lastActivity = (int) $session->get('clinical_last_activity', now()->timestamp);
            $version = (int) $session->get('clinical_session_version', $user->session_version ?? 0);
            if (Auth::viaRemember() || ! $user->linkedProfileAllowsAccess() || $version !== (int) $user->session_version || now()->timestamp - $lastActivity >= config('clinical_security.idle_minutes') * 60) {
                app(ClinicalAuditRecorder::class)->record($request, 'auth.session_expired', metadata: ['outcome' => 'denied']);
                Auth::logout();
                $session->invalidate();
                $session->regenerateToken();

                return $request->expectsJson()
                    ? response()->json(['message' => 'Sesi berakhir. Silakan masuk kembali.'], 401)->header('Cache-Control', 'private, no-store')
                    : redirect()->route('login')->with('error', 'Sesi berakhir. Silakan masuk kembali.');
            }
            $session->put('clinical_last_activity', $lastActivity);
            $session->put('clinical_session_version', $version);
            if ($user->must_change_password && ! $request->routeIs('profile.show', 'profile.password', 'logout', 'session.activity', 'session.status')) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Ubah kata sandi sementara melalui Profil saya.'], 403)
                    : redirect()->route('profile.show')->with('error', 'Ubah kata sandi sementara sebelum melanjutkan pelayanan.');
            }
        }

        return $next($request);
    }
}
