<?php

namespace App;

use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Screening;

class PatientAllergyHistory
{
    public function __construct(private ClinicalNoteRecorder $notes) {}

    public function examination(?Pemeriksaan $examination): ?string
    {
        if (! $examination) {
            return null;
        }

        if ($examination->signed_at) {
            $examination->loadMissing('clinicalNoteVersions');

            return $this->notes->verify($examination)
                ? $this->normalize($examination->clinicalNoteVersions->sortBy('version')->last()?->payload['riwayat_alergi'] ?? null)
                : null;
        }

        return $this->normalize($examination->riwayat_alergi);
    }

    /** @return array{riwayat_alergi: ?string, alergi_jenis: ?string, alergi_reaksi: ?string} */
    public function forVisit(Kunjungan $visit): array
    {
        $visit->loadMissing(['pasien', 'screening', 'pemeriksaan']);
        $history = $this->examination($visit->pemeriksaan) ?? $this->screening($visit->screening);
        if ($history !== null) {
            return $this->values($history, $visit);
        }

        $previousVisits = $visit->pasien->kunjungan()
            ->whereNotIn('status', ['batal', 'menunggu', 'screening'])
            ->where(fn ($query) => $query->whereDate('tanggal', '<', $visit->tanggal)
                ->orWhere(fn ($sameDay) => $sameDay->whereDate('tanggal', $visit->tanggal)->where('id', '<', $visit->id)))
            ->whereDate('tanggal', '<=', today())
            ->where(fn ($query) => $query->whereHas('screening', fn ($screenings) => $screenings->where(fn ($allergies) => $allergies
                ->whereNotNull('riwayat_alergi')->orWhereNotNull('alergi_jenis')->orWhereNotNull('alergi_reaksi')))
                ->orWhereHas('pemeriksaan', fn ($examinations) => $examinations->whereNotNull('riwayat_alergi')->orWhereNotNull('signed_at')))
            ->with(['screening', 'pemeriksaan.clinicalNoteVersions'])
            ->orderByDesc('tanggal')->orderByDesc('id');

        foreach ($previousVisits->lazy(50) as $previousVisit) {
            $history = $this->examination($previousVisit->pemeriksaan)
                ?? $this->screening($previousVisit->screening);
            if ($history !== null) {
                return $this->values($history, $previousVisit);
            }
        }

        return ['riwayat_alergi' => $this->normalize($visit->pasien->riwayat_alergi), 'alergi_jenis' => null, 'alergi_reaksi' => null];
    }

    public function remember(Kunjungan $visit, ?string $history): void
    {
        $history = $this->normalize($history);
        if ($history !== null) {
            Pasien::whereKey($visit->pasien_id)->lockForUpdate()->firstOrFail()->update(['riwayat_alergi' => $history]);
        }
    }

    /** @return array{riwayat_alergi: string, alergi_jenis: ?string, alergi_reaksi: ?string} */
    private function values(string $history, Kunjungan $visit): array
    {
        $matchesScreening = $history === $this->screening($visit->screening);

        return [
            'riwayat_alergi' => $history,
            'alergi_jenis' => $matchesScreening ? $visit->screening?->alergi_jenis : null,
            'alergi_reaksi' => $matchesScreening ? $visit->screening?->alergi_reaksi : null,
        ];
    }

    private function normalize(?string $history): ?string
    {
        $history = filled($history) ? trim($history) : null;

        return in_array(mb_strtolower($history ?? ''), ['', '-', 'belum dicatat', 'belum diketahui'], true) ? null : $history;
    }

    private function screening(?Screening $screening): ?string
    {
        return $this->normalize($screening?->riwayat_alergi)
            ?? $this->normalize(implode(' - ', array_filter([
                $this->normalize($screening?->alergi_jenis), $this->normalize($screening?->alergi_reaksi),
            ])));
    }
}
