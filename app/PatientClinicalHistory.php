<?php

namespace App;

use App\Models\Kunjungan;

class PatientClinicalHistory
{
    public function __construct(private ClinicalNoteRecorder $notes) {}

    /** @return array{riwayat_penyakit: ?string, riwayat_penyakit_keluarga: ?string, riwayat_obat: ?string} */
    public function forVisit(Kunjungan $visit): array
    {
        $visit->loadMissing(['screening', 'pemeriksaan.clinicalNoteVersions']);
        $history = $this->values($visit);
        if (! in_array(null, $history, true)) {
            return $history;
        }
        $previousVisits = Kunjungan::where('pasien_id', $visit->pasien_id)
            ->whereNotIn('status', ['batal', 'menunggu', 'screening'])
            ->where(fn ($query) => $query->whereDate('tanggal', '<', $visit->tanggal)
                ->orWhere(fn ($sameDay) => $sameDay->whereDate('tanggal', $visit->tanggal)->where('id', '<', $visit->id)))
            ->whereDate('tanggal', '<=', today())
            ->where(fn ($query) => $query->whereHas('screening')->orWhereHas('pemeriksaan'))
            ->with(['screening', 'pemeriksaan.clinicalNoteVersions'])
            ->orderByDesc('tanggal')->orderByDesc('id');

        foreach ($previousVisits->lazy(50) as $previous) {
            foreach ($this->values($previous, previous: true) as $field => $value) {
                $history[$field] ??= $value;
            }
            if (! in_array(null, $history, true)) {
                break;
            }
        }

        return $history;
    }

    /** @return array{riwayat_penyakit: ?string, riwayat_penyakit_keluarga: ?string, riwayat_obat: ?string} */
    private function values(Kunjungan $visit, bool $previous = false): array
    {
        $exam = $visit->pemeriksaan;
        $screening = $visit->screening;
        if ($previous && $screening?->started_at && ! $screening->submitted_at) {
            $screening = null;
        }
        $payload = [];
        if ($exam?->signed_at) {
            $payload = $this->notes->verify($exam) ? ($exam->clinicalNoteVersions->sortBy('version')->last()?->payload ?? []) : [];
        } elseif ($exam && (! $previous || $exam->status === 'selesai')) {
            $payload = $exam->only(['riwayat_penyakit_dahulu', 'riwayat_penyakit_keluarga']);
        }

        return [
            'riwayat_penyakit' => ClinicalAnswer::normalize($payload['riwayat_penyakit_dahulu'] ?? null)
                ?? ClinicalAnswer::normalize($screening?->riwayat_penyakit),
            'riwayat_penyakit_keluarga' => ClinicalAnswer::normalize($payload['riwayat_penyakit_keluarga'] ?? null)
                ?? ClinicalAnswer::normalize($screening?->riwayat_penyakit_keluarga),
            'riwayat_obat' => ClinicalAnswer::normalize($screening?->riwayat_obat),
        ];
    }
}
