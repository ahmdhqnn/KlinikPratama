<?php

namespace App\Http\Controllers\Pelayanan;

use App\Http\Controllers\Controller;
use App\Models\Asuransi;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class KunjunganController extends Controller
{
    public function index(Request $request): View
    {
        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'dokter'])
            ->when($request->search, fn ($q, $s) => $q->whereHas('pasien', fn ($pq) => $pq->where('nama', 'like', "%$s%")->orWhere('no_rm', 'like', "%$s%")))
            ->when($request->tanggal, fn ($q, $t) => $q->whereDate('tanggal', $t))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->poliklinik_id, fn ($q, $p) => $q->where('poliklinik_id', $p))
            ->whereDate('tanggal', $request->tanggal ?? today())
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();

        return view('pelayanan.kunjungan.index', compact('kunjungan', 'poliklinikList'));
    }

    public function antrian(): View
    {
        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'dokter'])
            ->whereDate('tanggal', today())
            ->whereNotIn('status', ['selesai', 'batal'])
            ->orderBy('created_at')
            ->get();

        return view('pelayanan.kunjungan.antrian', compact('kunjungan'));
    }

    public function create(Request $request): View
    {
        $pasienId = $request->pasien_id;
        $pasien = $pasienId ? Pasien::find($pasienId) : null;

        $pasienList = Pasien::orderBy('nama')->get(['id', 'no_rm', 'nama', 'tanggal_lahir', 'jenis_kelamin']);
        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $dokterList = Nakes::where('jabatan', 'dokter')->where('is_active', true)->orderBy('nama')->get();
        $asuransiList = Asuransi::where('is_active', true)->orderBy('nama')->get();

        return view('pelayanan.kunjungan.create', compact('pasien', 'pasienList', 'poliklinikList', 'dokterList', 'asuransiList'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pasien_id' => ['required', 'exists:pasien,id'],
            'poliklinik_id' => ['required', 'exists:poliklinik,id'],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'asuransi_id' => ['nullable', 'exists:asuransi,id'],
            'tanggal' => ['required', 'date'],
            'jenis_pasien' => ['required', 'in:baru,lama'],
            'jenis_bayar' => ['required', 'in:umum,bpjs,asuransi'],
            'catatan' => ['nullable', 'string'],
        ]);

        $data['no_kunjungan'] = Kunjungan::generateNomor();
        $data['status'] = 'menunggu';

        $kunjungan = Kunjungan::create($data);

        return redirect()->route('pelayanan.kunjungan.show', $kunjungan)
            ->with('success', "Kunjungan berhasil didaftarkan: {$kunjungan->no_kunjungan}");
    }

    public function show(Kunjungan $kunjungan): View
    {
        $kunjungan->load([
            'pasien.asuransi', 'poliklinik', 'dokter', 'screening',
            'pemeriksaan.diagnosa', 'resep.resepObat.obat',
            'tindakanKunjungan.tindakan', 'farmasi', 'tagihan',
            'suratMedis', 'labHasil.laboratorium', 'rujukanInternal',
        ]);

        return view('pelayanan.kunjungan.show', compact('kunjungan'));
    }

    public function edit(Kunjungan $kunjungan): View
    {
        $poliklinikList = Poliklinik::where('is_active', true)->orderBy('nama')->get();
        $dokterList = Nakes::where('jabatan', 'dokter')->where('is_active', true)->orderBy('nama')->get();
        $asuransiList = Asuransi::where('is_active', true)->orderBy('nama')->get();

        return view('pelayanan.kunjungan.edit', compact('kunjungan', 'poliklinikList', 'dokterList', 'asuransiList'));
    }

    public function update(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $data = $request->validate([
            'poliklinik_id' => ['required', 'exists:poliklinik,id'],
            'dokter_id' => ['nullable', 'exists:nakes,id'],
            'asuransi_id' => ['nullable', 'exists:asuransi,id'],
            'tanggal' => ['required', 'date'],
            'jenis_pasien' => ['required', 'in:baru,lama'],
            'jenis_bayar' => ['required', 'in:umum,bpjs,asuransi'],
            'catatan' => ['nullable', 'string'],
        ]);

        $kunjungan->update($data);

        return redirect()->route('pelayanan.kunjungan.show', $kunjungan)->with('success', 'Kunjungan berhasil diperbarui.');
    }

    public function destroy(Kunjungan $kunjungan): RedirectResponse
    {
        if ($kunjungan->tagihan()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus kunjungan yang sudah memiliki tagihan.');
        }

        if ($kunjungan->resep()->exists() || $kunjungan->pemeriksaan()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus kunjungan yang sudah ada pelayanan medis.');
        }

        $kunjungan->delete();

        return redirect()->route('pelayanan.kunjungan.index')->with('success', 'Kunjungan berhasil dihapus.');
    }

    public function batal(Kunjungan $kunjungan): RedirectResponse
    {
        $kunjungan->update(['status' => 'batal']);

        return back()->with('success', 'Kunjungan berhasil dibatalkan.');
    }
}
