<?php

namespace App;

use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Screening;

class PatientAllergyHistory
{
    public function forPatient(Pasien $patient): array
    {
        $visit = $patient->kunjungan()->whereNotIn('status', ['batal', 'menunggu', 'screening'])->whereDate('tanggal', '<=', today())
            ->with(['screening', 'pemeriksaan.clinicalNoteVersions'])->orderByDesc('tanggal')->orderByDesc('id')->first();
        if ($visit) {
            $visit->setRelation('pasien', $patient);

            return $this->forVisit($visit);
        }

        return ['riwayat_alergi' => $this->normalize($patient->riwayat_alergi), 'alergi_jenis' => null, 'alergi_reaksi' => null];
    }

    public function __construct(private ClinicalNoteRecorder $notes) {}

    /** @return array{riwayat_alergi: ?string, alergi_jenis: ?string, alergi_reaksi: ?string} */
    public function recordedBeforeVisit(Kunjungan $visit): array
    {
        $previousContext = clone $visit;
        $previousContext->setRelation('screening', null);
        $previousContext->setRelation('pemeriksaan', null);

        return $this->forVisit($previousContext);
    }

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
            $this->syncStructuredAllergies($visit, $history);
        }
    }

    /**
     * Sync relational FHIR-ready AllergyIntolerance records from confirmed clinical allergy text.
     */
    private function syncStructuredAllergies(Kunjungan $visit, string $history): void
    {
        $safety = app(ClinicalSafetyService::class);
        if ($safety->isNegativeAllergyText($history)) {
            Models\PatientAllergy::query()
                ->where('pasien_id', $visit->pasien_id)
                ->whereNull('allergen_code')
                ->where('clinical_status', 'active')
                ->update(['clinical_status' => 'inactive', 'verification_status' => 'refuted']);

            return;
        }

        $category = match (mb_strtolower((string) ($visit->screening?->alergi_jenis ?? ''))) {
            'makanan' => 'food',
            'udara', 'udara/lainnya', 'lingkungan' => 'environment',
            default => 'medication',
        };
        $reaction = $this->normalize($visit->screening?->alergi_reaksi);
        $criticality = match (mb_strtolower((string) ($visit->screening?->alergi_keparahan ?? ''))) {
            'berat', 'anafilaksis', 'tinggi', 'high' => 'high',
            'sedang', 'ringan', 'rendah', 'low' => 'low',
            default => 'unable-to-assess',
        };
        $terms = collect(preg_split('/[,;\n]+/u', $history, -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->map(fn (string $item): string => mb_substr(trim($item), 0, 255))
            ->filter(fn (string $item): bool => $item !== '' && ! $safety->isNegativeAllergyText($item))
            ->unique()
            ->take(10)
            ->values();

        if ($terms->isNotEmpty()) {
            Models\PatientAllergy::query()
                ->where('pasien_id', $visit->pasien_id)
                ->whereNull('allergen_code')
                ->where('clinical_status', 'active')
                ->whereNotIn('allergen_display', $terms->all())
                ->update(['clinical_status' => 'inactive', 'verification_status' => 'refuted']);
        }

        foreach ($terms as $term) {
            Models\PatientAllergy::query()->updateOrCreate(
                [
                    'pasien_id' => $visit->pasien_id,
                    'allergen_display' => $term,
                ],
                [
                    'kunjungan_id' => $visit->id,
                    'recorded_by_user_id' => auth()->id(),
                    'category' => $category,
                    'criticality' => $criticality,
                    'reaction_text' => $reaction,
                    'clinical_status' => 'active',
                    'verification_status' => 'confirmed',
                ]
            );
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
        return ClinicalAnswer::normalize($history);
    }

    private function screening(?Screening $screening): ?string
    {
        return $this->normalize($screening?->riwayat_alergi)
            ?? $this->normalize(implode(' - ', array_filter([
                $this->normalize($screening?->alergi_jenis), $this->normalize($screening?->alergi_reaksi),
            ])));
    }
}
