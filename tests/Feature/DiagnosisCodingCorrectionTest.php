<?php

namespace Tests\Feature;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\EffectiveDiagnosisReport;
use App\Models\ClinicalTerminology;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DiagnosisCodingCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_integrity_failure_is_scoped_to_doctor_and_period(): void
    {
        [$doctor, $visit] = $this->context();
        $visit->pemeriksaan->update(['latest_note_hash' => str_repeat('0', 64)]);
        $this->actingAs($doctor)->get(route('dokter.laporan-top-diagnosa'))->assertStatus(409);
        $visits = DB::table('kunjungan')->whereDate('tanggal', '<', today());
        $this->assertSame(0, app(EffectiveDiagnosisReport::class)->query($visits)->count());
        $other = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        Nakes::create(['kode' => 'DOK-REPORT-OTHER', 'nama' => 'Dokter laporan lain', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $other->id, 'is_active' => true]);
        $this->actingAs($other)->get(route('dokter.laporan-top-diagnosa'))->assertInertia(fn (Assert $page) => $page->where('diagnoses.total', 0));
    }

    public function test_correction_preserves_original_and_context_updates_effective_version_and_retries_once(): void
    {
        [$doctor, $visit, $term] = $this->context();
        $original = $visit->pemeriksaan->clinicalNoteVersions()->sole();
        $payload = $this->payload($visit, $term);
        $this->actingAs($doctor)->get(route('pelayanan.pemeriksaan.diagnosa.correction.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pemeriksaan/diagnosis-correction')->where('diagnoses.0.status', 'unresolved'));
        $this->post($this->url($visit), $payload)->assertSessionHasNoErrors();
        $this->post($this->url($visit), $payload)->assertSessionHasNoErrors();

        $exam = $visit->pemeriksaan->fresh();
        $this->assertSame(2, $exam->clinicalNoteVersions()->count());
        $this->assertSame('Diagnosis naratif asli', $original->fresh()->payload['diagnoses'][0]['name']);
        $latest = $exam->clinicalNoteVersions()->latest('version')->firstOrFail();
        $this->assertSame('I10', $latest->payload['diagnoses'][0]['code']);
        $this->assertSame($original->content_hash, $latest->payload['correction_context']['base_hash']);
        $this->assertContains('diagnoses', $latest->payload['correction_context']['changed_fields']);
        $this->assertTrue($latest->payload['correction_context']['downstream_review_required']);
        $report = app(EffectiveDiagnosisReport::class)->query()->get();
        $this->assertCount(1, $report);
        $this->assertSame('I10', $report->sole()->kode_icd10);
        $this->assertSame($term->display, $report->sole()->nama_diagnosa);
        $this->get(route('dokter.laporan-top-diagnosa'))->assertInertia(fn (Assert $page) => $page
            ->where('diagnoses.data.0.code', 'I10')->where('diagnoses.data.0.count', 1));
        $this->assertSame('46', $latest->payload['diagnoses'][0]['tooth_fdi']);
        $this->assertSame(['O'], $latest->payload['diagnoses'][0]['surfaces']);
        $this->assertSame('utama', $latest->payload['diagnoses'][0]['type']);
        $this->assertSame($original->payload['prescriptions'], $latest->payload['prescriptions']);
        $this->assertSame('verified', $exam->coding_status);
        $this->assertNull($exam->coding_review_due_at);
        $this->assertTrue(app(ClinicalNoteRecorder::class)->verify($exam));
        $this->assertDatabaseCount('diagnosa', 1);
        $this->assertDatabaseHas('diagnosa', ['nama_diagnosa' => 'Diagnosis naratif asli', 'coding_status' => 'unresolved']);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'clinical_coding.corrected', 'actor_id' => $doctor->id]);
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page->where('visit.diagnoses.0.code', 'I10'));
    }

    public function test_stale_retry_conflict_invalid_diagnosis_and_retired_or_wrong_catalog_are_rejected(): void
    {
        [$doctor, $visit, $term] = $this->context();
        $payload = $this->payload($visit, $term);
        $this->actingAs($doctor)->post($this->url($visit), [...$payload, 'base_hash' => str_repeat('0', 64)])->assertSessionHasErrors('base_hash');
        $this->post($this->url($visit), [...$payload, 'diagnosis_key' => 99])->assertNotFound();
        $term->update(['is_active' => false]);
        $this->post($this->url($visit), $payload)->assertSessionHasErrors('terminology_id');
        $term->update(['is_active' => true, 'code_system' => 'icd10_cm']);
        $this->post($this->url($visit), $payload)->assertSessionHasErrors('terminology_id');
        $term->update(['code_system' => 'icd10_who']);
        $this->post($this->url($visit), $payload)->assertSessionHasNoErrors();
        $this->post($this->url($visit), [...$payload, 'reason' => 'Isi berbeda pada ID permintaan yang sama'])->assertConflict();
        $this->post($this->url($visit), [...$payload, 'request_id' => (string) Str::uuid()])->assertSessionHasErrors('base_hash');
        $this->assertDatabaseCount('clinical_note_versions', 2);
    }

    public function test_non_doctors_and_other_doctor_cannot_view_or_correct(): void
    {
        [, $visit, $term] = $this->context();
        foreach (['admin', 'pendaftaran', 'perawat', 'farmasi', 'kasir', 'dokter'] as $role) {
            $other = User::factory()->create(['role' => $role, 'is_active' => true]);
            if ($role === 'dokter') {
                Nakes::create(['kode' => 'DOK-OTHER', 'nama' => 'Dokter lain', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $other->id, 'is_active' => true]);
            }
            $this->actingAs($other)->post($this->url($visit), $this->payload($visit, $term))->assertStatus(in_array($role, ['dokter'], true) ? 404 : 403);
        }
        $this->assertDatabaseCount('clinical_note_versions', 1);
    }

    public function test_reauthentication_and_review_required_and_audit_failure_rolls_back(): void
    {
        [$doctor, $visit, $term] = $this->context();
        $payload = $this->payload($visit, $term);
        $this->actingAs($doctor)->post($this->url($visit), [...$payload, 'signature_password' => 'salah', 'review_clinical_impact' => false])
            ->assertSessionHasErrors(['signature_password', 'review_clinical_impact']);
        $this->assertNull(session('_old_input.signature_password'));
        $this->mock(ClinicalAuditRecorder::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit gagal sintetis'));
        $this->post($this->url($visit), $payload)->assertServerError();
        $this->assertDatabaseCount('clinical_note_versions', 1);
        $this->assertSame('unresolved', $visit->pemeriksaan->fresh()->coding_status);
    }

    private function url(Kunjungan $visit): string
    {
        return route('pelayanan.pemeriksaan.diagnosa.correction.store', $visit);
    }

    private function payload(Kunjungan $visit, ClinicalTerminology $term): array
    {
        return ['base_hash' => $visit->pemeriksaan->clinicalNoteVersions()->latest('version')->firstOrFail()->content_hash,
            'request_id' => (string) Str::uuid(), 'diagnosis_key' => 0, 'terminology_id' => $term->id,
            'reason' => 'Rekonsiliasi setelah konfirmasi diagnosis oleh dokter.', 'review_clinical_impact' => true, 'signature_password' => 'password'];
    }

    private function context(): array
    {
        $user = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DOK-CORRECTION', 'nama' => 'Dokter Koreksi', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $user->id, 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'CORRECTION', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-CORRECTION', 'nama' => 'Pasien sintetis']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-CORRECTION', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'selesai']);
        $exam = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'anamnesis' => 'Keluhan sintetis', 'pemeriksaan_fisik' => 'Temuan sintetis', 'coding_status' => 'unresolved', 'coding_review_due_at' => now()->addDay()]);
        $exam->diagnosa()->create(['nama_diagnosa' => 'Diagnosis naratif asli', 'jenis' => 'utama', 'code_system' => 'manual', 'coding_status' => 'unresolved', 'tooth_fdi' => '46', 'surfaces' => ['O']]);
        $notes = app(ClinicalNoteRecorder::class);
        $notes->recordFinal($exam, $notes->snapshot($visit, $exam), $user->id);
        $exam->update(['status' => 'selesai', 'signed_by_user_id' => $user->id, 'signed_at' => now()]);
        $term = ClinicalTerminology::create(['code_system' => 'icd10_who', 'code_type' => 'diagnosis', 'code' => 'I10', 'display' => 'Essential hypertension', 'release' => 'WHO 2016', 'is_active' => true]);

        return [$user, $visit->fresh(['pemeriksaan']), $term];
    }
}
