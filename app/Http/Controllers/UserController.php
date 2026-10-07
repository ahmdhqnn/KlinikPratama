<?php

namespace App\Http\Controllers;

use App\Models\Nakes;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::with('nakes')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))
            ->when($request->role, fn ($q, $r) => $q->where('role', $r))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $dokterList = Nakes::query()
            ->where('jabatan', 'dokter')
            ->where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode', 'user_id']);

        return view('users.index', compact('users', 'dokterList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:6'],
            'role' => ['required', 'in:admin,dokter,perawat,farmasi,kasir,pendaftaran'],
            'is_active' => ['boolean'],
            'nakes_id' => [
                Rule::requiredIf($request->input('role') === 'dokter'),
                'nullable',
                Rule::exists('nakes', 'id')->where(fn ($query) => $query
                    ->where('jabatan', 'dokter')
                    ->where('is_active', true)
                    ->whereNull('user_id')),
            ],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $request->boolean('is_active');

        DB::transaction(function () use ($data): void {
            $user = User::create($data);
            $this->syncNakesProfile($user, $data['nakes_id'] ?? null);
        });

        return back()->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', "unique:users,email,{$user->id}"],
            'role' => ['required', 'in:admin,dokter,perawat,farmasi,kasir,pendaftaran'],
            'is_active' => ['boolean'],
            'nakes_id' => [
                Rule::requiredIf($request->input('role') === 'dokter'),
                'nullable',
                Rule::exists('nakes', 'id')->where(fn ($query) => $query
                    ->where('jabatan', 'dokter')
                    ->where('is_active', true)
                    ->where(function ($query) use ($user): void {
                        $query->whereNull('user_id')->orWhere('user_id', $user->id);
                    })),
            ],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        DB::transaction(function () use ($data, $user): void {
            $user->update($data);
            $this->syncNakesProfile($user->fresh(), $data['nakes_id'] ?? null);
        });

        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun Anda sendiri.');
        }

        $user->delete();

        return back()->with('success', 'User berhasil dihapus.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat mengubah status akun Anda sendiri.');
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', 'Status user berhasil diubah.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $request->validate(['password' => ['required', 'min:6']]);

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password user berhasil direset.');
    }

    private function syncNakesProfile(User $user, ?int $nakesId = null): void
    {
        $nakesRoles = ['dokter', 'perawat', 'farmasi', 'kasir', 'pendaftaran'];

        if (! in_array($user->role, $nakesRoles, true)) {
            return;
        }

        if ($user->role === 'dokter') {
            $selectedNakes = Nakes::findOrFail($nakesId);
            $currentNakes = $user->nakes;

            if ($currentNakes && $currentNakes->isNot($selectedNakes)) {
                $currentNakes->update(['user_id' => null]);
            }

            $selectedNakes->update(['user_id' => $user->id]);

            return;
        }

        if ($user->nakes?->jabatan === 'dokter') {
            $user->nakes->update(['user_id' => null]);
        }

        $nakes = $user->nakes()->first();
        $jabatan = $user->role;
        $kategori = in_array($jabatan, ['dokter', 'perawat'], true) ? 'medis' : 'non_medis';

        if ($nakes) {
            $nakes->update([
                'nama' => $user->name,
                'jabatan' => $jabatan,
                'kategori' => $kategori,
                'is_active' => $user->is_active,
            ]);

            return;
        }

        $nakes = Nakes::withTrashed()->where('user_id', $user->id)->first();
        if ($nakes) {
            $nakes->restore();
            $nakes->update([
                'nama' => $user->name,
                'jabatan' => $jabatan,
                'kategori' => $kategori,
                'is_active' => $user->is_active,
            ]);

            return;
        }

        Nakes::create([
            'kode' => $this->generateNakesCode($jabatan),
            'nama' => $user->name,
            'kategori' => $kategori,
            'jabatan' => $jabatan,
            'user_id' => $user->id,
            'is_active' => $user->is_active,
        ]);
    }

    private function generateNakesCode(string $jabatan): string
    {
        $prefix = match ($jabatan) {
            'dokter' => 'DR',
            'perawat' => 'PRW',
            'farmasi' => 'FAR',
            'kasir' => 'KSR',
            'pendaftaran' => 'REG',
            default => 'NKS',
        };

        do {
            $code = $prefix.'-'.str()->upper(str()->random(6));
        } while (Nakes::withTrashed()->where('kode', $code)->exists());

        return $code;
    }
}
