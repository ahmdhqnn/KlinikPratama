<?php

namespace Tests\Feature;

use App\ClinicalAuditRecorder;
use App\GeneralConsentService;
use App\Models\GeneralConsent;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GeneralConsentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_person_confirmation_uses_the_patient_identity_and_verifies_without_an_upload(): void
    {
        [$staff, $nurse, $visit] = $this->context();
        $payload = [...$this->decision(), 'in_person' => true, 'signer_name' => 'Nama dari permintaan'];
        unset($payload['evidence']);

        $this->actingAs($staff)->post(route('general-consent.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->post(route('general-consent.store', $visit), $payload)->assertSessionHasNoErrors();
        $consent = GeneralConsent::sole();
        $this->assertSame($visit->pasien->nama, $consent->signer_name);
        $this->assertSame($staff->id, $consent->recorded_by);
        $this->assertNull($consent->evidence_path);
        $this->assertSame([], Storage::disk('local')->allFiles('consents'));

        $url = route('general-consent.verify', $consent->signature_token);
        $this->get($url)->assertOk()->assertSee('Catatan terverifikasi')->assertSee('logo_kemenpar.webp')
            ->assertSee('clinical-document-head')->assertSee('logo_wonderful.png');
        $this->actingAs($nurse)->get($url)->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'farmasi', 'is_active' => true]))->get($url)->assertForbidden();

        $consent->decision = 'refused';
        $this->assertFalse(app(GeneralConsentService::class)->verifies($consent));
    }

    public function test_registration_records_private_immutable_evidence_and_retries_do_not_duplicate_it(): void
    {
        [$staff, , $visit] = $this->context();
        $payload = $this->decision();
        $this->actingAs($staff)->get(route('general-consent.show', $visit))->assertInertia(fn (Assert $page) => $page->where('summary.status', 'undocumented')->where('canWrite', true));
        $this->post(route('general-consent.store', $visit), $payload)->assertSessionHasNoErrors();
        $consent = GeneralConsent::sole();
        $this->assertSame(GeneralConsentService::TEXT, $consent->text_snapshot);
        $this->assertSame($staff->id, $consent->recorded_by);
        $this->assertNotNull($consent->recorded_at);
        Storage::disk('local')->assertExists($consent->evidence_path);
        $this->get(route('general-consent.evidence', [$visit, $consent]))->assertDownload();
        $this->post(route('general-consent.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('general_consents', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('consents'));
        $this->post(route('general-consent.store', $visit), [...$payload, 'signer_name' => 'Keputusan berbeda'])->assertStatus(409);
        foreach (['update', 'delete'] as $operation) {
            try {
                $query = DB::table('general_consents')->where('id', $consent->id);
                $operation === 'update' ? $query->update(['decision' => 'refused']) : $query->delete();
                $this->fail('Bukti consent tidak boleh ditimpa atau dihapus.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('Bukti consent dilindungi', $exception->getMessage());
            }
        }
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'general_consent.recorded', 'actor_id' => $staff->id]);
    }

    public function test_representatives_and_refusals_require_explanation_and_verified_evidence(): void
    {
        [$staff, , $visit] = $this->context();
        $payload = [...$this->decision(), 'signer_relationship' => 'guardian', 'decision' => 'refused', 'explained' => false];
        unset($payload['evidence']);
        $this->actingAs($staff)->post(route('general-consent.store', $visit), $payload)->assertSessionHasErrors(['evidence', 'representative_verification', 'reason', 'explained']);
        $this->assertDatabaseCount('general_consents', 0);
        $payload = [...$payload, 'representative_verification' => 'Kewenangan wali diperiksa pada dokumen keluarga.', 'reason' => 'Pasien memilih menolak setelah penjelasan.', 'explained' => true, 'evidence' => $this->pdf()];
        $this->post(route('general-consent.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->assertSame('refused', GeneralConsent::sole()->decision);
    }

    public function test_withdrawal_is_a_new_record_and_stale_decisions_cannot_overwrite_it(): void
    {
        [$staff, , $visit] = $this->context();
        $this->actingAs($staff)->post(route('general-consent.store', $visit), $this->decision())->assertSessionHasNoErrors();
        $first = GeneralConsent::sole();
        $this->post(route('general-consent.store', $visit), [...$this->decision(), 'current_id' => $first->id, 'decision' => 'withdrawn', 'reason' => 'Pasien mencabut persetujuan setelah penjelasan.'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('general_consents', 2);
        $this->assertSame('accepted', $first->fresh()->decision);
        $this->assertSame('withdrawn', app(GeneralConsentService::class)->summary($visit)['status']);
        $this->post(route('general-consent.store', $visit), [...$this->decision(), 'current_id' => $first->id])->assertSessionHasErrors('current_id');
        $this->assertDatabaseCount('general_consents', 2);
    }

    public function test_nurses_can_read_own_clinic_but_cannot_record_a_patients_decision(): void
    {
        [$staff, $nurse, $visit] = $this->context();
        $this->actingAs($staff)->post(route('general-consent.store', $visit), $this->decision())->assertSessionHasNoErrors();
        $consent = GeneralConsent::sole();
        $this->actingAs($nurse)->get(route('general-consent.show', $visit))->assertInertia(fn (Assert $page) => $page->where('canWrite', false));
        $this->post(route('general-consent.store', $visit), $this->decision())->assertForbidden();
        $other = Poliklinik::create(['kode' => 'GC-OTHER', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $visit->update(['poliklinik_id' => $other->id]);
        $this->get(route('general-consent.show', $visit))->assertNotFound();
        $this->get(route('general-consent.evidence', [$visit, $consent]))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'farmasi', 'is_active' => true]))->get(route('general-consent.show', $visit))->assertForbidden();
    }

    public function test_evidence_rejects_cross_patient_access_and_tampering(): void
    {
        [$staff, , $visit] = $this->context();
        $this->actingAs($staff)->post(route('general-consent.store', $visit), $this->decision())->assertSessionHasNoErrors();
        $consent = GeneralConsent::sole();
        $otherPatient = Pasien::create(['no_rm' => 'RM-GC-OTHER', 'nama' => 'Pasien Lain Sintetis']);
        $otherVisit = $visit->replicate();
        $otherVisit->no_kunjungan = 'KNJ-GC-OTHER';
        $otherVisit->pasien_id = $otherPatient->id;
        $otherVisit->save();
        $this->get(route('general-consent.evidence', [$otherVisit, $consent]))->assertNotFound();
        Storage::disk('local')->put($consent->evidence_path, 'Bukti diubah di luar aplikasi');
        $this->get(route('general-consent.evidence', [$visit, $consent]))->assertStatus(409);
    }

    public function test_tampered_uploaded_evidence_cannot_satisfy_the_general_consent_gate(): void
    {
        [$staff, $nurse, $visit] = $this->context();
        $this->actingAs($staff)->post(route('general-consent.store', $visit), $this->decision())->assertSessionHasNoErrors();
        $consent = GeneralConsent::sole();
        Storage::disk('local')->put($consent->evidence_path, 'Bukti yang berbeda setelah pencatatan');

        $this->actingAs($nurse)->get(route('general-consent.show', $visit))
            ->assertInertia(fn (Assert $page) => $page->where('summary.status', 'invalid'));
        $this->actingAs($nurse)->beginNursingAssessment($visit);
        $this->actingAs($nurse)->post(route('pelayanan.screening.store', $visit), $this->assessment())
            ->assertSessionHasErrors('general_consent');
        $this->assertNull($visit->fresh()->general_consent_id);
    }

    public function test_upload_rejects_executable_disguises_and_oversized_evidence(): void
    {
        [$staff, , $visit] = $this->context();
        $this->actingAs($staff);
        foreach ([UploadedFile::fake()->createWithContent('evidence.pdf', '<script>synthetic</script>')->mimeType('text/html'),
            UploadedFile::fake()->createWithContent('evidence.php', "%PDF-1.4\nSynthetic\n%%EOF"),
            UploadedFile::fake()->create('oversized.pdf', 5121, 'application/pdf')] as $file) {
            $this->post(route('general-consent.store', $visit), [...$this->decision(), 'evidence' => $file])->assertSessionHasErrors('evidence');
        }
        $this->assertDatabaseCount('general_consents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('consents'));
    }

    public function test_audit_failure_rolls_back_decision_and_cleans_only_the_uncommitted_file(): void
    {
        [$staff, , $visit] = $this->context();
        $this->mock(ClinicalAuditRecorder::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Simulasi audit gagal'));
        $this->actingAs($staff)->post(route('general-consent.store', $visit), $this->decision())->assertServerError();
        $this->assertDatabaseCount('general_consents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('consents'));
    }

    public function test_new_visit_handoff_requires_documentation_but_drafts_can_be_saved(): void
    {
        [$staff, $nurse, $visit] = $this->context();
        $this->actingAs($nurse)->beginNursingAssessment($visit);
        $this->post(route('pelayanan.screening.store', $visit), [...$this->assessment(), 'tindakan' => 'draf'])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.screening.store', $visit), $this->assessment())->assertSessionHasErrors('general_consent');
        $this->assertNull($visit->fresh()->screening->submitted_at);
        $this->actingAs($staff)->post(route('general-consent.store', $visit), [...$this->decision(), 'decision' => 'refused', 'reason' => 'Pasien memilih menolak setelah penjelasan.'])->assertSessionHasNoErrors();
        $this->actingAs($nurse)->post(route('pelayanan.screening.store', $visit), $this->assessment())->assertSessionHasNoErrors();
        $this->assertSame(GeneralConsent::sole()->id, $visit->fresh()->general_consent_id);
        $this->assertNotNull($visit->fresh()->screening->submitted_at);
    }

    public function test_emergency_deferral_is_audited_without_fabricating_consent(): void
    {
        [, $nurse, $visit] = $this->context();
        $this->actingAs($nurse)->beginNursingAssessment($visit);
        $payload = [...$this->assessment(), 'general_consent_emergency_reason' => 'Pelayanan segera diperlukan; administrasi diselesaikan setelah stabil.'];
        $this->post(route('pelayanan.screening.store', $visit), $payload)->assertSessionHasErrors('general_consent');
        $this->post(route('pelayanan.screening.store', $visit), [...$payload, 'nyeri_dada' => 'ya'])->assertSessionHasNoErrors();
        $this->assertNull($visit->fresh()->general_consent_id);
        $this->assertDatabaseCount('general_consents', 0);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'general_consent.emergency_deferral', 'kunjungan_id' => $visit->id, 'actor_id' => $nurse->id]);
    }

    private function context(): array
    {
        Storage::fake('local');
        $staff = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'GC-UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        Nakes::create(['kode' => 'GC-NURSE', 'nama' => 'Perawat Sintetis', 'kategori' => 'medis', 'jabatan' => 'perawat', 'user_id' => $nurse->id, 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-GC', 'nama' => 'Pasien Consent Sintetis']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-GC', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'menunggu', 'general_consent_required' => true]);

        return [$staff, $nurse, $visit];
    }

    private function decision(): array
    {
        return ['request_id' => (string) Str::uuid(), 'current_id' => null, 'decision' => 'accepted', 'text_version' => GeneralConsentService::VERSION, 'signer_name' => 'Pasien Consent Sintetis', 'signer_relationship' => 'patient', 'explained' => true, 'evidence' => $this->pdf()];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('consent-sintetis.pdf', "%PDF-1.4\nBukti sintetis pengujian\n%%EOF");
    }

    private function assessment(): array
    {
        return ['keluhan' => 'Keluhan uji', 'lama_keluhan' => '2 hari', 'sumber_informasi' => 'pasien', 'status_alergi' => 'belum_diketahui', 'status_riwayat_penyakit' => 'belum_diketahui', 'konfirmasi_riwayat' => true,
            'td_sistole' => 120, 'td_diastole' => 80, 'nadi' => 80, 'suhu' => 36.5, 'respirasi' => 18, 'berat_badan' => 60, 'tinggi_badan' => 160,
            'nyeri_dada' => 'tidak', 'kejang' => 'tidak', 'nadi_teraba' => 'teraba', 'pola_pernapasan' => 'normal', 'kesadaran' => 'sadar', 'kondisi_psikiatri' => 'normal', 'risiko_jatuh_visual' => 'rendah'];
    }
}
