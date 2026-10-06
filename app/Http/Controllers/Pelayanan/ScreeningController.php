<?php

namespace App\Http\Controllers\Pelayanan;

use App\Http\Controllers\Controller;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Screening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScreeningController extends Controller
{
    public function index(Request $request): View
    {
        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'screening'])
            ->when($request->search, fn ($q, $s) => $q->whereHas('pasien', fn ($pq) => $pq->where(fn ($sub) => $sub->where('nama', 'like', "%$s%")->orWhere('no_rm', 'like', "%$s%"))))
            ->when($request->poliklinik_id, fn ($q, $p) => $q->where('poliklinik_id', $p))
            ->whereDate('tanggal', $request->tanggal ?? today())
            ->whereIn('status', ['menunggu', 'screening'])
            ->orderBy('created_at')
            ->get();

        return view('pelayanan.screening.index', compact('kunjungan'));
    }

    public function show(Kunjungan $kunjungan): View
    {
        $kunjungan->load(['pasien.asuransi', 'poliklinik', 'dokter', 'screening']);
        $petugasList = Nakes::whereIn('jabatan', ['perawat', 'bidan'])->where('is_active', true)->orderBy('nama')->get();

        return view('pelayanan.screening.show', compact('kunjungan', 'petugasList'));
    }

    public function store(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        $data = $request->validate([
            'petugas_id' => ['nullable', 'exists:nakes,id'],
            'keluhan' => ['nullable', 'string'],
            'td_sistole' => ['nullable', 'integer', 'min:0', 'max:300'],
            'td_diastole' => ['nullable', 'integer', 'min:0', 'max:200'],
            'nadi' => ['nullable', 'integer', 'min:0', 'max:300'],
            'suhu' => ['nullable', 'numeric', 'min:30', 'max:45'],
            'berat_badan' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'tinggi_badan' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'spo2' => ['nullable', 'integer', 'min:0', 'max:100'],
            'respirasi' => ['nullable', 'integer', 'min:0', 'max:100'],
            'riwayat_penyakit' => ['nullable', 'string'],
            'riwayat_alergi' => ['nullable', 'string'],
            'risiko_jatuh' => ['nullable', 'string'],
            'risiko_nyeri' => ['nullable', 'string'],
            'skrining_gizi' => ['nullable', 'string'],
            'pemeriksaan_fisik' => ['nullable', 'string'],
        ]);

        $data['kunjungan_id'] = $kunjungan->id;

        Screening::updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);

        // Sync riwayat_alergi to patient record if provided
        if (! empty($data['riwayat_alergi']) && $kunjungan->pasien) {
            $kunjungan->pasien->update(['riwayat_alergi' => $data['riwayat_alergi']]);
        }

        // Update kunjungan status
        $kunjungan->update(['status' => 'pemeriksaan']);

        return redirect()->route('pelayanan.kunjungan.show', $kunjungan)
            ->with('success', 'Screening berhasil disimpan.');
    }

    public function update(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        return $this->store($request, $kunjungan);
    }
}
