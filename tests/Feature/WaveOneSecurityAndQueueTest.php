<?php

namespace Tests\Feature;

use App\Models\ClinicalTerminology;
use App\Models\GeneralConsent;
use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\ProcedureSignatureSession;
use App\Models\Screening;
use App\Models\TindakanKunjungan;
use App\Models\User;
use App\PersediaanRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WaveOneSecurityAndQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_clinician_signing_pin_prevents_admin_password_reset_impersonation(): void
    {
        [$admin, $doctorUser, $visit] = $this->preparedExaminationContext();

        // Signing PIN cannot equal login password
        $this->actingAs($doctorUser)->put(route('profile.signing-pin'), [
            'current_password' => 'password',
            'signing_pin' => 'password',
            'signing_pin_confirmation' => 'password',
        ])->assertSessionHasErrors('signing_pin');

        // Doctor sets personal signing PIN
        $this->put(route('profile.signing-pin'), [
            'current_password' => 'password',
            'signing_pin' => 'PIN-Dokter-8899',
            'signing_pin_confirmation' => 'PIN-Dokter-8899',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($doctorUser->fresh()->hasSigningPin());

        // Login password alone can no longer sign the medical record
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'review_final_plan' => true,
            'review_nursing_assessment' => true,
            'screening_revision' => $visit->fresh()->screening->revisionToken(),
            'signature_password' => 'password',
        ])->assertSessionHasErrors('signature_password');

        // Admin resets doctor's login password; even after password change, attacker without PIN cannot sign
        $this->actingAs($admin)->post(route('users.reset-password', $doctorUser), [
            'password' => 'Temporary123!',
        ])->assertSessionHasNoErrors();

        $this->post('/logout');
        $this->post('/login', ['email' => $doctorUser->email, 'password' => 'Temporary123!'])
            ->assertRedirect(route('profile.show'));

        $this->put(route('profile.password'), [
            'current_password' => 'Temporary123!',
            'password' => 'NewLoginPass456!',
            'password_confirmation' => 'NewLoginPass456!',
        ])->assertSessionHasNoErrors();

        // Attempt to change signing PIN without knowing old signing PIN fails
        $this->put(route('profile.signing-pin'), [
            'current_password' => 'NewLoginPass456!',
            'current_signing_pin' => 'Salah-PIN',
            'signing_pin' => 'PinBaru9999',
            'signing_pin_confirmation' => 'PinBaru9999',
        ])->assertSessionHasErrors('current_signing_pin');

        // Attempt to sign exam with new login password fails
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'review_final_plan' => true,
            'review_nursing_assessment' => true,
            'screening_revision' => $visit->fresh()->screening->revisionToken(),
            'signature_password' => 'NewLoginPass456!',
        ])->assertSessionHasErrors('signature_password');

        // Real doctor with personal signing PIN succeeds
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'review_final_plan' => true,
            'review_nursing_assessment' => true,
            'screening_revision' => $visit->fresh()->screening->revisionToken(),
            'signature_password' => 'PIN-Dokter-8899',
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($visit->fresh()->pemeriksaan->signed_at);
    }

    public function test_admin_signing_pin_reset_blocks_signing_until_clinician_enrolls_new_pin(): void
    {
        [$admin, $doctorUser, $visit] = $this->preparedExaminationContext();

        $this->actingAs($admin)->post(route('users.reset-signing-pin', $doctorUser))
            ->assertSessionHasNoErrors();

        $this->assertTrue($doctorUser->fresh()->must_setup_signing_pin);

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'review_final_plan' => true,
            'review_nursing_assessment' => true,
            'screening_revision' => $visit->fresh()->screening->revisionToken(),
            'signature_password' => 'password',
        ])->assertSessionHasErrors('signature_password');

        $this->put(route('profile.signing-pin'), [
            'current_password' => 'password',
            'signing_pin' => 'PinPribadi2026!',
            'signing_pin_confirmation' => 'PinPribadi2026!',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($doctorUser->fresh()->must_setup_signing_pin);

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'review_final_plan' => true,
            'review_nursing_assessment' => true,
            'screening_revision' => $visit->fresh()->screening->revisionToken(),
            'signature_password' => 'PinPribadi2026!',
        ])->assertSessionHasNoErrors();
    }

    public function test_historical_signed_rme_retains_original_signature_image_after_profile_update(): void
    {
        Storage::fake('local');
        [, $doctorUser, $visit] = $this->preparedExaminationContext();

        $this->actingAs($doctorUser)->post(route('profile.signature.upload'), [
            'signature' => UploadedFile::fake()->image('sig-v1.png', 120, 40),
        ])->assertSessionHasNoErrors();
        $originalPath = $doctorUser->fresh()->signature_path;
        $originalBytes = Storage::disk('local')->get($originalPath);

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'review_final_plan' => true,
            'review_nursing_assessment' => true,
            'screening_revision' => $visit->fresh()->screening->revisionToken(),
            'signature_password' => 'password',
        ])->assertSessionHasNoErrors();

        // Replace signature with different dimensions/bytes, then delete it
        $this->post(route('profile.signature.upload'), [
            'signature' => UploadedFile::fake()->image('sig-v2.png', 200, 80),
        ])->assertSessionHasNoErrors();
        $this->delete(route('profile.signature.delete'))->assertSessionHasNoErrors();

        $expectedDataUrl = 'data:image/png;base64,'.base64_encode($originalBytes);
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visits.0.examination.signatureDataUrl', $expectedDataUrl)
            );
    }

    public function test_admin_medicine_stock_page_masks_clinical_patient_identifiers(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $pharmacist = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-SECRET-999', 'nama' => 'Pasien Rahasia']);
        $medicine = Obat::create([
            'kode' => 'OBT-MASK', 'nama' => 'Obat Sensitif',
            'satuan_kecil' => 'Tablet', 'konversi_satuan' => 1,
            'stok' => 0, 'stok_minimum' => 5, 'jenis' => 'obat',
            'boleh_diberikan_langsung' => true, 'is_active' => true,
        ]);

        $recorder = app(PersediaanRecorder::class);
        $recorder->receive($medicine->id, [
            'nomor_batch' => 'BATCH-MASK-1',
            'expired_at' => today()->addYear()->toDateString(),
            'jumlah' => 20,
            'sumber' => 'distribusi',
            'referensi' => 'DO-001',
        ], $admin);
        $recorder->issueWithoutVisit($medicine->id, 2, $pharmacist, $patient, 'FarmasiMandiri', 77);

        $response = $this->actingAs($admin)->get(route('master.obat.stok', $medicine))->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('movements.data.0.description', 'Mutasi pelayanan tercatat')
        );
        $this->assertStringNotContainsString('RM-SECRET-999', $response->getContent());
    }

    public function test_procedure_signature_qr_session_locks_after_five_wrong_pair_codes(): void
    {
        [, $doctorUser, $visit] = $this->preparedExaminationContext();
        $procedure = TindakanKunjungan::create([
            'kunjungan_id' => $visit->id,
            'dokter_id' => $visit->dokter_id,
            'nama_tindakan' => 'Ekstraksi Gigi',
            'jumlah' => 1,
            'execution_role' => 'dokter',
            'execution_status' => 'pending',
            'consent_review_required' => true,
            'coding_status' => 'not_applicable',
        ]);

        $rawToken = bin2hex(random_bytes(32));
        $session = new ProcedureSignatureSession;
        $session->forceFill([
            'procedure_id' => $procedure->id,
            'doctor_id' => $doctorUser->id,
            'token_hash' => hash('sha256', $rawToken),
            'credentials' => ['code' => '654321'],
            'snapshot' => ['plan' => ['name' => 'Ekstraksi Gigi']],
            'expires_at' => now()->addMinutes(15),
        ])->save();

        for ($i = 1; $i <= 4; $i++) {
            $this->post(route('procedure-signature.pair', $rawToken), ['pair_code' => '000000'])
                ->assertSessionHasErrors('pair_code');
        }

        $this->post(route('procedure-signature.pair', $rawToken), ['pair_code' => '000000'])
            ->assertStatus(410);

        $this->assertNotNull($session->fresh()->cancelled_at);
        $this->assertDatabaseHas('clinical_audit_events', [
            'action' => 'procedure_signature.pair_locked',
            'kunjungan_id' => $visit->id,
            'outcome' => 'denied',
        ]);
    }

    public function test_queue_numbers_persist_across_cancellations_and_reservation_confirmations(): void
    {
        $staff = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'POL-Q', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DR-Q', 'nama' => 'dr. Antrean', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        JadwalDokter::create([
            'dokter_id' => $doctor->id,
            'poliklinik_id' => $clinic->id,
            'hari' => strtolower(today()->locale('id')->dayName),
            'berlaku_mulai' => '2020-01-01',
            'jam_mulai' => '08:00',
            'jam_selesai' => '16:00',
            'is_active' => true,
        ]);

        $p1 = Pasien::create(['no_rm' => 'RM-Q1', 'nama' => 'Pasien Satu']);
        $p2 = Pasien::create(['no_rm' => 'RM-Q2', 'nama' => 'Pasien Dua']);
        $p3 = Pasien::create(['no_rm' => 'RM-Q3', 'nama' => 'Pasien Kontrol']);

        // Pre-existing unconfirmed follow-up created earlier
        $followUp = Kunjungan::create([
            'no_kunjungan' => 'KNJ-OLD-RES',
            'pasien_id' => $p3->id,
            'poliklinik_id' => $clinic->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today()->toDateString(),
            'status' => 'menunggu',
            'jenis_pasien' => 'lama',
            'kontrol_pending' => true,
        ]);

        $this->actingAs($staff);
        $this->post(route('pendaftaran.store-pasien-lama'), [
            'pasien_id' => $p1->id,
            'poliklinik_id' => $clinic->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today()->toDateString(),
        ])->assertRedirect();

        $this->post(route('pendaftaran.store-pasien-lama'), [
            'pasien_id' => $p2->id,
            'poliklinik_id' => $clinic->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today()->toDateString(),
        ])->assertRedirect();

        $v1 = Kunjungan::where('pasien_id', $p1->id)->firstOrFail();
        $v2 = Kunjungan::where('pasien_id', $p2->id)->firstOrFail();
        $this->assertSame(1, $v1->no_antrian);
        $this->assertSame(2, $v2->no_antrian);

        // Cancel Visit #1 -> Visit #2 must KEEP Queue #2!
        $this->post(route('pendaftaran.batal-kunjungan', $v1), ['reason' => 'Pasien batal hadir'])->assertRedirect();
        $this->get(route('pendaftaran.cetak-antrian', $v2))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('ticket.queueNumber', 2));

        // Confirm older follow-up reservation -> gets Queue #3, NOT Queue #1!
        $this->put(route('pendaftaran.update-kunjungan', $followUp), [
            'poliklinik_id' => $clinic->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today()->toDateString(),
        ])->assertRedirect();

        $this->assertSame(3, $followUp->fresh()->no_antrian);
        $this->get(route('pendaftaran.cetak-antrian', $followUp))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('ticket.queueNumber', 3));
    }

    /**
     * @return array{0: User, 1: User, 2: Kunjungan}
     */
    private function preparedExaminationContext(): array
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'POL-W1', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $doctor = Nakes::create([
            'kode' => 'DR-W1',
            'nama' => 'dr. Gelombang Satu',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'no_sip' => 'SIP-W1-2026',
            'no_str' => 'STR-W1-2026',
            'user_id' => $doctorUser->id,
            'is_active' => true,
        ]);
        $patient = Pasien::create(['no_rm' => 'RM-W1-001', 'nama' => 'Pasien Uji Gelombang Satu']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-W1-001',
            'no_antrian' => 1,
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today()->toDateString(),
            'status' => 'pemeriksaan',
            'jenis_pasien' => 'lama',
        ]);

        GeneralConsent::create([
            'pasien_id' => $patient->id,
            'kunjungan_id' => $visit->id,
            'request_id' => (string) Str::uuid(),
            'request_hash' => str_repeat('a', 64),
            'decision' => 'accepted',
            'text_version' => 'GC-1',
            'text_snapshot' => 'Persetujuan umum sintetis',
            'signer_name' => $patient->nama,
            'signer_relationship' => 'patient',
            'recorded_by' => $admin->id,
            'recorded_at' => now(),
        ]);

        Screening::create([
            'kunjungan_id' => $visit->id,
            'keluhan' => 'Demam ringan 2 hari',
            'td_sistole' => 120,
            'td_diastole' => 80,
            'nadi' => 80,
            'suhu' => 37.2,
            'respirasi' => 18,
            'spo2' => 98,
            'status_alergi' => 'tidak_ada',
            'riwayat_alergi' => 'Tidak ada alergi',
            'prioritas_layanan' => 'reguler',
            'kesimpulan_triase' => 'hijau',
            'submitted_at' => now(),
            'submitted_by_user_id' => $admin->id,
        ]);

        $term = ClinicalTerminology::create([
            'code_system' => 'icd10_who',
            'code_type' => 'diagnosis',
            'release' => '2019',
            'code' => 'J00',
            'display' => 'Acute nasopharyngitis [common cold]',
            'is_active' => true,
        ]);

        $exam = Pemeriksaan::create([
            'kunjungan_id' => $visit->id,
            'dokter_id' => $doctor->id,
            'anamnesis' => 'Keluhan pilek dan demam ringan.',
            'pemeriksaan_fisik' => 'Faring hiperemis ringan, paru vesikuler.',
            'catatan' => 'Istirahat cukup dan terapi simtomatik.',
            'edukasi' => 'Minum air hangat dan kontrol bila demam >3 hari.',
            'kondisi_akhir' => 'Stabil',
            'disposisi' => 'pulang',
            'status' => 'draft',
            'coding_status' => 'verified',
            'started_at' => now(),
        ]);

        $exam->diagnosa()->create([
            'kode_icd10' => $term->code,
            'nama_diagnosa' => $term->display,
            'jenis' => 'utama',
            'code_system' => 'icd10_who',
            'code_release' => '2019',
            'coding_status' => 'verified',
            'coding_verified_by' => $doctorUser->id,
            'coding_verified_at' => now(),
        ]);

        return [$admin, $doctorUser, $visit];
    }
}
