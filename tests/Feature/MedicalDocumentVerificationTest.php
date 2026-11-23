<?php

namespace Tests\Feature;

use App\ClinicalAuditRecorder;
use App\ClinicalNoteRecorder;
use App\MedicalDocumentVerificationService;
use App\MedicalLetterData;
use App\Models\Kunjungan;
use App\Models\MedicalDocumentVerification;
use App\Models\MedicalInformationReleaseEvent;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\SuratMedis;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MedicalDocumentVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_release_requires_doctor_authorization_then_verified_delivery_and_keeps_encrypted_events(): void
    {
        [$doctor, $visit] = $this->context();
        $letter = $this->letter($visit);
        app(MedicalDocumentVerificationService::class)->issue($letter, $doctor);
        $registration = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $url = route('information-release.store', $visit);
        $request = ['request_id' => (string) Str::uuid(), 'base_version' => 0, 'action' => 'requested',
            'requester' => 'Pemohon sintetis', 'relationship' => 'representative', 'identity_verification' => 'Identitas wakil telah diperiksa.',
            'authority' => 'Kuasa tertulis pasien telah diperiksa.', 'recipient' => 'Penerima sintetis', 'purpose' => 'Salinan untuk kebutuhan pasien.', 'scope' => 'ringkasan'];
        $this->actingAs($registration)->post($url, $request)->assertSessionHasNoErrors();
        $this->assertStringNotContainsString('Pemohon sintetis', DB::table('medical_information_release_events')->value('data'));
        $approve = ['request_id' => $request['request_id'], 'base_version' => 1, 'action' => 'authorized', 'document_id' => $letter->id,
            'reason' => 'Dasar kuasa dan cakupan telah diperiksa.', 'signature_password' => 'password'];
        $this->post($url, $approve)->assertForbidden();
        $this->actingAs($doctor)->post($url, $approve)->assertSessionHasNoErrors();
        $this->post($url, $approve)->assertSessionHasErrors('base_version');
        $delivery = ['request_id' => $request['request_id'], 'base_version' => 2, 'action' => 'delivered', 'reason' => 'Diserahkan sesuai kuasa yang diperiksa.',
            'signature_password' => 'password', 'delivery_method' => 'hand', 'receipt' => 'Tanda terima sintetis nomor 001 diarsipkan.', 'recipient_verified' => true];
        $this->actingAs($registration)->post($url, [...$delivery, 'recipient_verified' => false])->assertSessionHasErrors('recipient_verified');
        $this->post($url, $delivery)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('medical_information_release_events', 3);
        $this->assertDatabaseHas('medical_information_release_events', ['version' => 3, 'state' => 'delivered', 'actor_id' => $registration->id]);
        $this->get(route('information-release.show', $visit))->assertInertia(fn (Assert $page) => $page->where('documents', [])->missing('clinical')->where('canAuthorize', false));
        foreach (['admin', 'perawat', 'farmasi', 'kasir'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))->get(route('information-release.show', $visit))->assertForbidden();
        }
        try {
            DB::table('medical_information_release_events')->where('version', 1)->update(['state' => 'authorized']);
            $this->fail('Raw update register harus ditolak.');
        } catch (QueryException) {
            $this->assertDatabaseHas('medical_information_release_events', ['version' => 1, 'state' => 'requested']);
        }
    }

    public function test_release_request_rolls_back_on_audit_failure(): void
    {
        [$doctor, $visit] = $this->context();
        $this->mock(ClinicalAuditRecorder::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit gagal sintetis'));
        $this->actingAs($doctor)->post(route('information-release.store', $visit), [
            'request_id' => (string) Str::uuid(), 'base_version' => 0, 'action' => 'requested', 'requester' => 'Pasien sintetis',
            'relationship' => 'patient', 'identity_verification' => 'Identitas pasien telah diperiksa.', 'authority' => 'Persetujuan tertulis spesifik diperiksa.',
            'recipient' => 'Pasien sintetis', 'purpose' => 'Salinan untuk pasien yang bersangkutan.', 'scope' => 'ringkasan',
        ])->assertServerError();
        $this->assertDatabaseCount('medical_information_release_events', 0);
    }

    public function test_release_cannot_deliver_a_document_voided_after_authorization(): void
    {
        [$doctor, $visit] = $this->context();
        $letter = $this->letter($visit);
        app(MedicalDocumentVerificationService::class)->issue($letter, $doctor);
        $root = MedicalInformationReleaseEvent::create([
            'kunjungan_id' => $visit->id, 'request_id' => (string) Str::uuid(), 'version' => 1,
            'state' => 'requested', 'actor_id' => $doctor->id, 'recorded_at' => now(),
            'data' => ['requester' => 'Pasien sintetis', 'recipient' => 'Pasien sintetis', 'scope' => 'ringkasan',
                'identity_verification' => 'Identitas pasien diperiksa.', 'authority' => 'Permintaan tertulis pasien.', 'purpose' => 'Salinan untuk pasien.'],
        ]);
        $url = route('information-release.store', $visit);
        $this->actingAs($doctor)->post($url, ['request_id' => $root->request_id, 'base_version' => 1, 'action' => 'authorized',
            'document_id' => $letter->id, 'reason' => 'Dasar pelepasan telah diperiksa.', 'signature_password' => 'password'])->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('persuratan.surat.void', $letter), ['reason' => 'Dokumen harus dibatalkan setelah review.', 'signature_password' => 'password'])->assertSessionHasNoErrors();
        $this->post($url, ['request_id' => $root->request_id, 'base_version' => 2, 'action' => 'delivered',
            'reason' => 'Penyerahan kepada pasien sesuai permintaan.', 'signature_password' => 'password', 'delivery_method' => 'hand',
            'receipt' => 'Tanda terima sintetis nomor 002.', 'recipient_verified' => true])->assertSessionHasErrors('action');
        $this->assertDatabaseCount('medical_information_release_events', 2);
        $this->post($url, ['request_id' => $root->request_id, 'base_version' => 2, 'action' => 'revoked',
            'reason' => 'Otorisasi dicabut karena dokumen dibatalkan.', 'signature_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('medical_information_release_events', ['version' => 3, 'state' => 'revoked']);
    }

    public function test_replacement_retains_original_links_new_snapshot_and_prevents_duplicate_replacement(): void
    {
        [$doctor, $visit] = $this->context();
        $original = $this->letter($visit);
        $service = app(MedicalDocumentVerificationService::class);
        $proof = $service->issue($original, $doctor);
        $originalData = $original->document_data;
        $payload = ['jenis' => $original->jenis, 'tanggal' => today()->toDateString(),
            'replacement_id' => $original->id, 'replacement_reason' => 'Perbaikan keterangan pada dokumen.', 'signature_password' => 'password'];
        $this->actingAs($doctor)->post(route('persuratan.surat.store', $visit), [...$payload, 'signature_password' => 'salah'])->assertSessionHasErrors('signature_password');
        $this->post(route('persuratan.surat.store', $visit), $payload)->assertSessionHasNoErrors();
        $replacement = SuratMedis::latest('id')->firstOrFail();
        $this->assertNotSame($original->id, $replacement->id);
        $this->assertSame($original->id, $replacement->document_data['replacement']['document_id']);
        $this->assertSame($originalData, $original->fresh()->document_data);
        $this->assertSame('void', $service->status($proof));
        $this->assertSame('valid', $service->status(MedicalDocumentVerification::where('surat_medis_id', $replacement->id)->firstOrFail()));
        $this->post(route('persuratan.surat.store', $visit), $payload)->assertSessionHasErrors('replacement_id');
        $this->assertDatabaseCount('surat_medis', 2);
        $this->assertDatabaseCount('medical_document_verification_events', 1);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'medical_letter.replaced']);
    }

    public function test_replacement_rejects_wrong_visit_and_rolls_back_when_audit_fails(): void
    {
        [$doctor, $visit] = $this->context();
        $original = $this->letter($visit);
        $service = app(MedicalDocumentVerificationService::class);
        $proof = $service->issue($original, $doctor);
        $payload = ['jenis' => 'ringkasan', 'tanggal' => today()->toDateString(),
            'replacement_id' => $original->id + 100, 'replacement_reason' => 'Alasan penggantian sintetis.', 'signature_password' => 'password'];
        $this->actingAs($doctor)->post(route('persuratan.surat.store', $visit), $payload)->assertNotFound();
        $payload['replacement_id'] = $original->id;
        $this->mock(ClinicalAuditRecorder::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit gagal sintetis'));
        $this->post(route('persuratan.surat.store', $visit), $payload)->assertServerError();
        $this->assertDatabaseCount('surat_medis', 1);
        $this->assertDatabaseCount('medical_document_verifications', 1);
        $this->assertDatabaseCount('medical_document_verification_events', 0);
        $this->assertSame('valid', $service->status($proof));
    }

    public function test_issued_letter_has_private_immutable_proof_and_public_page_exposes_no_patient_or_clinical_data(): void
    {
        [$doctor, $visit] = $this->context();
        $this->actingAs($doctor)->post(route('persuratan.surat.store', $visit), ['jenis' => 'ringkasan', 'tanggal' => today()->toDateString(), 'konten' => 'Narasi rahasia sintetis'])->assertSessionHasNoErrors();
        $proof = MedicalDocumentVerification::sole();
        $this->assertSame(64, strlen($proof->token));
        $this->assertNotSame($proof->token, DB::table('medical_document_verifications')->value('token'));
        $this->assertArrayNotHasKey('token', $proof->toArray());
        $url = route('medical-document.verify', $proof->token);
        $this->get($url)->assertInertia(fn (Assert $page) => $page->component('persuratan/verifikasi')
            ->where('status', 'valid')->missing('auth')->missing('patient')->missing('clinical')->missing('sessionSecurity'));
        auth()->logout();
        $response = $this->get($url)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $response->assertDontSee('Pasien Rahasia Sintetis')->assertDontSee('Narasi rahasia sintetis')->assertDontSee('RM-SECRET');
        $letter = SuratMedis::sole();
        $data = app(MedicalDocumentVerificationService::class)->printData($letter);
        $this->assertSame($url, $data['url']);
        $svg = base64_decode(substr($data['qrDataUrl'], strpos($data['qrDataUrl'], ',') + 1), true);
        $this->assertNotFalse(simplexml_load_string($svg));
        $this->assertStringContainsString('<svg', $svg);
        try {
            DB::table('medical_document_verifications')->where('id', $proof->id)->update(['content_hash' => str_repeat('0', 64)]);
            $this->fail('Bukti penerbitan harus append-only.');
        } catch (QueryException) {
            $this->assertSame($proof->content_hash, $proof->fresh()->content_hash);
        }
    }

    public function test_content_drift_and_void_are_visible_without_changing_the_original_and_retry_is_idempotent(): void
    {
        [$doctor, $visit] = $this->context();
        $letter = $this->letter($visit);
        $service = app(MedicalDocumentVerificationService::class);
        $proof = $service->issue($letter, $doctor);
        $letter->update(['konten' => 'Simulasi drift']);
        $this->get(route('medical-document.verify', $proof->token))->assertInertia(fn (Assert $page) => $page->where('status', 'invalid'));
        $payload = ['reason' => 'Dokumen salah dan harus dibatalkan.', 'signature_password' => 'password'];
        $this->actingAs($doctor)->post(route('persuratan.surat.void', $letter), $payload)->assertSessionHasNoErrors();
        $this->post(route('persuratan.surat.void', $letter), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('medical_document_verification_events', 1);
        $this->assertDatabaseHas('surat_medis', ['id' => $letter->id, 'konten' => 'Simulasi drift']);
        $this->get(route('medical-document.verify', $proof->token))->assertInertia(fn (Assert $page) => $page->where('status', 'void'));
        $this->get(route('persuratan.surat.cetak', $letter))->assertInertia(fn (Assert $page) => $page->where('document.verification.status', 'void'));
    }

    public function test_only_attending_doctor_can_void_with_reauthentication_and_audit_failure_rolls_back(): void
    {
        [$doctor, $visit] = $this->context();
        $letter = $this->letter($visit);
        app(MedicalDocumentVerificationService::class)->issue($letter, $doctor);
        $url = route('persuratan.surat.void', $letter);
        foreach (['admin', 'perawat', 'farmasi', 'pendaftaran'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))->post($url, [])->assertForbidden();
        }
        $this->actingAs($doctor)->post($url, ['reason' => 'Alasan pembatalan sintetis', 'signature_password' => 'salah'])->assertSessionHasErrors('signature_password');
        $this->assertNull(session('_old_input.signature_password'));
        $this->mock(ClinicalAuditRecorder::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit gagal sintetis'));
        $this->post($url, ['reason' => 'Alasan pembatalan sintetis', 'signature_password' => 'password'])->assertServerError();
        $this->assertDatabaseCount('medical_document_verification_events', 0);
    }

    public function test_unknown_tokens_return_not_found_and_public_endpoint_is_rate_limited(): void
    {
        $url = route('medical-document.verify', str_repeat('a', 64));
        for ($index = 0; $index < 30; $index++) {
            $this->get($url)->assertNotFound();
        }
        $this->get($url)->assertStatus(429);
    }

    public function test_issuance_and_proof_roll_back_together_when_audit_fails(): void
    {
        [$doctor, $visit] = $this->context();
        $this->mock(ClinicalAuditRecorder::class)->shouldReceive('record')->once()->andThrow(new \RuntimeException('Audit penerbitan gagal sintetis'));
        $this->actingAs($doctor)->post(route('persuratan.surat.store', $visit), ['jenis' => 'ringkasan', 'tanggal' => today()->toDateString()])->assertServerError();
        $this->assertDatabaseCount('surat_medis', 0);
        $this->assertDatabaseCount('medical_document_verifications', 0);
    }

    private function letter(Kunjungan $visit): SuratMedis
    {
        return $visit->suratMedis()->create(['dokter_id' => $visit->dokter_id, 'jenis' => 'ringkasan', 'nomor_surat' => 'RSM-SYNTHETIC', 'konten' => 'Narasi rahasia sintetis', 'tanggal' => today(), 'document_data' => app(MedicalLetterData::class)->snapshot($visit, $visit->dokter, today())]);
    }

    private function context(): array
    {
        $user = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DOK-PROOF', 'nama' => 'Dokter Bukti', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $user->id, 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'PROOF', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-SECRET', 'nama' => 'Pasien Rahasia Sintetis']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-PROOF', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'selesai']);
        $exam = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'anamnesis' => 'Keluhan rahasia sintetis', 'pemeriksaan_fisik' => 'Temuan sintetis']);
        $recorder = app(ClinicalNoteRecorder::class);
        $recorder->recordFinal($exam, $recorder->snapshot($visit, $exam), $user->id);
        $exam->update(['signed_at' => now(), 'signed_by_user_id' => $user->id, 'status' => 'selesai']);

        return [$user, $visit->fresh(['pemeriksaan', 'dokter'])];
    }
}
