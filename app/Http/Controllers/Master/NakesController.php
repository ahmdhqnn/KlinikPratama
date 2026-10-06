<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Nakes;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class NakesController extends Controller
{
    public function index(Request $request): View
    {
        $nakes = Nakes::with('user')
            ->when($request->search, fn ($q, $s) => $q->where('nama', 'like', "%$s%")->orWhere('kode', 'like', "%$s%"))
            ->when($request->kategori, fn ($q, $k) => $q->where('kategori', $k))
            ->when($request->jabatan, fn ($q, $j) => $q->where('jabatan', $j))
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        $userList = User::whereNull('id')->get(); // placeholder, will show available users

        return view('master.nakes.index', compact('nakes'));
    }

    public function store(Request $request): RedirectResponse
    {
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
            'email' => ['nullable', 'email', 'unique:users,email'],
            'password' => ['nullable', 'min:6'],
        ]);

        $userId = null;
        if ($request->boolean('buat_akun') && $request->email) {
            $user = User::create([
                'name' => $request->nama,
                'email' => $request->email,
                'password' => Hash::make($request->password ?? 'password'),
                'role' => $this->getRoleFromJabatan($request->jabatan),
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
