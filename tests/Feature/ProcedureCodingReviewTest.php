<?php

namespace Tests\Feature;

use App\ClinicalAuditRecorder;
use App\ClinicalCodingService;
use App\Models\ClinicalTerminology;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\ProcedureCodingReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProcedureCodingReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_uncoded_new_procedure_blocks_normal_final_until_doctor_explicitly_classifies_supportive_care(): void
    {
        [$doctor, $visit, $procedure] = $this->context();
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.selesai', $visit), $this->signature($visit))->assertSessionHasErrors('coding_review');
        $this->post($this->url($visit, $procedure), [...$this->review($procedure), 'reviewed' => false])->assertSessionHasErrors('reviewed');
        $this->post($this->url($visit, $procedure), $this->review($procedure))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tindakan_kunjungan', ['id' => $procedure->id, 'coding_status' => 'not_applicable', 'coding_reviewed_by' => $doctor->id, 'kode_icd9cm' => null]);
        $this->classifySyntheticProceduresAsNotRequiringConsent($visit, $doctor);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), $this->signature($visit))->assertSessionHasNoErrors();
        $payload = $visit->pemeriksaan->clinicalNoteVersions()->sole()->payload;
        $this->assertSame('not_applicable', $payload['treatments'][0]['coding_status']);
        $this->assertNull($payload['treatments'][0]['code']);
        $this->assertSame('completed', $procedure->fresh()->execution_status);
    }

    public function test_mapping_preserves_local_name_and_binds_canonical_release_display_and_invalidates_old_consent(): void
    {
        [$doctor, $visit, $procedure] = $this->context();
        $this->classifySyntheticProceduresAsNotRequiringConsent($visit, $doctor);
        $procedure->refresh();
        $term = $this->term();
        $payload = [...$this->review($procedure), 'classification' => 'mapped', 'terminology_id' => $term->id];
        $this->post($this->url($visit, $procedure), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tindakan_kunjungan', ['id' => $procedure->id, 'nama_tindakan' => 'Perawatan sintetis', 'kode_icd9cm' => '23.09', 'coding_display' => 'Extraction of other tooth', 'code_release' => 'FY 2015']);
        $this->post($this->url($visit, $procedure), $payload)->assertSessionHasErrors('revision');
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), $this->signature($visit))->assertSessionHasErrors('procedure_consent');
        $term->update(['is_active' => false]);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), $this->signature($visit))->assertSessionHasErrors('procedure_coding');
        $this->assertDatabaseCount('clinical_note_versions', 0);
    }

    public function test_wrong_catalog_other_visit_or_non_doctor_cannot_classify_and_final_is_immutable(): void
    {
        [$doctor, $visit, $procedure] = $this->context();
        $term = $this->term();
        $term->update(['code_system' => 'icd10_who', 'code_type' => 'diagnosis']);
        $this->actingAs($doctor)->post($this->url($visit, $procedure), [...$this->review($procedure), 'classification' => 'mapped', 'terminology_id' => $term->id])->assertSessionHasErrors('terminology_id');
        $other = $visit->replicate();
        $other->no_kunjungan = 'KNJ-OTHER';
        $other->save();
        $this->post($this->url($other, $procedure), $this->review($procedure))->assertNotFound();
        foreach (['admin', 'perawat', 'farmasi', 'pendaftaran'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))->post($this->url($visit, $procedure), $this->review($procedure))->assertForbidden();
        }
        $this->actingAs($doctor);
        $visit->pemeriksaan->update(['signed_at' => now(), 'signed_by_user_id' => $doctor->id]);
        $this->post($this->url($visit, $procedure), $this->review($procedure))->assertUnprocessable();
        $this->assertSame('unresolved', $procedure->fresh()->coding_status);
    }

    public function test_coding_review_and_audit_are_atomic(): void
    {
        [$doctor, $visit, $procedure] = $this->context();
        $this->mock(ClinicalAuditRecorder::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit gagal sintetis'));
        $this->actingAs($doctor)->post($this->url($visit, $procedure), $this->review($procedure))->assertServerError();
        $this->assertSame('unresolved', $procedure->fresh()->coding_status);
        $this->assertNull($procedure->fresh()->coding_reviewed_by);
    }

    private function url(Kunjungan $visit, TindakanKunjungan $procedure): string
    {
        return route('pelayanan.pemeriksaan.tindakan.coding', [$visit, $procedure]);
    }

    private function review(TindakanKunjungan $procedure): array
    {
        return ['revision' => app(ProcedureCodingReview::class)->revision($procedure), 'classification' => 'not_applicable', 'reason' => 'Perawatan suportif sintetis yang tidak termasuk prosedur medis berkode.', 'reviewed' => true];
    }

    private function signature(Kunjungan $visit): array
    {
        return ['review_final_plan' => true, 'review_performed_procedures' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password'];
    }

    private function term(): ClinicalTerminology
    {
        return ClinicalTerminology::create(['code_system' => 'icd9cm_procedure', 'release' => 'FY 2015', 'code' => '23.09', 'display' => 'Extraction of other tooth', 'code_type' => 'procedure', 'is_active' => true]);
    }

    public function test_legacy_procedure_does_not_make_examination_coding_appear_fully_verified(): void
    {
        [, $visit, $procedure] = $this->context();
        $visit->pemeriksaan->diagnosa()->first()->voidClinicalEntry(User::factory()->create(['role' => 'dokter']), 'Fixture diagnosis diganti untuk mengisolasi status tindakan.');
        $procedure->update(['coding_status' => 'legacy', 'consent_review_required' => false]);
        app(ClinicalCodingService::class)->finalize(Request::create('/'), $visit, $visit->pemeriksaan);
        $this->assertSame('legacy', $visit->pemeriksaan->fresh()->coding_status);
    }

    private function context(): array
    {
        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $staff = Nakes::create(['kode' => 'DOCTOR-CODING', 'nama' => 'Dokter sintetis', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $doctor->id, 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'CODING', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-CODING', 'nama' => 'Pasien sintetis']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-CODING', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $staff->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        Screening::create(['kunjungan_id' => $visit->id, 'submitted_at' => now()]);
        $exam = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $staff->id, 'anamnesis' => 'Keluhan sintetis', 'pemeriksaan_fisik' => 'Temuan sintetis']);
        $exam->diagnosa()->create(['kode_icd10' => 'Z00.0', 'nama_diagnosa' => 'Diagnosis fixture legacy', 'jenis' => 'utama']);
        $procedure = $visit->tindakanKunjungan()->create(['nama_tindakan' => 'Perawatan sintetis', 'nama_tindakan_manual' => 'Perawatan sintetis', 'jumlah' => 1, 'execution_role' => 'dokter', 'execution_status' => 'pending', 'consent_review_required' => true, 'coding_status' => 'unresolved']);

        return [$doctor, $visit->fresh(['pemeriksaan', 'screening']), $procedure];
    }
}
