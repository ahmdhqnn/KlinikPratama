<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\Models\Nakes;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'in:admin,dokter,perawat,farmasi,pendaftaran,manajemen'],
        ]);

        $users = User::with('nakes.poliklinik')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();

        $dokterList = Nakes::query()
            ->where('jabatan', 'dokter')
            ->where('is_active', true)
            ->orderBy('nama')
            ->get(['id', 'nama', 'kode', 'user_id']);

        return Inertia::render('users/index', [
            'users' => [
                'data' => $users->getCollection()->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'roleLabel' => $user->role_label,
                    'active' => $user->is_active,
                    'nakes' => $user->nakes ? [
                        'id' => $user->nakes->id,
                        'name' => $user->nakes->nama,
                        'code' => $user->nakes->kode,
                        'clinicId' => $user->nakes->poliklinik_id,
                        'clinic' => $user->nakes->poliklinik?->nama,
                    ] : null,
                    'isCurrentUser' => $user->is(auth()->user()),
                ])->values(),
                'currentPage' => $users->currentPage(),
                'lastPage' => $users->lastPage(),
                'perPage' => $users->perPage(),
                'total' => $users->total(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
                'previousUrl' => $users->previousPageUrl(),
                'nextUrl' => $users->nextPageUrl(),
            ],
            'filters' => ['search' => $filters['search'] ?? '', 'role' => $filters['role'] ?? ''],
            'roles' => [
                ['value' => 'admin', 'label' => 'Administrator'],
                ['value' => 'dokter', 'label' => 'Dokter'],
                ['value' => 'perawat', 'label' => 'Perawat'],
                ['value' => 'farmasi', 'label' => 'Farmasi'],
                ['value' => 'manajemen', 'label' => 'Manajemen'],
                ['value' => 'pendaftaran', 'label' => 'Pendaftaran'],
            ],
            'doctors' => $dokterList->map(fn (Nakes $doctor): array => [
                'id' => $doctor->id,
                'name' => $doctor->nama,
                'code' => $doctor->kode,
                'linked' => $doctor->user_id !== null,
            ])->values(),
            'staffProfiles' => Nakes::query()->where('is_active', true)->orderBy('nama')->get()
                ->map(fn (Nakes $staff): array => [
                    'id' => $staff->id, 'name' => $staff->nama, 'code' => $staff->kode,
                    'role' => match ($staff->jabatan) {
                        'dokter' => 'dokter', 'perawat', 'bidan', 'terapis_gigi' => 'perawat',
                        'farmasi', 'apotek' => 'farmasi', 'pendaftaran' => 'pendaftaran', default => null,
                    },
                    'userId' => $staff->user_id, 'clinicId' => $staff->poliklinik_id,
                ])->values(),
            'clinics' => Poliklinik::registrable()->orderBy('nama')->get(['id', 'nama'])->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
        ]);
    }

    public function store(Request $request, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,dokter,perawat,farmasi,pendaftaran,manajemen'],
            'is_active' => ['boolean'],
            'poliklinik_id' => [Rule::requiredIf($request->input('role') === 'perawat'), 'nullable', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'nakes_id' => [
                Rule::requiredIf($request->input('role') === 'dokter'),
                'nullable',
                Rule::exists('nakes', 'id')->where(fn ($query) => $query
                    ->whereIn('jabatan', $this->positionsForRole($request->string('role')->toString()))
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->whereNull('user_id')),
            ],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $request->boolean('is_active');

        DB::transaction(function () use ($data, $request, $audit): void {
            $user = User::create($data);
            $this->syncNakesProfile($user, $data['nakes_id'] ?? null, isset($data['poliklinik_id']) ? (int) $data['poliklinik_id'] : null);
            $audit->record($request, 'user.created', metadata: ['user_id' => $user->id, 'role' => $user->role]);
        });

        return back()->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(Request $request, User $user, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', "unique:users,email,{$user->id}"],
            'role' => ['required', 'in:admin,dokter,perawat,farmasi,pendaftaran,manajemen'],
            'is_active' => ['boolean'],
            'poliklinik_id' => [Rule::requiredIf($request->input('role') === 'perawat'), 'nullable', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'nakes_id' => [
                Rule::requiredIf($request->input('role') === 'dokter'),
                'nullable',
                Rule::exists('nakes', 'id')->where(fn ($query) => $query
                    ->whereIn('jabatan', $this->positionsForRole($request->string('role')->toString()))
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->where(function ($query) use ($user): void {
                        $query->whereNull('user_id')->orWhere('user_id', $user->id);
                    })),
            ],
        ]);

        $data['is_active'] = $request->boolean('is_active', $user->is_active);
        if ($data['is_active'] && $data['role'] === $user->role && $user->nakes && ! $user->nakes->is_active
            && (empty($data['nakes_id']) || (int) $data['nakes_id'] === $user->nakes->id)) {
            return back()->withErrors(['is_active' => 'Aktifkan profil petugas terlebih dahulu sebelum mengaktifkan akun.']);
        }

        if ($user->is($request->user()) && ($data['role'] !== $user->role || $data['is_active'] !== $user->is_active)) {
            return back()->with('error', 'Peran dan status akun sendiri tidak dapat diubah.');
        }

        DB::transaction(function () use ($data, $user, $request, $audit): void {
            $user->update($data);
            $this->syncNakesProfile($user->fresh(), $data['nakes_id'] ?? null, isset($data['poliklinik_id']) ? (int) $data['poliklinik_id'] : null);
            $audit->record($request, 'user.updated', metadata: ['user_id' => $user->id, 'role' => $user->role, 'active' => $user->is_active]);
        });

        return back()->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user, ClinicalAuditRecorder $audit): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat menghapus akun Anda sendiri.');
        }

        DB::transaction(function () use ($request, $user, $audit): void {
            $user->delete();
            $audit->record($request, 'user.deleted', metadata: ['user_id' => $user->id]);
        });

        return back()->with('success', 'Pengguna berhasil dihapus.');
    }

    public function toggleActive(Request $request, User $user, ClinicalAuditRecorder $audit): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat mengubah status akun Anda sendiri.');
        }

        if (! $user->is_active && $user->nakes && ! $user->nakes->is_active) {
            return back()->with('error', 'Aktifkan profil petugas terlebih dahulu sebelum mengaktifkan akun.');
        }

        DB::transaction(function () use ($request, $user, $audit): void {
            $user->update(['is_active' => ! $user->is_active]);
            $audit->record($request, 'user.status_changed', metadata: ['user_id' => $user->id, 'active' => $user->is_active]);
        });

        return back()->with('success', 'Status pengguna berhasil diperbarui.');
    }

    public function resetPassword(Request $request, User $user, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'min:8']]);

        DB::transaction(function () use ($request, $user, $audit): void {
            $user->update(['password' => Hash::make($request->password)]);
            $audit->record($request, 'user.password_reset', metadata: ['user_id' => $user->id]);
        });

        return back()->with('success', 'Kata sandi pengguna berhasil diatur ulang.');
    }

    private function syncNakesProfile(User $user, ?int $nakesId = null, ?int $clinicId = null): void
    {
        $nakesRoles = ['dokter', 'perawat', 'farmasi', 'pendaftaran'];

        if (! in_array($user->role, $nakesRoles, true)) {
            Nakes::withTrashed()->where('user_id', $user->id)->update(['user_id' => null]);

            return;
        }

        if ($nakesId !== null) {
            $selectedNakes = Nakes::query()->lockForUpdate()->findOrFail($nakesId);
            if (! $selectedNakes->is_active || ($selectedNakes->user_id !== null && $selectedNakes->user_id !== $user->id)
                || ! in_array($selectedNakes->jabatan, $this->positionsForRole($user->role), true)) {
                throw ValidationException::withMessages(['nakes_id' => 'Profil petugas tidak tersedia untuk akun ini.']);
            }
            $currentNakes = $user->nakes;

            if ($currentNakes && $currentNakes->isNot($selectedNakes)) {
                $currentNakes->update(['user_id' => null]);
            }

            $selectedNakes->update([
                'user_id' => $user->id,
                ...($user->role === 'perawat' ? ['poliklinik_id' => $clinicId] : []),
            ]);

            return;
        }

        if ($user->nakes?->jabatan === 'dokter') {
            $user->nakes->update(['user_id' => null]);
        }

        $nakes = $user->nakes()->first();
        $jabatan = $user->role === 'perawat' && in_array($nakes?->jabatan, ['bidan', 'terapis_gigi'], true)
            ? $nakes->jabatan : $user->role;
        $kategori = in_array($jabatan, ['dokter', 'perawat', 'bidan', 'terapis_gigi'], true) ? 'medis' : 'non_medis';

        if ($nakes) {
            $nakes->update([
                'nama' => $user->name,
                'jabatan' => $jabatan,
                'kategori' => $kategori,
                'poliklinik_id' => $user->role === 'perawat' ? $clinicId : null,
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
                'poliklinik_id' => $user->role === 'perawat' ? $clinicId : null,
                'is_active' => $user->is_active,
            ]);

            return;
        }

        Nakes::create([
            'kode' => $this->generateNakesCode($jabatan),
            'nama' => $user->name,
            'kategori' => $kategori,
            'jabatan' => $jabatan,
            'poliklinik_id' => $user->role === 'perawat' ? $clinicId : null,
            'user_id' => $user->id,
            'is_active' => $user->is_active,
        ]);
    }

    private function positionsForRole(string $role): array
    {
        return match ($role) {
            'dokter' => ['dokter'],
            'perawat' => ['perawat', 'bidan', 'terapis_gigi'],
            'farmasi' => ['farmasi', 'apotek'],
            'pendaftaran' => ['pendaftaran'],
            default => [],
        };
    }

    private function generateNakesCode(string $jabatan): string
    {
        $prefix = match ($jabatan) {
            'dokter' => 'DR',
            'perawat' => 'PRW',
            'farmasi' => 'FAR',
            'pendaftaran' => 'REG',
            default => 'NKS',
        };

        do {
            $code = $prefix.'-'.str()->upper(str()->random(6));
        } while (Nakes::withTrashed()->where('kode', $code)->exists());

        return $code;
    }
}
