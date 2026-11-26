<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\Rules\AvailableAccountEmail;
use App\Rules\ClinicalSigningCredential;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user()->load('nakes');

        return Inertia::render('profile/show', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'address' => $user->address ?? '',
                'role' => $user->role,
                'roleLabel' => $user->role_label,
                'active' => $user->is_active,
                'joinedAt' => $user->created_at?->format('d/m/Y'),
                'photoUrl' => $user->photo_path ? route('profile.photo', ['v' => basename($user->photo_path)]) : null,
                'signatureUrl' => $user->signature_path ? route('profile.signature') : null,
                'supportsSigningPin' => $user->supportsSigningPin(),
                'hasSigningPin' => $user->hasSigningPin(),
                'mustSetupSigningPin' => (bool) $user->must_setup_signing_pin,
                'signingPinSetAt' => $user->signing_pin_set_at?->format('d/m/Y H:i'),
                'professional' => $user->nakes ? [
                    'name' => $user->nakes->nama,
                    'code' => $user->nakes->kode,
                    'position' => $user->nakes->jabatan,
                    'category' => $user->nakes->kategori,
                    'gender' => $user->nakes->jenis_kelamin,
                    'sip' => $user->nakes->no_sip,
                    'str' => $user->nakes->no_str,
                    'phone' => $user->nakes->telepon,
                ] : null,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255', new AvailableAccountEmail($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

        $data['email'] = strtolower($data['email']);
        if ($user->email !== $data['email']) {
            $user->email_verified_at = null;
        }

        $user->fill($data)->save();

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);

        if (Hash::check($data['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'Gunakan kata sandi baru yang berbeda dari kata sandi sementara atau sebelumnya.']);
        }
        if ($request->user()->hasSigningPin() && Hash::check($data['password'], $request->user()->signing_pin_hash)) {
            return back()->withErrors(['password' => 'Kata sandi login harus berbeda dari PIN/Passphrase tanda tangan klinis Anda.']);
        }
        DB::transaction(function () use ($request, $data): void {
            $user = $request->user();
            $user->forceFill(['password' => $data['password'], 'must_change_password' => false, 'session_version' => (int) $user->session_version + 1, 'remember_token' => null])->save();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $request->session()->getId())
                    ->delete();
            }
            app(ClinicalAuditRecorder::class)->record($request, 'auth.password_changed');
        });
        $request->session()->regenerate();
        $request->session()->put('clinical_session_version', (int) $request->user()->session_version);

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }

    public function updateSigningPin(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->supportsSigningPin(), 403);

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'current_signing_pin' => [Rule::requiredIf($user->hasSigningPin()), 'nullable', 'string'],
            'signing_pin' => ['required', 'string', 'min:6', 'max:72', 'confirmed'],
        ]);

        if ($user->hasSigningPin() && ! Hash::check((string) ($data['current_signing_pin'] ?? ''), $user->signing_pin_hash)) {
            return back()->withErrors(['current_signing_pin' => 'PIN/Passphrase tanda tangan saat ini tidak sesuai.']);
        }

        if (Hash::check($data['signing_pin'], $user->password)) {
            return back()->withErrors(['signing_pin' => 'PIN/Passphrase tanda tangan klinis wajib berbeda dari kata sandi login akun Anda.']);
        }

        DB::transaction(function () use ($request, $user, $data): void {
            $user->forceFill([
                'signing_pin_hash' => Hash::make($data['signing_pin']),
                'signing_pin_set_at' => now(),
                'must_setup_signing_pin' => false,
            ])->save();
            app(ClinicalAuditRecorder::class)->record($request, 'auth.signing_pin_updated');
        });

        return back()->with('success', 'PIN/Passphrase tanda tangan klinis berhasil disimpan.');
    }

    public function photo(Request $request): StreamedResponse
    {
        $path = $request->user()->photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }

    public function signature(Request $request): StreamedResponse
    {
        $path = $request->user()->signature_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }

    public function uploadPhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $previousPath = $user->photo_path;
        $path = $request->file('photo')->store('profile-photos/'.$user->id, 'local');
        abort_unless(is_string($path), 500, 'Foto profil gagal disimpan.');

        $user->update(['photo_path' => $path]);

        if ($previousPath) {
            Storage::disk('local')->delete($previousPath);
        }

        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }

    public function deletePhoto(Request $request): RedirectResponse
    {
        $user = $request->user();
        $previousPath = $user->photo_path;
        $user->update(['photo_path' => null]);

        if ($previousPath) {
            Storage::disk('local')->delete($previousPath);
        }

        return back()->with('success', 'Foto profil berhasil dihapus.');
    }

    public function uploadSignature(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate([
            'signature' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:1024'],
            'signature_password' => [Rule::requiredIf($user->hasSigningPin() || $user->must_setup_signing_pin), 'nullable', new ClinicalSigningCredential],
        ]);

        $path = $request->file('signature')->store('signatures/'.$user->id, 'local');
        abort_unless(is_string($path), 500, 'Tanda tangan gagal disimpan.');

        try {
            DB::transaction(function () use ($request, $user, $path): void {
                $user->update(['signature_path' => $path]);
                app(ClinicalAuditRecorder::class)->record($request, 'profile.signature_changed', metadata: ['signature_sha256' => hash_file('sha256', Storage::disk('local')->path($path))]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Tanda tangan berhasil diperbarui.');
    }

    public function deleteSignature(Request $request): RedirectResponse
    {
        $user = $request->user();
        if ($user->hasSigningPin() || $user->must_setup_signing_pin) {
            $request->validate([
                'signature_password' => ['required', new ClinicalSigningCredential],
            ]);
        }

        DB::transaction(function () use ($request, $user): void {
            $user->update(['signature_path' => null]);
            app(ClinicalAuditRecorder::class)->record($request, 'profile.signature_removed');
        });

        return back()->with('success', 'Tanda tangan berhasil dihapus.');
    }
}
