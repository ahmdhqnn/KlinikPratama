<?php

namespace Tests\Feature;

use App\ClinicalAuditRecorder;
use App\Models\InformedConsent;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\ProcedureConsentService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProcedureConsentFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_procedure_requires_review_and_accepted_evidence_is_private_immutable_and_idempotent(): void
    {
        [$doctor, , $visit, $procedure] = $this->context();
        $service = app(ProcedureConsentService::class);
        $this->actingAs($doctor)->get(route('procedure-consent.show', [$visit, $procedure]))
            ->assertInertia(fn (Assert $page) => $page->where('summary.decision', 'unreviewed')->where('summary.requiredReview', true)
                ->where('planHash', $service->planHash($procedure))->where('canWrite', true));
        $payload = $this->decision($procedure);
        $this->post(route('procedure-consent.store', [$visit, $procedure]), $payload)->assertSessionHasNoErrors();
        $consent = InformedConsent::sole();
        $this->assertSame($procedure->id, $consent->tindakan_kunjungan_id);
        $this->assertSame($service->planHash($procedure), $consent->plan_hash);
        $this->assertSame($doctor->id, $consent->recorded_by);
        $this->assertNotNull($consent->recorded_at);
        Storage::disk('local')->assertExists($consent->evidence_path);
        $this->get(route('procedure-consent.evidence', [$visit, $procedure, $consent]))->assertDownload();
        $this->post(route('procedure-consent.store', [$visit, $procedure]), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('informed_consent', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('consents/procedures'));
        $this->post(route('procedure-consent.store', [$visit, $procedure]), [...$payload, 'reason' => 'Keputusan lain menggunakan UUID yang sama.'])->assertStatus(409);
        foreach (['update', 'delete'] as $operation) {
            try {
                $query = DB::table('informed_consent')->where('id', $consent->id);
                $operation === 'update' ? $query->update(['decision' => 'refused']) : $query->delete();
                $this->fail('Bukti informed consent tidak boleh dimutasi.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('Catatan klinis dilindungi', $exception->getMessage());
            }
        }
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'procedure_consent.recorded', 'actor_id' => $doctor->id]);
    }

    public function test_roles_and_clinic_scope_limit_read_and_write_access(): void
    {
        [$doctor, $nurse, $visit, $procedure] = $this->context();
        $this->actingAs($doctor)->post(route('procedure-consent.store', [$visit, $procedure]), $this->classification($procedure))->assertSessionHasNoErrors();
        $this->actingAs($nurse)->get(route('procedure-consent.show', [$visit, $procedure]))->assertInertia(fn (Assert $page) => $page->where('canWrite', false));
        $this->post(route('procedure-consent.store', [$visit, $procedure]), $this->classification($procedure))->assertForbidden();
        foreach (['admin', 'pendaftaran', 'farmasi', 'manajemen'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))
                ->get(route('procedure-consent.show', [$visit, $procedure]))->assertForbidden();
        }
        $otherClinic = Poliklinik::create(['kode' => 'IC-OTHER', 'nama' => 'Poli Gigi Lain', 'jenis' => 'gigi', 'is_active' => true]);
        Nakes::where('user_id', $nurse->id)->update(['poliklinik_id' => $otherClinic->id]);
        $this->actingAs($nurse)->get(route('procedure-consent.show', [$visit, $procedure]))->assertNotFound();
    }

    public function test_stale_plan_cross_visit_and_tampered_evidence_are_rejected(): void
    {
        [$doctor, , $visit, $procedure] = $this->context();
        $payload = $this->decision($procedure);
        $procedure->update(['jumlah' => 2]);
        $this->actingAs($doctor)->post(route('procedure-consent.store', [$visit, $procedure]), $payload)->assertSessionHasErrors('plan_hash');
        $this->assertDatabaseCount('informed_consent', 0);
        $payload = $this->decision($procedure->fresh());
        $this->post(route('procedure-consent.store', [$visit, $procedure]), $payload)->assertSessionHasNoErrors();
        $consent = InformedConsent::sole();
        $otherPatient = Pasien::create(['no_rm' => 'RM-IC-OTHER', 'nama' => 'Pasien Lain Sintetis']);
        $otherVisit = $visit->replicate();
        $otherVisit->no_kunjungan = 'KNJ-IC-OTHER';
        $otherVisit->pasien_id = $otherPatient->id;
        $otherVisit->save();
        $this->get(route('procedure-consent.evidence', [$otherVisit, $procedure, $consent]))->assertNotFound();
        Storage::disk('local')->put($consent->evidence_path, 'Bukti diubah');
        $this->get(route('procedure-consent.evidence', [$visit, $procedure, $consent]))->assertStatus(409);
        $this->expectException(ValidationException::class);
        app(ProcedureConsentService::class)->ensureCanPerform($procedure->fresh());
    }

    public function test_refusal_and_withdrawal_lock_execution_and_cannot_be_reclassified_away(): void
    {
        [$doctor, , $visit, $procedure] = $this->context();
        $this->actingAs($doctor)->post(route('procedure-consent.store', [$visit, $procedure]), [...$this->decision($procedure), 'decision' => 'refused'])->assertSessionHasNoErrors();
        $refusal = InformedConsent::sole();
        try {
            app(ProcedureConsentService::class)->ensureCanPerform($procedure);
            $this->fail('Penolakan harus mengunci tindakan.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('procedure_consent', $exception->errors());
        }
        $this->post(route('procedure-consent.store', [$visit, $procedure]), [...$this->classification($procedure), 'current_id' => $refusal->id])->assertSessionHasErrors('decision');
        $this->post(route('procedure-consent.store', [$visit, $procedure]), [...$this->decision($procedure), 'current_id' => $refusal->id])->assertSessionHasNoErrors();
        $accepted = InformedConsent::latest('id')->firstOrFail();
        app(ProcedureConsentService::class)->ensureCanPerform($procedure);
        $this->post(route('procedure-consent.store', [$visit, $procedure]), [...$this->decision($procedure), 'current_id' => $accepted->id, 'decision' => 'withdrawn'])->assertSessionHasNoErrors();
        $this->expectException(ValidationException::class);
        app(ProcedureConsentService::class)->ensureCanPerform($procedure);
    }

    public function test_emergency_requires_documented_triage_and_post_procedure_backdating_is_rejected(): void
    {
        [$doctor, , $visit, $procedure] = $this->context();
        $payload = [...$this->classification($procedure), 'decision' => 'emergency'];
        $this->actingAs($doctor)->post(route('procedure-consent.store', [$visit, $procedure]), $payload)->assertSessionHasErrors('decision');
        $visit->screening()->update(['prioritas_layanan' => 'darurat']);
        $this->post(route('procedure-consent.store', [$visit, $procedure]), $payload)->assertSessionHasNoErrors();
        app(ProcedureConsentService::class)->ensureCanPerform($procedure->fresh());

        $second = $visit->tindakanKunjungan()->create(['nama_tindakan_manual' => 'Tindakan sudah selesai', 'nama_tindakan' => 'Tindakan sudah selesai', 'jumlah' => 1, 'execution_role' => 'dokter', 'execution_status' => 'completed', 'consent_review_required' => true, 'performed_by' => $doctor->id, 'performed_at' => now()]);
        $this->post(route('procedure-consent.store', [$visit, $second]), $this->decision($second))->assertStatus(409);
        $this->assertDatabaseMissing('informed_consent', ['tindakan_kunjungan_id' => $second->id]);
        $this->assertSame([], Storage::disk('local')->allFiles('consents/procedures/'.$visit->id.'/'.$second->id));
    }

    public function test_reauthentication_representative_upload_validation_and_audit_atomicity(): void
    {
        [$doctor, , $visit, $procedure] = $this->context();
        $payload = [...$this->decision($procedure), 'signature_password' => 'salah', 'signer_relationship' => 'guardian'];
        unset($payload['representative_verification']);
        $this->actingAs($doctor)->post(route('procedure-consent.store', [$visit, $procedure]), $payload)
            ->assertSessionHasErrors(['signature_password', 'representative_verification']);
        $this->assertNull(session('_old_input.signature_password'));
        foreach ([
            UploadedFile::fake()->createWithContent('bukti.pdf', '<script>uji</script>')->mimeType('text/html'),
            UploadedFile::fake()->createWithContent('bukti.php', "%PDF-1.4\nSintetis\n%%EOF"),
            UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf'),
        ] as $file) {
            $this->post(route('procedure-consent.store', [$visit, $procedure]), [...$this->decision($procedure), 'evidence' => $file])->assertSessionHasErrors('evidence');
        }
        $this->mock(ClinicalAuditRecorder::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Simulasi audit gagal'));
        $this->post(route('procedure-consent.store', [$visit, $procedure]), $this->decision($procedure))->assertServerError();
        $this->assertDatabaseCount('informed_consent', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('consents/procedures'));
    }

    /** @return array{User, User, Kunjungan, TindakanKunjungan} */
    private function context(): array
    {
        Storage::fake('local');
        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'IC-UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $doctorProfile = Nakes::create(['kode' => 'IC-DOC', 'nama' => 'Dokter Consent', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        Nakes::create(['kode' => 'IC-NURSE', 'nama' => 'Perawat Consent', 'kategori' => 'medis', 'jabatan' => 'perawat', 'user_id' => $nurse->id, 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-IC', 'nama' => 'Pasien Consent Tindakan']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-IC', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctorProfile->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        Screening::create(['kunjungan_id' => $visit->id, 'submitted_at' => now(), 'prioritas_layanan' => 'normal']);
        $procedure = $visit->tindakanKunjungan()->create(['nama_tindakan_manual' => 'Eksisi lesi sintetis', 'nama_tindakan' => 'Eksisi lesi sintetis', 'kode_icd9cm' => '86.3', 'jumlah' => 1, 'execution_role' => 'dokter', 'execution_status' => 'pending', 'consent_review_required' => true]);

        return [$doctor, $nurse, $visit, $procedure];
    }

    private function decision(TindakanKunjungan $procedure): array
    {
        return [...$this->base($procedure), 'decision' => 'accepted', 'signer_name' => 'Pasien Consent Tindakan', 'signer_relationship' => 'patient', 'witness_name' => 'Saksi Sintetis', 'evidence' => $this->pdf()];
    }

    private function classification(TindakanKunjungan $procedure): array
    {
        return [...$this->base($procedure), 'decision' => 'not_required'];
    }

    private function base(TindakanKunjungan $procedure): array
    {
        return [
            'request_id' => (string) Str::uuid(), 'current_id' => null,
            'plan_hash' => app(ProcedureConsentService::class)->planHash($procedure), 'text_version' => ProcedureConsentService::VERSION,
            'signature_password' => 'password', 'explained' => true,
            'reason' => 'Keputusan sintetis setelah penjelasan lengkap.',
            'explanation' => [
                'indication' => 'Indikasi sintetis', 'method' => 'Metode tindakan sintetis', 'benefits' => 'Manfaat yang diharapkan',
                'risks' => 'Risiko telah dijelaskan', 'alternatives' => 'Alternatif telah dijelaskan',
                'without_procedure' => 'Konsekuensi tanpa tindakan', 'prognosis' => 'Prognosis telah dijelaskan',
            ],
        ];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('consent-tindakan.pdf', "%PDF-1.4\nBukti sintetis\n%%EOF");
    }
}
