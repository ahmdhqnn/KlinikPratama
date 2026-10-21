<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\JadwalPraktikResolver;
use App\Models\Nakes;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NakesController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kategori' => ['nullable', 'in:medis,non_medis'],
            'jabatan' => ['nullable', 'string', 'max:50'],
        ]);
        $nakes = Nakes::with(['user', 'poliklinik'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($subquery) => $subquery
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('kode', 'like', "%{$search}%")))
            ->when($filters['kategori'] ?? null, fn ($query, string $category) => $query->where('kategori', $category))
            ->when($filters['jabatan'] ?? null, fn ($query, string $position) => $query->where('jabatan', $position))
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('master/nakes/index', [
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

    public function store(Request $request, JadwalPraktikResolver $schedule): RedirectResponse
    {
        $requiresLogin = $request->boolean('buat_akun');
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:nakes,kode'],
            'nama' => ['required', 'string', 'max:200'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'kategori' => ['required', 'in:medis,non_medis'],
            'jabatan' => ['required', Rule::in(['dokter', 'perawat', 'bidan', 'terapis_gigi', 'farmasi', 'apotek', 'pendaftaran', 'lab', 'petugas_lab'])],
            'poliklinik_id' => ['required_if:jabatan,dokter,perawat,bidan,terapis_gigi', 'nullable', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'no_sip' => ['nullable', 'string', 'max:100'],
            'no_str' => ['nullable', 'string', 'max:100'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'buat_akun' => ['boolean'],
            'email' => [Rule::requiredIf($requiresLogin), 'nullable', 'email', 'unique:users,email'],
            'password' => [Rule::requiredIf($requiresLogin), 'nullable', 'min:6'],
        ]);

        DB::transaction(function () use ($data, $request, $schedule): void {
            $userId = null;
            if ($request->boolean('buat_akun')) {
                $user = User::create([
                    'name' => $data['nama'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'role' => $this->getRoleFromJabatan($data['jabatan']),
                    'is_active' => $request->boolean('is_active'),
                ]);
                $userId = $user->id;
            }

            $staffMember = Nakes::create([
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'jenis_kelamin' => $data['jenis_kelamin'],
                'kategori' => $data['kategori'],
                'jabatan' => $data['jabatan'],
                'poliklinik_id' => $data['poliklinik_id'] ?? null,
                'no_sip' => $data['no_sip'],
                'no_str' => $data['no_str'],
                'telepon' => $data['telepon'],
                'user_id' => $userId,
                'is_active' => $request->boolean('is_active'),
            ]);
            if ($staffMember->jabatan === 'dokter' && $staffMember->is_active) {
                $schedule->applyAsnTemplate($staffMember->id, (int) $data['poliklinik_id'], today()->toDateString());
            }
        });

        return back()->with('success', 'Data nakes berhasil ditambahkan.');
    }

    public function update(Request $request, Nakes $nake): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', "unique:nakes,kode,{$nake->id}"],
            'nama' => ['required', 'string', 'max:200'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'kategori' => ['required', 'in:medis,non_medis'],
            'jabatan' => ['required', Rule::in(['dokter', 'perawat', 'bidan', 'terapis_gigi', 'farmasi', 'apotek', 'pendaftaran', 'lab', 'petugas_lab'])],
            'poliklinik_id' => ['required_if:jabatan,perawat,bidan,terapis_gigi', 'nullable', Rule::exists('poliklinik', 'id')->where('is_active', true)->whereIn('jenis', ['umum', 'gigi'])->whereNull('deleted_at')],
            'no_sip' => ['nullable', 'string', 'max:100'],
            'no_str' => ['nullable', 'string', 'max:100'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $nake->loadMissing('user');

        if ($nake->user && $this->getRoleFromJabatan($data['jabatan']) !== $nake->user->role) {
            return back()->withErrors(['jabatan' => 'Jabatan tidak sesuai dengan peran akun tertaut. Ubah peran akun di Manajemen Pengguna terlebih dahulu.']);
        }

        DB::transaction(function () use ($nake, $data): void {
            $nake->update($data);

            if (! $nake->is_active) {
                $nake->user?->update(['is_active' => false]);
            }
        });

        return back()->with('success', 'Data nakes berhasil diperbarui.');
    }

    public function destroy(Nakes $nake): RedirectResponse
    {
        DB::transaction(function () use ($nake): void {
            $nake->user?->update(['is_active' => false]);
            $nake->delete();
        });

        return back()->with('success', 'Data nakes berhasil dihapus.');
    }

    private function getRoleFromJabatan(string $jabatan): string
    {
        return match ($jabatan) {
            'dokter' => 'dokter',
            'perawat', 'bidan', 'terapis_gigi' => 'perawat',
            'farmasi', 'apotek' => 'farmasi',
            'pendaftaran' => 'pendaftaran',
            'lab', 'petugas_lab' => 'perawat',
            default => 'perawat',
        };
    }
}
