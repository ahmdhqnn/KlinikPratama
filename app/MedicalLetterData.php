<?php

namespace App;

use App\Models\CorrespondenceTemplate;
use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\ResepObat;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MedicalLetterData
{
    public const TYPES = ['sakit', 'sehat', 'rujukan', 'laboratorium', 'rontgen_gigi', 'ringkasan', 'lainnya'];

    /** @return array<string, mixed> */
    public function validate(Request $request): array
    {
        $type = $request->input('jenis');
        $templateContent = $type === 'lainnya' && filled($request->input('surat_template_id'))
            ? CorrespondenceTemplate::query()->whereKey($request->input('surat_template_id'))->where('is_active', true)->where('jenis', 'lainnya')->value('isi')
            : null;
        $request->validate([
            'konten' => [Rule::requiredIf($type === 'lainnya' && ! filled($templateContent)), 'nullable', 'string', 'max:10000'],
        ]);
        $data = $request->validate([
            'details' => ['nullable', 'array:rest_start,rest_end,purpose,conclusion,recipient,recipient_address,requested_tests,xray_types,teeth,result_delivery'],
            'details.rest_start' => [Rule::excludeIf($type !== 'sakit'), Rule::requiredIf($type === 'sakit'), 'nullable', 'date_format:Y-m-d'],
            'details.rest_end' => [Rule::excludeIf($type !== 'sakit'), Rule::requiredIf($type === 'sakit'), 'nullable', 'date_format:Y-m-d', 'after_or_equal:details.rest_start'],
            'details.purpose' => [Rule::excludeIf($type !== 'sehat'), Rule::requiredIf($type === 'sehat'), 'nullable', 'string', 'max:500'],
            'details.conclusion' => [Rule::excludeIf($type !== 'sehat'), Rule::requiredIf($type === 'sehat'), 'nullable', 'string', 'max:2000'],
            'details.recipient' => [Rule::excludeIf($type !== 'rujukan'), Rule::requiredIf($type === 'rujukan'), 'nullable', 'string', 'max:300'],
            'details.recipient_address' => [Rule::excludeIf($type !== 'rujukan'), 'nullable', 'string', 'max:1000'],
            'details.requested_tests' => [Rule::excludeIf($type !== 'laboratorium'), Rule::requiredIf($type === 'laboratorium'), 'nullable', 'string', 'max:4000'],
            'details.xray_types' => [Rule::excludeIf($type !== 'rontgen_gigi'), Rule::requiredIf($type === 'rontgen_gigi'), 'nullable', 'array', 'min:1', 'max:5'],
            'details.xray_types.*' => [Rule::excludeIf($type !== 'rontgen_gigi'), 'required', Rule::in(['Dental', 'Panoramic', 'Cephalometric', 'Occlusal', 'TMJ']), 'distinct'],
            'details.teeth' => [Rule::excludeIf($type !== 'rontgen_gigi'), 'nullable', 'array', 'max:52'],
            'details.teeth.*' => [Rule::excludeIf($type !== 'rontgen_gigi'), 'required', 'integer', Rule::in($this->teeth()), 'distinct'],
            'details.result_delivery' => [Rule::excludeIf(! in_array($type, ['laboratorium', 'rontgen_gigi'], true)), Rule::requiredIf(in_array($type, ['laboratorium', 'rontgen_gigi'], true)), 'nullable', Rule::in(['pasien', 'dokter'])],
        ], [
            'details.xray_types.required' => 'Pilih minimal satu jenis foto rontgen gigi.',
            'details.xray_types.min' => 'Pilih minimal satu jenis foto rontgen gigi.',
            'details.rest_end.after_or_equal' => 'Tanggal akhir istirahat harus sama atau setelah tanggal mulai.',
        ]);
        $fields = match ($type) {
            'sakit' => ['rest_start', 'rest_end'],
            'sehat' => ['purpose', 'conclusion'],
            'rujukan' => ['recipient', 'recipient_address'],
            'laboratorium' => ['requested_tests', 'result_delivery'],
            'rontgen_gigi' => ['xray_types', 'teeth', 'result_delivery'],
            default => [],
        };

        return array_intersect_key($data['details'] ?? [], array_flip($fields));
    }

    /** @return list<int> */
    private function teeth(): array
    {
        $teeth = [];
        foreach (range(1, 8) as $quadrant) {
            foreach (range(1, $quadrant <= 4 ? 8 : 5) as $position) {
                $teeth[] = $quadrant * 10 + $position;
            }
        }

        return $teeth;
    }

    /** @return array<string, mixed> */
    public function snapshot(Kunjungan $visit, ?Nakes $doctor, CarbonInterface $date): array
    {
        $visit->loadMissing(['pasien', 'pemeriksaan.diagnosa', 'screening', 'resep.resepObat.obat', 'tindakanKunjungan.tindakan']);
        $doctor?->loadMissing('user');
        $patient = $visit->pasien;
        $exam = $visit->pemeriksaan;
        $effective = app(EffectiveClinicalNote::class)->resolve($exam);
        if ($effective !== null) {
            $exam = clone $exam;
            $exam->forceFill(array_intersect_key($effective['payload'], array_flip([
                'anamnesis', 'pemeriksaan_fisik', 'riwayat_alergi', 'non_farmakologis', 'edukasi', 'catatan',
            ])));
        }
        $screening = $visit->screening;
        $diagnoses = $effective !== null
            ? collect($effective['payload']['diagnoses'] ?? [])->sortBy(fn (array $item): int => ($item['type'] ?? null) === 'utama' ? 0 : 1)
                ->map(fn (array $item): string => ($item['name'] ?? '').(! empty($item['code']) ? ' ('.$item['code'].')' : ''))->values()->all()
            : ($exam?->diagnosa->sortBy(fn (Diagnosa $diagnosis): int => $diagnosis->jenis === 'utama' ? 0 : 1)->map(fn (Diagnosa $diagnosis): string => $diagnosis->nama_diagnosa.($diagnosis->kode_icd10 ? ' ('.$diagnosis->kode_icd10.')' : ''))->values()->all() ?? []);
        $therapies = $effective !== null
            ? collect($effective['payload']['prescriptions'] ?? [])->map(fn (array $item): string => trim(($item['name'] ?? '').' - '.($item['quantity'] ?? '').' '.($item['unit'] ?? '').'; '.($item['instructions'] ?? '')))->all()
            : ($visit->resep?->resepObat->map(fn (ResepObat $item): string => trim(($item->nama_obat ?? $item->obat?->nama).' - '.$item->jumlah.' '.$item->satuan.'; '.$item->aturan_pakai))->all() ?? []);
        if ($effective !== null) {
            $executions = $visit->tindakanKunjungan->keyBy('id');
            foreach ($effective['payload']['treatments'] ?? [] as $procedure) {
                $execution = $executions->get($procedure['id'] ?? null);
                $completed = ($procedure['execution_status'] ?? null) === 'completed';
                // Nursing execution follows the signed plan; only realization status comes
                // from that matching transaction, never a mutable plan label/code.
                if (($procedure['execution_role'] ?? null) === 'perawat' && $execution?->execution_role === 'perawat') {
                    $completed = $execution->execution_status === 'completed';
                }
                $therapies[] = ($procedure['name'] ?? 'Tindakan').(! $completed ? ' (direncanakan)' : '');
            }
        } else {
            foreach ($visit->tindakanKunjungan as $procedure) {
                $therapies[] = ($procedure->nama_tindakan ?? $procedure->nama_tindakan_manual ?? $procedure->tindakan?->nama ?? 'Tindakan').($procedure->execution_status !== 'completed' ? ' (direncanakan)' : '');
            }
        }
        if (filled($exam?->non_farmakologis)) {
            $therapies[] = $exam->non_farmakologis;
        }

        return [
            'facility' => app(ClinicDocumentProfile::class)->data(),
            'clinicalVersion' => $effective !== null ? ['id' => $effective['id'], 'version' => $effective['version'], 'hash' => $effective['hash']] : null,
            'patient' => [
                'name' => $patient->nama, 'medicalRecordNumber' => $patient->no_rm,
                'gender' => $patient->jenis_kelamin,
                'age' => $patient->tanggal_lahir ? (int) $patient->tanggal_lahir->diffInYears($date, false) : null,
                'birthDate' => $patient->tanggal_lahir?->format('d/m/Y'),
                'address' => $patient->alamat, 'phone' => $patient->telepon,
                'nip' => $patient->nip, 'department' => $visit->unit_kerja ?? $patient->unit_kerja,
                'allergy' => $effective !== null ? ($effective['payload']['riwayat_alergi'] ?? null) : ($exam?->riwayat_alergi ?? $screening?->riwayat_alergi ?? $patient->riwayat_alergi),
            ],
            'doctor' => [
                'name' => $doctor?->nama ?? 'Dokter klinik', 'sip' => $doctor?->no_sip,
                'str' => $doctor?->no_str, 'signatureDataUrl' => app(CorrespondenceService::class)->signatureDataUrl($doctor?->user),
            ],
            'clinical' => [
                'anamnesis' => $exam?->anamnesis,
                'diagnoses' => $diagnoses,
                'physicalExamination' => $exam?->pemeriksaan_fisik,
                'height' => $screening?->tinggi_badan, 'weight' => $screening?->berat_badan,
                'bloodPressure' => $screening?->td_sistole !== null && $screening?->td_diastole !== null ? $screening->td_sistole.'/'.$screening->td_diastole : null,
                'therapy' => implode("\n", $therapies), 'education' => $exam?->edukasi,
                'notes' => $exam?->catatan, 'date' => $visit->tanggal?->format('d/m/Y'),
            ],
        ];
    }
}
