<?php

namespace Tests\Feature;

use App\Models\ClinicalNoteVersion;
use App\Models\ClinicalTerminology;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DiagnosisCodingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_coded_diagnosis_is_bound_to_active_who_catalog_and_final_snapshot(): void
    {
        [$doctor, $visit] = $this->context();
        $term = $this->whoTerm();

        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [
            'kode_icd10' => 'I10', 'code_system' => 'icd10_who', 'code_release' => 'WHO 2016',
            'nama_diagnosa' => 'Nama dari browser tidak dipercaya', 'jenis' => 'utama',
        ])->assertSessionHasNoErrors();

        $diagnosis = $visit->pemeriksaan->diagnosa()->sole();
        $this->assertSame($term->display, $diagnosis->nama_diagnosa);
        $this->assertSame('verified', $diagnosis->coding_status);
        $this->assertSame($doctor->id, $diagnosis->coding_verified_by);
        $this->assertNotNull($diagnosis->coding_verified_at);

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), $this->signature($visit))->assertSessionHasNoErrors();
        $version = ClinicalNoteVersion::sole();
        $this->assertSame('verified', $version->payload['coding']['status']);
        $this->assertSame('verified', $version->payload['diagnoses'][0]['coding_status']);
        $this->assertSame('WHO 2016', $version->payload['diagnoses'][0]['code_release']);
    }

    public function test_new_diagnosis_rejects_wrong_code_system_and_fake_code_when_catalog_exists(): void
    {
        [$doctor, $visit] = $this->context();
        $this->whoTerm();
        $this->actingAs($doctor);

        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [
            'kode_icd10' => 'I10', 'code_system' => 'icd10_cm', 'nama_diagnosa' => 'Hypertension', 'jenis' => 'utama',
        ])->assertSessionHasErrors('code_system');
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [
            'kode_icd10' => 'X99.9', 'code_system' => 'icd10_who', 'nama_diagnosa' => 'Kode palsu', 'jenis' => 'utama',
        ])->assertSessionHasErrors('kode_icd10');

        $this->assertDatabaseCount('diagnosa', 0);
    }

    public function test_manual_or_empty_catalog_diagnosis_stays_unresolved_and_blocks_normal_finalization(): void
    {
        [$doctor, $visit] = $this->context();
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [
            'is_manual' => true, 'nama_diagnosa' => 'Diagnosis klinis sementara', 'jenis' => 'utama',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diagnosa', ['coding_status' => 'unresolved', 'code_system' => 'manual']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), $this->signature($visit))
            ->assertSessionHasErrors('coding_review');
        $this->assertNull($visit->pemeriksaan->fresh()->signed_at);

        $visit->pemeriksaan->diagnosa()->sole()->cancelClinicalEntry(request()->merge(['reason' => 'Ganti ke kode yang belum dapat diverifikasi']));
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [
            'kode_icd10' => 'R50.9', 'code_system' => 'icd10_who', 'nama_diagnosa' => 'Demam', 'jenis' => 'utama',
        ])->assertSessionHasNoErrors();
        $this->assertSame('unresolved', $visit->pemeriksaan->diagnosa()->whereNull('voided_at')->sole()->coding_status);
    }

    public function test_emergency_can_sign_unresolved_diagnosis_with_reason_but_is_not_integration_ready(): void
    {
        [$doctor, $visit] = $this->context('darurat');
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [
            'is_manual' => true, 'nama_diagnosa' => 'Diagnosis kerja kegawatdaruratan', 'jenis' => 'utama',
        ])->assertSessionHasNoErrors();

        $payload = $this->signature($visit) + [
            'nursing_clarification' => 'Pasien dinilai ulang dan distabilkan sesuai kemampuan klinik.',
            'coding_downtime_acknowledged' => true,
            'coding_downtime_reason' => 'Prioritas darurat; coding direkonsiliasi maksimal satu hari setelah pelayanan.',
        ];
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), $payload)->assertSessionHasNoErrors();

        $examination = $visit->pemeriksaan->fresh();
        $this->assertSame('unresolved', $examination->coding_status);
        $this->assertNotNull($examination->coding_review_due_at);
        $this->assertSame('unresolved', ClinicalNoteVersion::sole()->payload['coding']['status']);
    }

    public function test_retired_catalog_after_draft_prevents_finalization(): void
    {
        [$doctor, $visit] = $this->context();
        $term = $this->whoTerm();
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [
            'kode_icd10' => 'I10', 'code_system' => 'icd10_who', 'code_release' => 'WHO 2016',
            'nama_diagnosa' => 'Hypertension', 'jenis' => 'utama',
        ])->assertSessionHasNoErrors();
        $term->update(['is_active' => false]);

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), $this->signature($visit))
            ->assertSessionHasErrors('coding_review');
    }

    public function test_examination_page_exposes_only_who_for_new_diagnoses(): void
    {
        [$doctor, $visit] = $this->context();
        $this->whoTerm();
        ClinicalTerminology::create(['code_system' => 'icd10_cm', 'release' => 'FY 2026', 'code' => 'I10', 'display' => 'Hypertension CM', 'code_type' => 'diagnosis', 'is_active' => true]);

        $this->actingAs($doctor)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('diagnosisCodeSystems', [['value' => 'icd10_who', 'label' => 'ICD-10 WHO']])
            ->where('dentalOptions.diagnosisCatalog', [])
        );
    }

    /** @return array{User, Kunjungan} */
    private function context(string $priority = 'normal'): array
    {
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DOK-CODING-'.$priority, 'nama' => 'Dokter Coding', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $doctorUser->id, 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'POLI-CODING-'.$priority, 'nama' => 'Poli Coding', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-CODING-'.$priority, 'nama' => 'Pasien Coding']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-CODING-'.$priority, 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        Screening::create(['kunjungan_id' => $visit->id, 'submitted_at' => now(), 'prioritas_layanan' => $priority]);
        Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'anamnesis' => 'Keluhan sintetis', 'pemeriksaan_fisik' => 'Temuan pemeriksaan sintetis']);

        return [$doctorUser, $visit->fresh(['screening', 'pemeriksaan'])];
    }

    private function whoTerm(): ClinicalTerminology
    {
        return ClinicalTerminology::create([
            'code_system' => 'icd10_who', 'release' => 'WHO 2016', 'code' => 'I10',
            'display' => 'Essential (primary) hypertension', 'code_type' => 'diagnosis', 'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function signature(Kunjungan $visit): array
    {
        return [
            'review_final_plan' => true,
            'review_nursing_assessment' => true,
            'screening_revision' => $visit->fresh()->screening?->revisionToken(),
            'signature_password' => 'password',
        ];
    }
}
