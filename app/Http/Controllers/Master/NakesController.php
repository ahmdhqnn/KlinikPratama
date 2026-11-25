<?php

namespace App\Http\Controllers\Master;

use App\ClinicalAuditRecorder;
use App\Http\Controllers\Controller;
use App\JadwalPraktikResolver;
use App\Models\Nakes;
use App\Models\Poliklinik;
use App\Models\User;
use App\Rules\AvailableAccountEmail;
use App\StaffAccountAccess;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NakesController extends Controller
{
    public function __construct(private StaffAccountAccess $access) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kategori' => ['nullable', 'in:medis,non_medis'],
            'jabatan' => ['nullable', 'string', 'max:50'],
            'penugasan' => ['nullable', 'in:perlu-perhatian'],
        ]);
        $needsClinic = Nakes::query()->where('is_active', true)
            ->whereIn('jabatan', ['dokter', 'perawat', 'bidan', 'terapis_gigi'])
            ->whereDoesntHave('poliklinik', fn ($query) => $query->registrable());
        $nakes = Nakes::with(['user', 'poliklinik'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($subquery) => $subquery
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('kode', 'like', "%{$search}%")))
            ->when($filters['kategori'] ?? null, fn ($query, string $category) => $query->where('kategori', $category))
            ->when($filters['jabatan'] ?? null, fn ($query, string $position) => $query->where('jabatan', $position))
            ->when(isset($filters['penugasan']), fn ($query) => $query->whereIn('id', (clone $needsClinic)->select('id')))
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('master/nakes/index', [
            'missingClinicCount' => $needsClinic->count(),
            'loginPositions' => array_keys(array_diff(Nakes::LOGIN_POSITIONS, User::occupiedSingleRoles())),
            'staff' => [
                'data' => $nakes->getCollection()->map(fn (Nakes $staffMember): array => [
                    'id' => $staffMember->id,
                    'code' => $staffMember->kode,
                    'name' => $staffMember->nama,
                    'gender' => $staffMember->jenis_kelamin,
                    'category' => $staffMember->kategori,
                    'position' => $staffMember->jabatan,
                    'clinicId' => $staffMember->poliklinik_id,
                    'clinic' => $staffMember->poliklinik?->nama,
                    'sip' => $staffMember->no_sip,
                    'str' => $staffMember->no_str,
                    'phone' => $staffMember->telepon,
                    'email' => $staffMember->user?->email,
                    'accountActive' => $staffMember->user?->is_active && $staffMember->user->hasRole(...User::ROLES),
                    'active' => $staffMember->is_active,
                ])->values(),
                'currentPage' => $nakes->currentPage(),
                'lastPage' => $nakes->lastPage(),
                'perPage' => $nakes->perPage(),
                'total' => $nakes->total(),
                'from' => $nakes->firstItem(),
                'to' => $nakes->lastItem(),
                'previousUrl' => $nakes->previousPageUrl(),
                'nextUrl' => $nakes->nextPageUrl(),
            ],
            'filters' => [
                'search' => $filters['search'] ?? '',
                'category' => $filters['kategori'] ?? '',
                'position' => $filters['jabatan'] ?? '',
                'assignment' => $filters['penugasan'] ?? '',
            ],
            'categories' => [
                ['value' => 'medis', 'label' => 'Tenaga medis'],
                ['value' => 'non_medis', 'label' => 'Non-medis'],
            ],
            'positions' => [
                ['value' => 'dokter', 'label' => 'Dokter'],
                ['value' => 'perawat', 'label' => 'Perawat'],
                ['value' => 'bidan', 'label' => 'Bidan'],
                ['value' => 'terapis_gigi', 'label' => 'Terapis gigi dan mulut'],
                ['value' => 'lab', 'label' => 'Petugas laboratorium'],
                ['value' => 'farmasi', 'label' => 'Farmasi / Apoteker'],
                ['value' => 'apotek', 'label' => 'Apoteker'],
                ['value' => 'pendaftaran', 'label' => 'Petugas pendaftaran'],
            ],
            'clinics' => Poliklinik::registrable()->orderBy('nama')->get(['id', 'nama'])->map(fn (Poliklinik $clinic): array => ['id' => $clinic->id, 'name' => $clinic->nama])->values(),
        ]);
    }

    public function store(Request $request, JadwalPraktikResolver $schedule, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $this->normalizeIdentity($request);
        $requiresLogin = $request->boolean('buat_akun');
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:nakes,kode'],
            'nama' => ['required', 'string', 'max:200'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'kategori' => ['required', 'in:medis,non_medis'],
            'jabatan' => ['required', Rule::in(['dokter', 'perawat', 'bidan', 'terapis_gigi', 'farmasi', 'apotek', 'pendaftaran', 'lab', 'petugas_lab'])],
            'poliklinik_id' => ['required_if:jabatan,dokter,perawat,bidan,terapis_gigi', 'nullable', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'no_sip' => ['nullable', 'string', 'max:100'],
            'no_str' => ['nullable', 'string', 'max:100', $this->uniqueStr()],
            'telepon' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'buat_akun' => ['boolean'],
            'email' => [Rule::excludeIf(! $requiresLogin), 'required', 'email', 'max:255', new AvailableAccountEmail],
            'password' => [Rule::excludeIf(! $requiresLogin), 'required', 'string', 'min:8'],
        ]);

        $isActive = $request->boolean('is_active', true);
        $loginRole = Nakes::LOGIN_POSITIONS[$data['jabatan']] ?? null;
        if ($requiresLogin && (! $loginRole || ! $isActive)) {
            throw ValidationException::withMessages(['buat_akun' => 'Akun hanya dapat dibuat untuk profil aktif dengan jabatan yang berhak mengakses aplikasi.']);
        }

        $this->access->save($loginRole ?? '', $requiresLogin, null, function () use ($data, $request, $schedule, $audit, $requiresLogin, $loginRole, $isActive): void {
            $userId = null;
            if ($requiresLogin) {
                $user = User::create([
                    'name' => $data['nama'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'role' => $loginRole,
                    'is_active' => $isActive,
                    'must_change_password' => true,
                ]);
                $userId = $user->id;
            }

            $staffMember = Nakes::create([
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                'kategori' => $data['kategori'],
                'jabatan' => $data['jabatan'],
                'poliklinik_id' => $data['poliklinik_id'] ?? null,
                'no_sip' => $data['no_sip'] ?? null,
                'no_str' => $data['no_str'] ?? null,
                'telepon' => $data['telepon'] ?? null,
                'user_id' => $userId,
                'is_active' => $isActive,
            ]);
            if ($staffMember->jabatan === 'dokter' && $staffMember->is_active) {
                $schedule->applyAsnTemplate($staffMember->id, (int) $data['poliklinik_id'], today()->toDateString());
            }
            $audit->record($request, 'staff.created', metadata: ['staff_id' => $staffMember->id, 'user_id' => $userId]);
        }, 'buat_akun');

        return back()->with('success', 'Data nakes berhasil ditambahkan.');
    }

    public function update(Request $request, Nakes $nake, ClinicalAuditRecorder $audit): RedirectResponse
    {
        $this->normalizeIdentity($request);
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', "unique:nakes,kode,{$nake->id}"],
            'nama' => ['required', 'string', 'max:200'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'kategori' => ['required', 'in:medis,non_medis'],
            'jabatan' => ['required', Rule::in(['dokter', 'perawat', 'bidan', 'terapis_gigi', 'farmasi', 'apotek', 'pendaftaran', 'lab', 'petugas_lab'])],
            'poliklinik_id' => ['required_if:jabatan,dokter,perawat,bidan,terapis_gigi', 'nullable', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'no_sip' => ['nullable', 'string', 'max:100'],
            'no_str' => ['nullable', 'string', 'max:100', $this->uniqueStr($nake)],
            'telepon' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', $nake->is_active);
        DB::transaction(function () use ($nake, $data, $request, $audit): void {
            $nake = Nakes::query()->lockForUpdate()->findOrFail($nake->id);
            $account = $nake->user()->lockForUpdate()->first();

            if ($account && $data['jabatan'] !== $nake->jabatan
                && (Nakes::LOGIN_POSITIONS[$data['jabatan']] ?? null) !== $account->role) {
                throw ValidationException::withMessages(['jabatan' => 'Jabatan tidak sesuai dengan peran akun tertaut. Ubah penautan akun di Manajemen Pengguna.']);
            }

            if (! $data['is_active'] && $account?->is($request->user())) {
                throw ValidationException::withMessages(['is_active' => 'Profil yang terhubung dengan akun Anda sendiri tidak dapat dinonaktifkan.']);
            }

            $nake->update($data);
            if (! $nake->is_active && $account?->is_active) {
                $account->revokeAccess();
            }
            $audit->record($request, 'staff.updated', metadata: ['staff_id' => $nake->id, 'user_id' => $nake->user_id, 'active' => $nake->is_active]);
        }, attempts: 3);

        return back()->with('success', 'Data nakes berhasil diperbarui.');
    }

    public function destroy(Request $request, Nakes $nake, ClinicalAuditRecorder $audit): RedirectResponse
    {
        DB::transaction(function () use ($nake, $request, $audit): void {
            $nake = Nakes::query()->lockForUpdate()->findOrFail($nake->id);
            $account = $nake->user()->lockForUpdate()->first();
            if ($account?->is($request->user())) {
                throw ValidationException::withMessages(['staff' => 'Profil yang terhubung dengan akun Anda sendiri tidak dapat diarsipkan.']);
            }
            if ($account?->is_active) {
                $account->revokeAccess();
            }
            $nake->delete();
            $audit->record($request, 'staff.archived', metadata: ['staff_id' => $nake->id, 'user_id' => $nake->user_id]);
        });

        return back()->with('success', 'Data nakes berhasil dihapus.');
    }

    private function uniqueStr(?Nakes $except = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($except): void {
            if (is_string($value) && Nakes::withTrashed()->whereRaw('UPPER(TRIM(no_str)) = ?', [$value])
                ->when($except, fn ($query, Nakes $staff) => $query->whereKeyNot($staff->id))->exists()) {
                $fail('Nomor STR sudah tercatat pada profil lain.');
            }
        };
    }

    private function normalizeIdentity(Request $request): void
    {
        $fields = [];
        foreach (['kode', 'no_str'] as $field) {
            if (is_string($value = $request->input($field))) {
                $fields[$field] = $field === 'no_str' && in_array(trim($value), ['', '-'], true)
                    ? null : str($value)->trim()->upper()->toString();
            }
        }

        $request->merge($fields);
    }
}
