<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\Models\Farmasi;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\PharmacyStaffCredential;
use App\Models\Poliklinik;
use App\Models\Resep;
use App\Models\User;
use App\PharmacyClinicalReviewService;
use App\PharmacyStaffAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PharmacyClinicalReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_staff_cannot_review_or_complete_and_ttk_requires_apoteker_review(): void
    {
        [$staff, $visit] = $this->context();
        $url = route('pelayanan.farmasi.selesai', $visit);
        $this->actingAs($staff)->post($url, $this->handover())->assertSessionHasErrors('pharmacy_review');
        $this->post(route('pelayanan.farmasi.review.store', $visit), $this->review($visit))->assertForbidden();
        $this->credential($staff, 'ttk');
        $this->post(route('pelayanan.farmasi.review.store', $visit), $this->review($visit))->assertForbidden();
        $this->post($url, $this->handover())->assertSessionHasErrors('pharmacy_review');
        $this->credential($staff, 'apoteker');
        $this->get(route('pelayanan.farmasi.review.show', $visit))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('indications.0.code', 'I10')->where('indications.0.name', 'Indikasi sintetis')->missing('anamnesis')->missing('visit.examination'));
        $this->post(route('pelayanan.farmasi.review.store', $visit), $this->review($visit))->assertRedirect()->assertSessionHasNoErrors();
        $ttk = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        Nakes::create(['kode' => 'TTK-SYNTHETIC', 'nama' => 'TTK sintetis', 'kategori' => 'medis', 'jabatan' => 'farmasi', 'user_id' => $ttk->id,
            'no_sip' => 'SIP-TTK-SYNTHETIC', 'no_str' => 'STR-TTK-SYNTHETIC', 'is_active' => true]);
        $this->credential($ttk, 'ttk');
        $this->actingAs($ttk);
        $this->post($url, [...$this->handover(), 'recipient_verified' => false])->assertSessionHasErrors('recipient_verified');
        $this->post($url, $this->handover())->assertRedirect(route('pelayanan.farmasi.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pharmacy_clinical_reviews', ['decision' => 'delivered', 'kunjungan_id' => $visit->id]);
        $this->assertDatabaseHas('farmasi', ['kunjungan_id' => $visit->id, 'dispensed_by' => $ttk->id]);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'selesai']);
        $this->post($url, $this->handover())->assertRedirect();
        $this->assertDatabaseCount('pharmacy_clinical_reviews', 2);
        $this->actingAs($staff)->post(route('pelayanan.farmasi.review.store', $visit), [
            ...$this->review($visit), 'current_id' => app(PharmacyClinicalReviewService::class)->latest($visit)->id,
            'decision' => 'follow_up', 'notes' => 'Dampak koreksi ditinjau, dokter dan pasien telah dihubungi; tindak lanjut dicatat.',
            'clarification' => 'resolved', 'clarification_reference' => 'Dokter sintetis mengonfirmasi tindak lanjut kepada pasien.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('pharmacy_clinical_reviews', 3);
        $this->assertDatabaseCount('stok_mutasi', 0);
        $this->assertDatabaseHas('farmasi', ['kunjungan_id' => $visit->id, 'status' => 'selesai', 'dispensed_by' => $ttk->id]);
    }

    public function test_pending_clarification_and_source_changes_block_approval_or_handover(): void
    {
        [$staff, $visit] = $this->context();
        $this->credential($staff, 'apoteker');
        $this->actingAs($staff)->post(route('pelayanan.farmasi.review.store', $visit), [...$this->review($visit), 'clarification' => 'pending',
            'clarification_reference' => 'Konfirmasi kepada dokter sedang ditunggu.'])->assertSessionHasErrors('decision');
        $payload = $this->review($visit);
        $visit->resep->resepObat()->first()->update(['aturan_pakai' => 'Aturan pakai direvisi dokter']);
        $this->post(route('pelayanan.farmasi.review.store', $visit), $payload)->assertSessionHasErrors('source_hash');
        $this->post(route('pelayanan.farmasi.review.store', $visit), $this->review($visit))->assertSessionHasNoErrors();
        $visit->resep->resepObat()->first()->update(['aturan_pakai' => 'Sumber kembali berubah']);
        $this->post(route('pelayanan.farmasi.selesai', $visit), $this->handover())->assertSessionHasErrors('pharmacy_review');
        $this->assertDatabaseMissing('pharmacy_clinical_reviews', ['decision' => 'delivered']);
    }

    public function test_credential_verification_is_admin_only_stale_guarded_and_invalidated_by_profile_change(): void
    {
        [$staff] = $this->context();
        $profile = Nakes::where('user_id', $staff->id)->sole();
        $url = route('master.nakes.pharmacy-credential.store', $profile);
        $payload = ['qualification' => 'apoteker', 'current_id' => null, 'reference' => 'Bukti profesi dan penugasan sintetis diperiksa.',
            'valid_until' => today()->addMonth()->toDateString(), 'documents_verified' => true, 'signature_password' => 'password'];
        $this->actingAs($staff)->post($url, $payload)->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->post($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('apoteker', app(PharmacyStaffAccess::class)->current($staff)->qualification);
        $this->post($url, $payload)->assertSessionHasErrors('current_id');
        $profile->update(['no_sip' => 'SIP berubah']);
        $this->assertNull(app(PharmacyStaffAccess::class)->current($staff));
        $this->assertDatabaseCount('pharmacy_staff_credentials', 1);
    }

    public function test_revoked_credential_invalidates_approved_review_before_handover(): void
    {
        [$staff, $visit] = $this->context();
        $this->credential($staff, 'apoteker');
        $this->actingAs($staff)->post(route('pelayanan.farmasi.review.store', $visit), $this->review($visit))->assertSessionHasNoErrors();
        $this->credential($staff, 'revoked');
        $this->assertNull(app(PharmacyStaffAccess::class)->current($staff));
        $this->post(route('pelayanan.farmasi.selesai', $visit), $this->handover())->assertSessionHasErrors('pharmacy_review');
        $this->assertDatabaseMissing('pharmacy_clinical_reviews', ['decision' => 'delivered']);
    }

    private function context(): array
    {
        $staff = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        Nakes::create(['kode' => 'FARM-REVIEW', 'nama' => 'Petugas farmasi sintetis', 'kategori' => 'medis', 'jabatan' => 'farmasi', 'user_id' => $staff->id,
            'no_sip' => 'SIP-SYNTHETIC', 'no_str' => 'STR-SYNTHETIC', 'is_active' => true]);
        $doctor = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'REVIEW', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-REVIEW', 'nama' => 'Pasien sintetis']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-REVIEW', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'farmasi']);
        $prescription = Resep::create(['no_resep' => 'RSP-REVIEW', 'kunjungan_id' => $visit->id, 'status' => 'menunggu']);
        $prescription->resepObat()->create(['nama_obat' => 'Obat resep luar sintetis', 'jumlah' => 1, 'satuan' => 'tablet', 'jenis' => 'jadi', 'is_resep_luar' => true]);
        Farmasi::create(['kunjungan_id' => $visit->id, 'resep_id' => $prescription->id, 'status' => 'diproses']);
        $exam = $visit->pemeriksaan()->create(['status' => 'draft']);
        $exam->diagnosa()->create(['kode_icd10' => 'I10', 'nama_diagnosa' => 'Indikasi sintetis', 'jenis' => 'utama']);
        $notes = app(ClinicalNoteRecorder::class);
        $notes->recordFinal($exam, $notes->snapshot($visit->fresh(), $exam), $doctor->id);
        $exam->update(['signed_at' => now(), 'signed_by_user_id' => $doctor->id, 'status' => 'selesai']);

        return [$staff, $visit->fresh()];
    }

    private function credential(User $staff, string $qualification): void
    {
        $profile = Nakes::where('user_id', $staff->id)->sole();
        PharmacyStaffCredential::create(['nakes_id' => $profile->id, 'actor_id' => $staff->id, 'qualification' => $qualification, 'recorded_at' => now(),
            'data' => ['user_id' => $staff->id, 'sip' => $profile->no_sip, 'str' => $profile->no_str, 'valid_until' => today()->addMonth()->toDateString(), 'reference' => 'Fixture kualifikasi sintetis.']]);
    }

    private function review(Kunjungan $visit): array
    {
        return ['source_hash' => app(PharmacyClinicalReviewService::class)->hash($visit->fresh()), 'current_id' => null,
            'decision' => 'approved', 'administrative_review' => true, 'pharmaceutical_review' => true, 'clinical_review' => true,
            'notes' => 'Review resep sintetis selesai dan layak diteruskan.', 'clarification' => 'not_needed', 'signature_password' => 'password'];
    }

    private function handover(): array
    {
        return ['recipient_name' => 'Penerima sintetis', 'recipient_relationship' => 'Pasien sendiri', 'recipient_verified' => true,
            'counseling_completed' => true, 'counseling_notes' => 'Petunjuk tebus resep dan penggunaan telah dipahami pasien.'];
    }
}
