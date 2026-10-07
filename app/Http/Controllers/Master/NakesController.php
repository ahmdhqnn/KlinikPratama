<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Nakes;
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
        $nakes = Nakes::with('user')
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($subquery) => $subquery
                ->where('nama', 'like', "%{$search}%")
                ->orWhere('kode', 'like', "%{$search}%")))
            ->when($filters['kategori'] ?? null, fn ($query, string $category) => $query->where('kategori', $category))
            ->when($filters['jabatan'] ?? null, fn ($query, string $position) => $query->where('jabatan', $position))
            ->orderBy('nama')
            ->paginate(15)
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
                ['value' => 'lab', 'label' => 'Petugas laboratorium'],
                ['value' => 'farmasi', 'label' => 'Farmasi / Apoteker'],
                ['value' => 'apotek', 'label' => 'Apoteker'],
                ['value' => 'kasir', 'label' => 'Kasir'],
                ['value' => 'pendaftaran', 'label' => 'Petugas pendaftaran'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $requiresLogin = $request->boolean('buat_akun');
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:30', 'unique:nakes,kode'],
            'nama' => ['required', 'string', 'max:200'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'kategori' => ['required', 'in:medis,non_medis'],
            'jabatan' => ['required', 'string'],
            'no_sip' => ['nullable', 'string', 'max:100'],
            'no_str' => ['nullable', 'string', 'max:100'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'buat_akun' => ['boolean'],
            'email' => [Rule::requiredIf($requiresLogin), 'nullable', 'email', 'unique:users,email'],
            'password' => [Rule::requiredIf($requiresLogin), 'nullable', 'min:6'],
        ]);

        DB::transaction(function () use ($data, $request): void {
            $userId = null;
            if ($request->boolean('buat_akun')) {
                $user = User::create([
                    'name' => $data['nama'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'role' => $this->getRoleFromJabatan($data['jabatan']),
                    'is_active' => true,
                ]);
                $userId = $user->id;
            }

            Nakes::create([
                'kode' => $data['kode'],
                'nama' => $data['nama'],
                'jenis_kelamin' => $data['jenis_kelamin'],
                'kategori' => $data['kategori'],
                'jabatan' => $data['jabatan'],
                'no_sip' => $data['no_sip'],
                'no_str' => $data['no_str'],
                'telepon' => $data['telepon'],
                'user_id' => $userId,
                'is_active' => $request->boolean('is_active'),
            ]);
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
            'jabatan' => ['required', 'string'],
            'no_sip' => ['nullable', 'string', 'max:100'],
            'no_str' => ['nullable', 'string', 'max:100'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $nake->update($data);

        return back()->with('success', 'Data nakes berhasil diperbarui.');
    }

    public function destroy(Nakes $nake): RedirectResponse
    {
        $nake->delete();

        return back()->with('success', 'Data nakes berhasil dihapus.');
    }

    private function getRoleFromJabatan(string $jabatan): string
    {
        return match ($jabatan) {
            'dokter' => 'dokter',
            'perawat', 'bidan' => 'perawat',
            'farmasi', 'apotek' => 'farmasi',
            'kasir' => 'kasir',
            'pendaftaran' => 'pendaftaran',
            'lab', 'petugas_lab' => 'perawat',
            default => 'perawat',
        };
    }
}
