<?php

namespace App\Http\Controllers\Pelayanan;

use App\Http\Controllers\Controller;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Poliklinik;
use App\Models\Screening;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScreeningController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
        ]);

        $kunjungan = Kunjungan::with(['pasien', 'poliklinik', 'screening'])
            ->when($filters['search'] ?? null, fn ($query, string $search) => $query->whereHas('pasien', fn ($patientQuery) => $patientQuery
                ->where(fn ($nestedQuery) => $nestedQuery
                    ->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_rm', 'like', "%{$search}%"))))
            ->when($filters['poliklinik_id'] ?? null, fn ($query, int $clinicId) => $query->where('poliklinik_id', $clinicId))
            ->whereDate('tanggal', $filters['tanggal'] ?? today())
            ->whereIn('status', ['menunggu', 'screening'])
            ->orderBy('created_at')
            ->get();

        $clinics = Poliklinik::where('is_active', true)->orderBy('nama')->get(['id', 'nama']);

        return Inertia::render('pelayanan/screening/index', [
            'filters' => [
                'search' => $filters['search'] ?? '',
                'tanggal' => $filters['tanggal'] ?? today()->toDateString(),
                'poliklinikId' => isset($filters['poliklinik_id']) ? (int) $filters['poliklinik_id'] : '',
            ],
            'clinics' => $clinics->map(fn (Poliklinik $clinic): array => [
                'id' => $clinic->id,
                'name' => $clinic->nama,
            ])->values(),
            'visits' => $kunjungan->map(fn (Kunjungan $visit): array => [
                'id' => $visit->id,
                'number' => $visit->no_kunjungan,
                'patient' => $visit->pasien?->nama ?? '—',
                'medicalRecordNumber' => $visit->pasien?->no_rm ?? '—',
                'clinic' => $visit->poliklinik?->nama ?? '—',
                'hasScreening' => $visit->screening !== null,
                'screeningUrl' => route('pelayanan.screening.show', $visit),
            ])->values(),
        ]);
    }

    public function show(Kunjungan $kunjungan): Response
    {
        abort_unless(in_array($kunjungan->status, ['menunggu', 'screening'], true), 422, 'Skrining tidak dapat diubah setelah pelayanan dimulai.');

        $kunjungan->load(['pasien.asuransi', 'poliklinik', 'dokter', 'screening']);
        $petugasList = Nakes::whereIn('jabatan', ['perawat', 'bidan'])->where('is_active', true)->orderBy('nama')->get();

        $screeningFields = [
            'petugas_id', 'keluhan', 'td_sistole', 'td_diastole', 'nadi', 'suhu', 'berat_badan', 'tinggi_badan',
            'lingkar_perut', 'spo2', 'respirasi', 'riwayat_penyakit', 'riwayat_alergi', 'risiko_jatuh',
            'risiko_nyeri', 'skrining_gizi', 'alergi_jenis', 'alergi_reaksi', 'penyakit_nama',
            'penyakit_keterangan', 'nyeri_dada', 'kondisi_psikiatri', 'nadi_teraba', 'kejang',
            'pola_pernapasan', 'kesadaran', 'risiko_jatuh_visual',
        ];

        return Inertia::render('pelayanan/screening/show', [
            'visit' => [
                'id' => $kunjungan->id,
                'number' => $kunjungan->no_kunjungan,
                'status' => $kunjungan->status,
                'patient' => [
                    'name' => $kunjungan->pasien?->nama ?? 'Pasien',
                    'medicalRecordNumber' => $kunjungan->pasien?->no_rm ?? '—',
                    'gender' => $kunjungan->pasien?->jenis_kelamin,
                    'age' => $kunjungan->pasien?->umur,
                    'bloodType' => $kunjungan->pasien?->golongan_darah,
                    'allergyHistory' => $kunjungan->pasien?->riwayat_alergi,
                ],
                'clinic' => $kunjungan->poliklinik?->nama ?? '—',
                'doctor' => $kunjungan->dokter?->nama ?? 'Belum ditentukan',
                'screening' => array_combine(
                    $screeningFields,
                    array_map(fn (string $field): mixed => $kunjungan->screening?->getAttribute($field), $screeningFields),
                ),
                'triage' => $kunjungan->screening?->kesimpulan_triase,
                'priority' => $kunjungan->screening?->prioritas_layanan,
            ],
            'staff' => $petugasList->map(fn (Nakes $staff): array => [
                'id' => $staff->id,
                'name' => $staff->nama,
                'position' => ucfirst($staff->jabatan),
            ])->values(),
        ]);
    }

    public function store(Request $request, Kunjungan $kunjungan): RedirectResponse
    {
        abort_unless(in_array($kunjungan->status, ['menunggu', 'screening'], true), 422, 'Skrining tidak dapat diubah setelah pelayanan dimulai.');

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
            $lockedVisit = Kunjungan::whereKey($kunjungan->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($lockedVisit->status, ['menunggu', 'screening'], true), 422, 'Skrining tidak dapat diubah setelah pelayanan dimulai.');

            Screening::updateOrCreate(['kunjungan_id' => $kunjungan->id], $data);

            if (! empty($data['riwayat_alergi']) && $lockedVisit->pasien) {
                $lockedVisit->pasien->update(['riwayat_alergi' => $data['riwayat_alergi']]);
            }

            $lockedVisit->update(['status' => 'pemeriksaan']);
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
