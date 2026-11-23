<?php

namespace App\Http\Controllers\Auth;

use App\ClinicalAuditRecorder;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function showLoginForm(Request $request): Response
    {
        $email = $request->old('email');

        return Inertia::render('auth/login', ['email' => is_string($email) ? $email : '']);
    }

    public function assistance(): Response
    {
        return Inertia::render('auth/bantuan-akses');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Masukkan email akun Anda.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'password.required' => 'Masukkan kata sandi akun Anda.',
            'password.string' => 'Masukkan kata sandi yang valid.',
        ]);

        if (Auth::attempt([...$credentials, 'is_active' => true], false)) {
            $request->session()->regenerate();
            $request->session()->put('clinical_last_activity', now()->timestamp);
            $request->session()->put('clinical_session_version', (int) $request->user()->session_version);
            app(ClinicalAuditRecorder::class)->record($request, 'auth.login');
            if ($request->user()->must_change_password) {
                return redirect()->route('profile.show');
            }

            $destination = match ($request->user()->role) {
                'pendaftaran' => route('pendaftaran.dashboard'),
                'perawat' => route('perawat.dashboard'),
                'dokter' => route('dokter.dashboard'),
                'farmasi' => route('farmasi.dashboard'),
                default => route('dashboard'),
            };

            return redirect()->intended($destination);
        }

        app(ClinicalAuditRecorder::class)->record($request, 'auth.failed_login', metadata: ['outcome' => 'denied']);

        return redirect()->route('login')->withErrors([
            'credentials' => 'Email atau kata sandi tidak cocok. Periksa kembali isian atau buka bantuan akses.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        if ($request->user()) {
            app(ClinicalAuditRecorder::class)->record($request, 'auth.logout');
        }
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
