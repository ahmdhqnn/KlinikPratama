<?php

namespace App\Http\Controllers\Pelayanan;

use App\Http\Controllers\Controller;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Screening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        abort_if(in_array($kunjungan->status, ['selesai', 'batal'], true), 422, 'Kunjungan sudah tidak dapat diperiksa.');

        $data = $request->validate([
            'petugas_id' => ['nullable', 'exists:nakes,id'],
            'keluhan' => ['nullable', 'string'],
            'td_sistole' => ['nullable', 'integer', 'min:0', 'max:300'],
            'td_diastole' => ['nullable', 'integer', 'min:0', 'max:200'],
            'nadi' => ['nullable', 'integer', 'min:0', 'max:300'],
            'suhu' => ['nullable', 'numeric', 'min:30', 'max:45'],
            'berat_badan' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'tinggi_badan' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'lingkar_perut' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'spo2' => ['nullable', 'integer', 'min:0', 'max:100'],
            'respirasi' => ['nullable', 'integer', 'min:0', 'max:100'],
            'fungsi_penciuman' => ['nullable', 'in:normal,menurun,tidak_ada'],
            'tingkat_kesadaran' => ['nullable', 'in:composmentis,apatis,somnolen,sopor,koma'],
            'riwayat_penyakit' => ['nullable', 'string'],
            'penyakit_nama' => ['nullable', 'string', 'max:255'],
            'penyakit_keterangan' => ['nullable', 'string'],
            'riwayat_alergi' => ['nullable', 'string'],
            'alergi_jenis' => ['nullable', 'string', 'max:255'],
            'alergi_reaksi' => ['nullable', 'string'],
            'risiko_jatuh' => ['nullable', 'string'],
            'risiko_nyeri' => ['nullable', 'string'],
            'skrining_gizi' => ['nullable', 'string'],
            'pemeriksaan_fisik' => ['nullable', 'string'],
            'nyeri_dada' => ['required', 'in:ya,tidak'],
            'kondisi_psikiatri' => ['required', 'in:normal,terganggu'],
            'nadi_teraba' => ['required', 'in:teraba,tidak_teraba'],
            'kejang' => ['required', 'in:ya,tidak'],
            'pola_pernapasan' => ['required', 'in:normal,tidak_normal'],
            'kesadaran' => ['required', 'in:sadar,menurun,tidak_sadar'],
            'risiko_jatuh_visual' => ['required', 'in:rendah,sedang,tinggi'],
        ]);

        $data['kunjungan_id'] = $kunjungan->id;
        [$data['kesimpulan_triase'], $data['prioritas_layanan']] = $this->determineTriage($data);

        DB::transaction(function () use ($data, $kunjungan): void {
            Screening::updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);

            if (! empty($data['riwayat_alergi']) && $kunjungan->pasien) {
                $kunjungan->pasien->update(['riwayat_alergi' => $data['riwayat_alergi']]);
            }

            $kunjungan->update(['status' => 'pemeriksaan']);
        });

        return redirect()->route('pelayanan.kunjungan.show', $kunjungan)
            ->with('success', 'Screening berhasil disimpan.');
    }

    public function update(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        return $this->store($request, $kunjungan);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: string, 1: string}
     */
    private function determineTriage(array $data): array
    {
        $emergency = $data['nyeri_dada'] === 'ya'
            || $data['kejang'] === 'ya'
            || $data['nadi_teraba'] === 'tidak_teraba'
            || $data['kesadaran'] === 'tidak_sadar'
            || $data['pola_pernapasan'] === 'tidak_normal';

        if ($emergency) {
            return ['merah', 'darurat'];
        }

        $priority = $data['kondisi_psikiatri'] === 'terganggu'
            || $data['kesadaran'] === 'menurun'
            || $data['risiko_jatuh_visual'] !== 'rendah';

        return $priority ? ['kuning', 'segera'] : ['hijau', 'normal'];
    }
}
