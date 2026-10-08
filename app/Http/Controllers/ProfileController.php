<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'roleLabel' => $user->role_label,
                'active' => $user->is_active,
                'joinedAt' => $user->created_at?->format('d/m/Y'),
                'photoUrl' => $user->photo_path ? route('profile.photo', ['v' => basename($user->photo_path)]) : null,
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
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
        ]);

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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
    }

    public function photo(Request $request): StreamedResponse
    {
        $path = $request->user()->photo_path;
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
}
