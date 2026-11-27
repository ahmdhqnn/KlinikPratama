<?php

namespace App;

use App\Models\ClinicalNoteVersion;
use App\Models\Kunjungan;
use App\Models\Pemeriksaan;
use Illuminate\Support\Facades\DB;

class ClinicalNoteRecorder
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(Kunjungan $visit, Pemeriksaan $examination): array
    {
        $examination->loadMissing('diagnosa');
        $visit->loadMissing(['tindakanKunjungan.tindakan', 'tindakanKunjungan.informedConsents', 'resep.resepObat', 'rujukanInternal', 'odontogramFindings']);

        return [
            'supporting_exams' => $examination->supporting_exams,
            'review_of_systems' => $examination->review_of_systems,
            'clinical_assessment' => $examination->clinical_assessment,
            'riwayat_sosial' => $examination->riwayat_sosial,
            'kondisi_akhir' => $examination->kondisi_akhir,
            'disposisi' => $examination->disposisi,
            'riwayat_obat' => ClinicalAnswer::normalize($examination->riwayat_obat),
            'riwayat_perawatan_gigi' => ClinicalAnswer::normalize($examination->riwayat_perawatan_gigi),
            'kebiasaan_gigi' => ClinicalAnswer::normalize($examination->kebiasaan_gigi),
            'anamnesis' => ClinicalAnswer::normalize($examination->anamnesis),
            'riwayat_penyakit_sekarang' => ClinicalAnswer::normalize($examination->riwayat_penyakit_sekarang),
            'riwayat_penyakit_dahulu' => ClinicalAnswer::normalize($examination->riwayat_penyakit_dahulu),
            'riwayat_penyakit_keluarga' => ClinicalAnswer::normalize($examination->riwayat_penyakit_keluarga),
            'riwayat_alergi' => ClinicalAnswer::normalize($examination->riwayat_alergi),
            'pemeriksaan_fisik' => ClinicalAnswer::normalize($examination->pemeriksaan_fisik),
            'td_sistole_ulang' => $examination->td_sistole_ulang,
            'td_diastole_ulang' => $examination->td_diastole_ulang,
            'nadi_ulang' => $examination->nadi_ulang,
            'suhu_ulang' => $examination->suhu_ulang,
            'ringkasan_odontogram' => ClinicalAnswer::normalize($examination->ringkasan_odontogram),
            'pemeriksaan_fisik_terstruktur' => $examination->pemeriksaan_fisik_terstruktur ?? [],
            'body_map' => $examination->body_map,
            'pemeriksaan_ekstraoral' => ClinicalAnswer::normalize($examination->pemeriksaan_ekstraoral),
            'oral_hygiene_index' => $examination->oral_hygiene_index,
            'diagnosis_banding' => ClinicalAnswer::normalize($examination->diagnosis_banding),
            'coding' => [
                'status' => $examination->coding_status,
                'unresolved_reason' => $examination->coding_unresolved_reason,
                'review_due_at' => $examination->coding_review_due_at?->toIso8601String(),
            ],
            'catatan' => ClinicalAnswer::normalize($examination->catatan),
            'edukasi' => ClinicalAnswer::normalize($examination->edukasi),
            'non_farmakologis' => ClinicalAnswer::normalize($examination->non_farmakologis),
            'kontrol_berikutnya' => $examination->kontrol_berikutnya?->toDateString(),
            'diagnoses' => $examination->diagnosa->sortBy('id')->map(fn ($diagnosis): array => [
                'id' => $diagnosis->id,
                'code' => $diagnosis->kode_icd10,
                'code_system' => $diagnosis->code_system,
                'code_release' => $diagnosis->code_release,
                'coding_status' => $diagnosis->coding_status,
                'coding_unresolved_reason' => $diagnosis->coding_unresolved_reason,
                'coding_verified_by' => $diagnosis->coding_verified_by,
                'coding_verified_at' => $diagnosis->coding_verified_at?->toIso8601String(),
                'name' => $diagnosis->nama_diagnosa,
                'note' => $diagnosis->keterangan,
                'type' => $diagnosis->jenis,
                'tooth_fdi' => $diagnosis->tooth_fdi,
                'surfaces' => $diagnosis->surfaces ?? [],
                'source_finding_code' => $diagnosis->source_finding_code,
            ])->values()->all(),
            'treatments' => $visit->tindakanKunjungan->sortBy('id')->map(function ($treatment): array {
                $consent = $treatment->informedConsents->sortBy('id')->last();

                return [
                    'id' => $treatment->id,
                    'name' => $treatment->nama_tindakan ?? $treatment->nama_tindakan_manual ?? $treatment->tindakan?->nama ?? 'Tindakan tidak tersedia',
                    'code' => $treatment->kode_icd9cm,
                    'code_system' => $treatment->code_system,
                    'code_release' => $treatment->code_release,
                    'coding_status' => $treatment->coding_status,
                    'code_display' => $treatment->coding_display,
                    'coding_review_reason' => $treatment->coding_review_reason,
                    'coding_reviewed_by' => $treatment->coding_reviewed_by,
                    'coding_reviewed_at' => $treatment->coding_reviewed_at?->toIso8601String(),
                    'quantity' => $treatment->jumlah,
                    'tooth_fdi' => $treatment->tooth_fdi,
                    'note' => $treatment->catatan,
                    'execution_role' => $treatment->execution_role,
                    'execution_status' => $treatment->execution_status,
                    'doctor_started_at' => $treatment->doctor_started_at?->toIso8601String(),
                    'doctor_start_consent_id' => $treatment->doctor_start_consent_id,
                    'consent' => $consent ? [
                        'id' => $consent->id,
                        'decision' => $consent->decision,
                        'text_version' => $consent->text_version,
                        'plan_hash' => $consent->plan_hash,
                        'recorded_at' => $consent->recorded_at?->toIso8601String(),
                    ] : null,
                ];
            })->values()->all(),
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
                    'details' => $finding->details,
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
        $previous = $examination->clinicalNoteVersions()->latest('version')->firstOrFail();
        $changed = collect(array_unique([...array_keys($previous->payload), ...array_keys($payload)]))
            ->reject(fn (string $key) => in_array($key, ['correction_context', 'coding_reconciliations'], true))
            ->filter(fn (string $key) => ($previous->payload[$key] ?? null) !== ($payload[$key] ?? null))->values()->all();
        $visit = $examination->kunjungan()->with('farmasi')->firstOrFail();
        $payload['correction_context'] = [
            'base_version_id' => $previous->id, 'base_hash' => $previous->content_hash,
            'changed_fields' => $changed, 'recorded_at' => now()->toIso8601String(),
            'after_48_hours' => $previous->recorded_at->copy()->addHours(48)->isPast(),
            'pharmacy_status' => $visit->farmasi?->status,
            'already_dispensed' => $visit->farmasi?->dispensed_at !== null,
            'existing_document_count' => $visit->suratMedis()->count(),
            'downstream_review_required' => array_intersect($changed, ['riwayat_alergi', 'diagnoses', 'prescriptions', 'treatments', 'kontrol_berikutnya']) !== [],
        ];

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
        return DB::transaction(function () use ($examination, $kind, $payload, $actorId, $reason): ClinicalNoteVersion {
            Pemeriksaan::whereKey($examination->id)->lockForUpdate()->firstOrFail();
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
        });
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
