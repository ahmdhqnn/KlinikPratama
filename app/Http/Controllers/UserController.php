<?php

namespace App\Http\Controllers;

use App\ClinicalAuditRecorder;
use App\FarmasiClinicResolver;
use App\Models\Nakes;
use App\Models\Poliklinik;
use App\Models\User;
use App\PerawatClinicResolver;
use App\Rules\AvailableAccountEmail;
use App\StaffAccountAccess;
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
    public function __construct(private StaffAccountAccess $access) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::in(User::ROLES)],
            'status' => ['nullable', 'in:aktif,nonaktif,pensiun'],
        ]);

        $users = User::with('nakes.poliklinik')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when(in_array($filters['status'] ?? null, ['aktif', 'nonaktif'], true), fn ($query) => $query
                ->whereIn('role', User::ROLES)->where('is_active', $filters['status'] === 'aktif'))
            ->when(($filters['status'] ?? null) === 'pensiun', fn ($query) => $query->whereNotIn('role', User::ROLES))
            ->orderBy('name')->orderBy('id')
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
                    'retiredRole' => ! in_array($user->role, User::ROLES, true),
                    'nakes' => $user->nakes ? [
                        'id' => $user->nakes->id,
                        'name' => $user->nakes->nama,
                        'code' => $user->nakes->kode,
                        'clinicId' => $user->nakes->poliklinik_id,
                        'clinic' => $user->nakes->poliklinik?->nama,
                    ] : null,
                    'isCurrentUser' => $user->is(auth()->user()),
                    'hasSigningPin' => $user->hasSigningPin(),
                    'mustSetupSigningPin' => (bool) $user->must_setup_signing_pin,
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
            'filters' => ['search' => $filters['search'] ?? '', 'role' => $filters['role'] ?? '', 'status' => $filters['status'] ?? ''],
            'occupiedSingleRoles' => User::occupiedSingleRoles(),
            'roles' => [
                ['value' => 'admin', 'label' => 'Administrator'],
                ['value' => 'dokter', 'label' => 'Dokter'],
                ['value' => 'perawat', 'label' => 'Perawat'],
                ['value' => 'farmasi', 'label' => 'Farmasi'],
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
                    'role' => Nakes::LOGIN_POSITIONS[$staff->jabatan] ?? null,
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
            'email' => ['required', 'email', 'max:255', new AvailableAccountEmail],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(User::ROLES)],
            'is_active' => ['boolean'],
            'poliklinik_id' => [
                Rule::requiredIf($request->input('role') === 'perawat' || ($request->input('role') === 'farmasi' && ($request->exists('poliklinik_id') || ! $request->filled('nakes_id')))),
                'nullable',
                Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at'),
            ],
            'nakes_id' => [
                Rule::requiredIf(in_array($request->input('role'), ['dokter', 'perawat', 'farmasi'], true)),
                'nullable',
                Rule::exists('nakes', 'id')->where(fn ($query) => $query
                    ->whereIn('jabatan', Nakes::positionsForRole($request->string('role')->toString()))
                    ->whereNull('deleted_at')
                    ->where('is_active', true)
                    ->whereNull('user_id')),
            ],
        ]);

        $data['password'] = Hash::make($data['password']);
        $data['is_active'] = $request->boolean('is_active');

        $this->access->save($data['role'], true, null, function () use ($data, $request, $audit): void {
            $user = User::create([...$data, 'must_change_password' => true]);
            $this->syncNakesProfile($user, $data['nakes_id'] ?? null, isset($data['poliklinik_id']) ? (int) $data['poliklinik_id'] : null);
            $audit->record($request, 'user.created', metadata: ['user_id' => $user->id, 'role' => $user->role]);
        });

        return back()->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(Request $request, User $user, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $priorProfile = $user->nakes;
        if (! $request->exists('nakes_id') && $request->input('role') === $user->role && $priorProfile?->is_active) {
            $request->merge(['nakes_id' => $priorProfile->id]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'email', 'max:255', new AvailableAccountEmail($user)],
            'role' => ['required', Rule::in(User::ROLES)],
            'is_active' => ['boolean'],
            'poliklinik_id' => [
                Rule::requiredIf(
                    $request->input('role') === 'perawat'
                    || ($request->input('role') === 'farmasi' && ($request->exists('poliklinik_id') || ($request->input('role') !== $user->role && $request->boolean('is_active', $user->is_active))))
                ),
                'nullable',
                Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at'),
            ],
            'nakes_id' => [
                Rule::requiredIf(
                    in_array($request->input('role'), ['dokter', 'perawat', 'farmasi'], true)
                    && ($request->input('role') !== $user->role || ! $priorProfile)
                    && $request->boolean('is_active', $user->is_active),
                ),
                'nullable',
                Rule::exists('nakes', 'id')->where(fn ($query) => $query
                    ->whereIn('jabatan', Nakes::positionsForRole($request->string('role')->toString()))
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

        $needsSlot = $data['is_active'] || $data['role'] !== $user->role;
        $this->access->save($data['role'], $needsSlot, $user->id, function () use ($data, $user, $request, $audit): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $roleChanged = $user->role !== $data['role'];
            $user->update([...$data, 'session_version' => (int) $user->session_version + 1, 'remember_token' => null]);
            $user->forceFill(['remember_token' => null])->save();
            $this->syncNakesProfile($user, $data['nakes_id'] ?? null, isset($data['poliklinik_id']) ? (int) $data['poliklinik_id'] : null, $roleChanged);
            if ($user->is_active && ! $user->linkedProfileAllowsAccess()) {
                throw ValidationException::withMessages(['is_active' => 'Tautkan profil aktif yang sesuai dengan peran sebelum mengaktifkan akun.']);
            }
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
            User::query()->lockForUpdate()->findOrFail($user->id)->revokeAccess();
            $audit->record($request, 'user.deactivated', metadata: ['user_id' => $user->id, 'source' => 'legacy_delete', 'role' => $user->role]);
        });

        return back()->with('success', 'Akun dinonaktifkan. Identitas operator dan bukti pelayanan tetap tersimpan.');
    }

    public function toggleActive(Request $request, User $user, ClinicalAuditRecorder $audit, PerawatClinicResolver $clinics): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'Tidak dapat mengubah status akun Anda sendiri.');
        }

        $activate = ! $user->is_active;

        try {
            $this->access->save($user->role, $activate, $user->id, function () use ($request, $user, $audit, $activate, $clinics): void {
                $lockedUser = User::query()->lockForUpdate()->findOrFail($user->id);
                if ($lockedUser->role !== $user->role || $lockedUser->is_active !== $user->is_active) {
                    throw ValidationException::withMessages(['is_active' => 'Status atau peran akun berubah. Muat ulang daftar pengguna.']);
                }
                $user = $lockedUser;
                if ($activate) {
                    $this->validateActivation($user, $clinics);
                    $user->forceFill(['is_active' => true, 'session_version' => (int) $user->session_version + 1, 'remember_token' => null])->save();
                } else {
                    $user->revokeAccess();
                }
                $audit->record($request, 'user.status_changed', metadata: ['user_id' => $user->id, 'active' => $user->is_active]);
            }, 'is_active');
        } catch (ValidationException $exception) {
            return back()->with('error', $exception->errors()['is_active'][0] ?? $exception->getMessage());
        }

        return back()->with('success', 'Status pengguna berhasil diperbarui.');
    }

    public function resetPassword(Request $request, User $user, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'min:8', 'max:72']]);

        DB::transaction(function () use ($request, $user, $audit): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($user->hasRole(...User::ROLES), 403);
            $user->update(['password' => Hash::make($request->password), 'must_change_password' => true, 'session_version' => (int) $user->session_version + 1]);
            $user->forceFill(['remember_token' => null])->save();
            $this->purgeUserSessions($user->id);
            $audit->record($request, 'user.password_reset', metadata: ['user_id' => $user->id]);
        });

        return back()->with('success', 'Kata sandi pengguna berhasil diatur ulang.');
    }

    public function resetSigningPin(Request $request, User $user, ClinicalAuditRecorder $audit): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $audit): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            abort_unless($user->supportsSigningPin(), 403);
            $user->forceFill([
                'signing_pin_hash' => null,
                'signing_pin_set_at' => null,
                'must_setup_signing_pin' => true,
                'session_version' => (int) $user->session_version + 1,
                'remember_token' => null,
            ])->save();
            $this->purgeUserSessions($user->id);
            $audit->record($request, 'user.signing_pin_reset', metadata: ['user_id' => $user->id, 'role' => $user->role]);
        });

        return back()->with('success', 'Status PIN tanda tangan klinis direset. Petugas wajib menyetel PIN pribadi baru di menu Profil saya.');
    }

    private function purgeUserSessions(int $userId): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $userId)->delete();
        }
    }

    private function validateActivation(User $user, PerawatClinicResolver $clinics): void
    {
        $error = match (true) {
            ! $user->hasRole(...User::ROLES) => 'Pilih peran yang masih tersedia sebelum mengaktifkan akun.',
            ! $user->linkedProfileAllowsAccess() => 'Aktifkan profil petugas terlebih dahulu sebelum mengaktifkan akun.',
            $user->hasRole('dokter', 'perawat', 'farmasi') && ! $user->nakes => 'Tautkan profil petugas yang sesuai sebelum mengaktifkan akun.',
            $user->isPerawat() && ! $clinics->assignedNakes($user) => 'Lengkapi penugasan poli aktif sebelum mengaktifkan akun perawat.',
            $user->role === 'farmasi' && ! app(FarmasiClinicResolver::class)->assignedNakes($user) => 'Lengkapi penugasan poli aktif sebelum mengaktifkan akun farmasi.',
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages(['is_active' => $error]);
        }
    }

    private function syncNakesProfile(User $user, ?int $nakesId = null, ?int $clinicId = null, bool $roleChanged = false): void
    {
        $compatiblePositions = Nakes::positionsForRole($user->role);
        $syncsClinic = in_array($user->role, ['perawat', 'farmasi'], true);
        if ($nakesId === null) {
            if ($roleChanged) {
                Nakes::withTrashed()->where('user_id', $user->id)->whereNotIn('jabatan', $compatiblePositions)->update(['user_id' => null]);
            } elseif ($syncsClinic && $clinicId !== null) {
                $user->nakes()->whereIn('jabatan', $compatiblePositions)->update(['poliklinik_id' => $clinicId]);
            }

            return;
        }

        $selected = Nakes::query()->lockForUpdate()->find($nakesId);
        if (! $selected?->is_active || ($selected->user_id !== null && $selected->user_id !== $user->id)
            || ! in_array($selected->jabatan, $compatiblePositions, true)) {
            throw ValidationException::withMessages(['nakes_id' => 'Profil petugas tidak tersedia untuk akun ini.']);
        }

        Nakes::withTrashed()->where('user_id', $user->id)->whereKeyNot($selected->id)->update(['user_id' => null]);
        $selected->update([
            'user_id' => $user->id,
            ...($syncsClinic && $clinicId !== null ? ['poliklinik_id' => $clinicId] : []),
        ]);
    }
}
