<?php

namespace App;

use App\Models\ClinicalNoteVersion;
use App\Models\Kunjungan;
use App\Models\Pemeriksaan;

class ClinicalNoteRecorder
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(Kunjungan $visit, Pemeriksaan $examination): array
    {
        $examination->loadMissing('diagnosa');
        $visit->loadMissing(['tindakanKunjungan.tindakan', 'resep.resepObat', 'rujukanInternal', 'odontogramFindings']);

        return [
            'anamnesis' => $examination->anamnesis,
            'pemeriksaan_fisik' => $examination->pemeriksaan_fisik,
            'catatan' => $examination->catatan,
            'edukasi' => $examination->edukasi,
            'kontrol_berikutnya' => $examination->kontrol_berikutnya?->toDateString(),
            'diagnoses' => $examination->diagnosa->sortBy('id')->map(fn ($diagnosis): array => [
                'code' => $diagnosis->kode_icd10,
                'name' => $diagnosis->nama_diagnosa,
                'type' => $diagnosis->jenis,
            ])->values()->all(),
            'treatments' => $visit->tindakanKunjungan->sortBy('id')->map(fn ($treatment): array => [
                'name' => $treatment->tindakan?->nama,
                'quantity' => $treatment->jumlah,
                'tooth_fdi' => $treatment->tooth_fdi,
            ])->values()->all(),
            'prescriptions' => $visit->resep?->resepObat->sortBy('id')->map(fn ($item): array => [
                'name' => $item->nama_obat,
                'quantity' => $item->jumlah,
                'unit' => $item->satuan,
                'instructions' => $item->aturan_pakai,
                'external' => $item->is_resep_luar,
            ])->values()->all() ?? [],
            'referrals' => $visit->rujukanInternal->sortBy('id')->map(fn ($referral): array => [
                'from_clinic_id' => $referral->dari_poli_id,
                'to_clinic_id' => $referral->ke_poli_id,
                'notes' => $referral->catatan,
            ])->values()->all(),
            'odontogram' => $visit->odontogramFindings->sortBy(fn ($finding): string => $finding->tooth_fdi.$finding->surface)
                ->map(fn ($finding): array => [
                    'tooth_fdi' => $finding->tooth_fdi,
                    'surface' => $finding->surface,
                    'finding_code' => $finding->finding_code,
                    'notes' => $finding->notes,
                ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordFinal(Pemeriksaan $examination, array $payload, int $actorId): ClinicalNoteVersion
    {
        return $this->record($examination, 'finalized', $payload, $actorId);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordAddendum(Pemeriksaan $examination, array $payload, int $actorId, string $reason): ClinicalNoteVersion
    {
        return $this->record($examination, 'addendum', $payload, $actorId, $reason);
    }

    public function verify(Pemeriksaan $examination): bool
    {
        if (! $examination->relationLoaded('clinicalNoteVersions')) {
            $examination->refresh();
        }

        $versions = $examination->relationLoaded('clinicalNoteVersions')
            ? $examination->clinicalNoteVersions->sortBy('version')
            : ClinicalNoteVersion::where('pemeriksaan_id', $examination->id)->orderBy('version')->get();
        $previousHash = null;
        $expectedVersion = 1;

        foreach ($versions as $version) {
            if ($version->version !== $expectedVersion
                || $version->previous_hash !== $previousHash
                || $version->content_hash !== $this->digest(
                    $examination->id,
                    $version->version,
                    $version->kind,
                    $version->actor_id,
                    $version->reason,
                    $version->payload,
                    $previousHash,
                    $version->recorded_at->toISOString()
                )) {
                return false;
            }

            $previousHash = $version->content_hash;
            $expectedVersion++;
        }

        return $versions->isNotEmpty()
            && $versions->first()->kind === 'finalized'
            && $versions->first()->actor_id === $examination->signed_by_user_id
            && $previousHash === $examination->latest_note_hash;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function record(Pemeriksaan $examination, string $kind, array $payload, int $actorId, ?string $reason = null): ClinicalNoteVersion
    {
        $previous = ClinicalNoteVersion::where('pemeriksaan_id', $examination->id)
            ->orderByDesc('version')->first();
        $version = ($previous?->version ?? 0) + 1;
        $recordedAt = now();
        $previousHash = $previous?->content_hash;
        $contentHash = $this->digest(
            $examination->id, $version, $kind, $actorId, $reason,
            $payload, $previousHash, $recordedAt->toISOString()
        );

        $record = $examination->clinicalNoteVersions()->create([
            'version' => $version,
            'kind' => $kind,
            'actor_id' => $actorId,
            'reason' => $reason,
            'payload' => $payload,
            'previous_hash' => $previousHash,
            'content_hash' => $contentHash,
            'recorded_at' => $recordedAt,
        ]);

        $examination->update(['latest_note_hash' => $contentHash]);

        return $record;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function digest(int $examinationId, int $version, string $kind, int $actorId, ?string $reason, array $payload, ?string $previousHash, string $recordedAt): string
    {
        return hash('sha256', json_encode([
            $examinationId, $version, $kind, $actorId, $reason,
            $this->canonicalize($payload), $previousHash, $recordedAt,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private function canonicalize(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonicalize($item);
            }
        }

        return $value;
    }
}
