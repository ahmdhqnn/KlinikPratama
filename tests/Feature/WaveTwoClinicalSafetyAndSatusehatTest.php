<?php

namespace Tests\Feature;

use App\GeneralConsentService;
use App\Models\ClinicalTerminology;
use App\Models\Diagnosa;
use App\Models\GeneralConsent;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\OdontogramFinding;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\User;
use App\PharmacyClinicalReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WaveTwoClinicalSafetyAndSatusehatTest extends TestCase
{
    use RefreshDatabase;

    public function test_physiological_bounds_numeric_triage_bmi_and_dental_hypertension_alert(): void
    {
        $nurseUser = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'GIGI-W2', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        Nakes::create([
            'kode' => 'PRW-GIGI-W2', 'nama' => 'Terapis Gigi W2', 'kategori' => 'medis',
            'jabatan' => 'terapis_gigi', 'poliklinik_id' => $clinic->id, 'user_id' => $nurseUser->id, 'is_active' => true,
        ]);
        $doctor = Nakes::create([
            'kode' => 'DRG-W2', 'nama' => 'drg. W2', 'kategori' => 'medis',
            'jabatan' => 'dokter', 'poliklinik_id' => $clinic->id, 'user_id' => $doctorUser->id, 'is_active' => true,
        ]);
        $patient = Pasien::create([
            'no_rm' => 'RM-W2-001', 'nama' => 'Pasien Hipertensi Gigi',
            'jenis_kelamin' => 'L', 'tanggal_lahir' => today()->subYears(42),
        ]);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-W2-001', 'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id,
            'tanggal' => today(), 'status' => 'menunggu', 'general_consent_required' => false,
        ]);
        $this->assertNotNull($visit->arrived_at);

        $basePayload = [
            'keluhan' => 'Gigi geraham berlubang',
            'lama_keluhan' => '3 hari',
            'sumber_informasi' => 'pasien',
            'status_alergi' => 'tidak_ada',
            'status_riwayat_penyakit' => 'ada',
            'risiko_medis_gigi' => ['hipertensi'],
            'dental_red_flags' => ['none'],
            'konfirmasi_riwayat' => '1',
            'td_sistole' => 0,
            'td_diastole' => 80,
            'nadi' => 80,
            'suhu' => 36.6,
            'respirasi' => 18,
            'spo2' => 0,
            'berat_badan' => 60,
            'tinggi_badan' => 165,
            'nyeri_dada' => 'tidak',
            'kejang' => 'tidak',
            'nadi_teraba' => 'teraba',
            'pola_pernapasan' => 'normal',
            'kesadaran' => 'sadar',
            'kondisi_psikiatri' => 'normal',
            'risiko_jatuh_visual' => 'rendah',
        ];

        $this->actingAs($nurseUser)->beginNursingAssessment($visit)
            ->post(route('pelayanan.screening.store', $visit), $basePayload)
            ->assertSessionHasErrors(['td_sistole', 'spo2']);

        $hypertensivePayload = [...$basePayload, 'td_sistole' => 185, 'td_diastole' => 112, 'spo2' => 98];
        $this->post(route('pelayanan.screening.store', $visit), $hypertensivePayload)
            ->assertSessionHasNoErrors();

        $screening = $visit->fresh()->screening;
        $this->assertSame('kuning', $screening->kesimpulan_triase);
        $this->assertSame('segera', $screening->prioritas_layanan);
        $this->assertSame('Normal (Ideal Asia-Pasifik)', $screening->imt_category);

        $this->actingAs($doctorUser)->get(route('pelayanan.pemeriksaan.show', $visit))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visit.screening.dentalHypertensionAlert', true)
                ->where('visit.screening.bmiCategory', 'Normal (Ideal Asia-Pasifik)'));

        $this->actingAs($nurseUser)->post(route('pelayanan.screening.store', $visit), [
            ...$hypertensivePayload,
            'td_sistole' => 230,
            'td_diastole' => 132,
            'spo2' => 86,
            'screening_revision' => $screening->revisionToken(),
            'revision_reason' => 'Pengukuran ulang menunjukkan krisis hipertensi dan desaturasi.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('merah', $screening->fresh()->kesimpulan_triase);
        $this->assertSame('darurat', $screening->fresh()->prioritas_layanan);
    }

    public function test_pregnancy_lactation_continuum_and_prescribing_allergy_cds_workflow(): void
    {
        $nurseUser = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $pharmacyUser = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM-W2', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        Nakes::create([
            'kode' => 'PRW-UMUM-W2', 'nama' => 'Perawat Umum W2', 'kategori' => 'medis',
            'jabatan' => 'perawat', 'poliklinik_id' => $clinic->id, 'user_id' => $nurseUser->id, 'is_active' => true,
        ]);
        $doctor = Nakes::create([
            'kode' => 'DR-UMUM-W2', 'nama' => 'dr. Umum W2', 'kategori' => 'medis',
            'jabatan' => 'dokter', 'poliklinik_id' => $clinic->id, 'user_id' => $doctorUser->id, 'is_active' => true,
        ]);
        $patient = Pasien::create([
            'no_rm' => 'RM-W2-002', 'nama' => 'Ibu Hamil Alergi',
            'jenis_kelamin' => 'P', 'tanggal_lahir' => today()->subYears(29),
        ]);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-W2-002', 'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id,
            'tanggal' => today(), 'status' => 'menunggu', 'general_consent_required' => false,
        ]);

        $this->actingAs($nurseUser)->beginNursingAssessment($visit)->post(route('pelayanan.screening.store', $visit), [
            'keluhan' => 'Batuk pilek 2 hari',
            'lama_keluhan' => '2 hari',
            'sumber_informasi' => 'pasien',
            'status_alergi' => 'ada',
            'riwayat_alergi' => 'Penisilin',
            'alergi_jenis' => 'Obat',
            'alergi_reaksi' => 'Urtikaria seluruh tubuh',
            'status_riwayat_penyakit' => 'tidak_ada',
            'status_kehamilan' => 'hamil',
            'usia_kehamilan_minggu' => 10,
            'hpht' => today()->subWeeks(10)->toDateString(),
            'status_menyusui' => 'ya',
            'konfirmasi_riwayat' => '1',
            'td_sistole' => 118,
            'td_diastole' => 76,
            'nadi' => 82,
            'suhu' => 36.8,
            'respirasi' => 18,
            'nyeri_dada' => 'tidak',
            'kejang' => 'tidak',
            'nadi_teraba' => 'teraba',
            'pola_pernapasan' => 'normal',
            'kesadaran' => 'sadar',
            'kondisi_psikiatri' => 'normal',
            'risiko_jatuh_visual' => 'rendah',
            'general_assessment' => ['general_condition' => 'baik', 'airway' => 'bebas', 'circulation' => 'tanpa_kelainan'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('patient_allergies', [
            'pasien_id' => $patient->id,
            'allergen_display' => 'Penisilin',
            'clinical_status' => 'active',
        ]);

        $amox = Obat::create([
            'kode' => 'OBT-AMOX', 'nama' => 'Amoxicillin 500 mg', 'kandungan' => 'Amoksisilin Trihidrat 500 mg',
            'satuan_kecil' => 'kapsul', 'stok' => 50, 'stok_minimum' => 10, 'jenis' => 'obat',
            'is_active' => true, 'is_lasa' => true, 'rute_default' => 'oral',
        ]);
        ObatBatch::factory()->create(['obat_id' => $amox->id, 'stok' => 50, 'expired_at' => today()->addDays(20)]);

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.start', $visit))->assertRedirect();
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.patient.maternal.hasRisk', true)
            ->where('visit.patient.maternal.pregnancyStatus', 'hamil')
            ->where('visit.patient.maternal.lactationStatus', 'ya')
            ->where('medicines.0.isLasa', true)
            ->whereNot('medicines.0.nearExpiryDate', null));

        // Incomplete 'racikan' option is disabled for initial release
        $this->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'obat_id' => $amox->id,
            'jumlah' => 10,
            'jenis' => 'racikan',
        ])->assertSessionHasErrors('jenis');

        // Cross-sensitivity Penisilin <-> Amoxicillin requires clinical override reason
        $this->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'obat_id' => $amox->id,
            'jumlah' => 10,
            'jenis' => 'jadi',
            'aturan_pakai' => '3 x 1 kapsul sesudah makan',
        ])->assertSessionHasErrors('allergy_override_reason');

        // With clinical override reason + structured FHIR dosage fields, prescription succeeds & audits override
        $this->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'obat_id' => $amox->id,
            'jumlah' => 15,
            'jenis' => 'jadi',
            'frekuensi' => 3,
            'dosis_sekali_pakai' => 1,
            'dosis_unit' => 'kapsul',
            'waktu_pakai' => 'sesudah_makan',
            'durasi_hari' => 5,
            'allergy_override_reason' => 'Hasil tes kulit alergi sebelumnya negatif amoksisilin oral; manfaat melebihi risiko dengan observasi.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('resep_obat', [
            'obat_id' => $amox->id,
            'aturan_pakai' => '3 x 1 kapsul sesudah makan selama 5 hari',
            'rute_pemberian' => 'oral',
            'allergy_matched_term' => 'Penisilin',
        ]);
        $this->assertDatabaseHas('clinical_audit_events', [
            'kunjungan_id' => $visit->id,
            'action' => 'clinical_cds.allergy_override',
        ]);

        // Finalize examination and verify maternal + safety flags propagate to Pharmacy & Apoteker Review
        ClinicalTerminology::create(['code_system' => 'icd10_who', 'release' => 'WHO 2016', 'code' => 'J00', 'display' => 'Nasofaringitis akut', 'code_type' => 'diagnosis', 'is_active' => true]);
        $visit->pemeriksaan->update(['anamnesis' => 'Batuk pilek 2 hari', 'pemeriksaan_fisik' => 'Faring hiperemis ringan']);
        Diagnosa::create(['pemeriksaan_id' => $visit->pemeriksaan->id, 'kode_icd10' => 'J00', 'code_system' => 'icd10_who', 'code_release' => 'WHO 2016', 'coding_status' => 'verified', 'nama_diagnosa' => 'Nasofaringitis akut', 'jenis' => 'utama']);

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'review_final_plan' => true,
            'review_nursing_assessment' => true,
            'screening_revision' => $visit->fresh()->screening->revisionToken(),
            'signature_password' => 'password',
        ])->assertRedirect();

        $this->actingAs($pharmacyUser)->get(route('pelayanan.farmasi.show', $visit))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visit.patient.maternal.hasRisk', true)
                ->where('visit.prescriptionItems.0.isLasa', true)
                ->where('visit.prescriptionItems.0.allergyMatchedTerm', 'Penisilin'));

        $source = app(PharmacyClinicalReviewService::class)->source($visit->fresh());
        $this->assertSame(['hamil', 10, 'ya'], $source['maternal']);
    }

    public function test_mixed_dentition_sound_teeth_guard_and_late_48h_addendum_governance(): void
    {
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'GIGI-PED', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $doctor = Nakes::create([
            'kode' => 'DRG-PED', 'nama' => 'drg. Pediatri', 'kategori' => 'medis',
            'jabatan' => 'dokter', 'poliklinik_id' => $clinic->id, 'user_id' => $doctorUser->id, 'is_active' => true,
        ]);
        $child = Pasien::create([
            'no_rm' => 'RM-W2-CHILD', 'nama' => 'Anak Gigi Bercampur',
            'jenis_kelamin' => 'L', 'tanggal_lahir' => today()->subYears(9),
        ]);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-W2-CHILD', 'pasien_id' => $child->id,
            'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id,
            'tanggal' => today(), 'status' => 'pemeriksaan',
        ]);
        Screening::create(['kunjungan_id' => $visit->id, 'submitted_at' => now()]);
        $exam = Pemeriksaan::create([
            'kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id,
            'anamnesis' => 'Pemeriksaan gigi rutin anak',
            'pemeriksaan_fisik' => 'Fase geligi bercampur',
            'pemeriksaan_ekstraoral' => 'Simetris, tanpa kelainan',
        ]);

        // Child (<14 yrs) requires mixed_dentition_verified before batch-marking teeth sound
        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.odontogram.sound', $visit), [
            'teeth' => ['16', '26', '36', '46'],
            'confirmed' => true,
        ])->assertSessionHasErrors('mixed_dentition_verified');

        $this->post(route('pelayanan.pemeriksaan.odontogram.sound', $visit), [
            'teeth' => ['16', '26', '36', '46'],
            'confirmed' => true,
            'mixed_dentition_verified' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, OdontogramFinding::where('kunjungan_id', $visit->id)->count());

        ClinicalTerminology::create(['code_system' => 'icd10_who', 'release' => 'WHO 2016', 'code' => 'Z01.2', 'display' => 'Pemeriksaan gigi', 'code_type' => 'diagnosis', 'is_active' => true]);
        Diagnosa::create(['pemeriksaan_id' => $exam->id, 'kode_icd10' => 'Z01.2', 'code_system' => 'icd10_who', 'code_release' => 'WHO 2016', 'coding_status' => 'verified', 'nama_diagnosa' => 'Pemeriksaan gigi', 'jenis' => 'utama']);

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            'review_final_plan' => true,
            'review_dental_assessment' => true,
            'screening_revision' => $visit->fresh()->screening->revisionToken(),
            'signature_password' => 'password',
        ])->assertRedirect();

        $this->assertNotNull($visit->fresh()->finished_at);

        // Travel >48 hours: PMK No. 24 Tahun 2022 Pasal 37 late correction governance gate
        $this->travel(49)->hours();
        $this->flushSession();

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.addendum', $visit), [
            'reason' => 'Koreksi catatan edukasi pasca evaluasi ulang rekam medis.',
            'edukasi' => 'Kontrol erupsi gigi premolar 6 bulan lagi.',
        ])->assertSessionHasErrors('late_correction_acknowledged');

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.addendum', $visit), [
            'reason' => 'Koreksi catatan edukasi pasca evaluasi ulang rekam medis.',
            'edukasi' => 'Kontrol erupsi gigi premolar 6 bulan lagi.',
            'late_correction_acknowledged' => true,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('clinical_audit_events', [
            'kunjungan_id' => $visit->id,
            'action' => 'clinical_examination.late_addendum_48h',
        ]);
    }

    public function test_satusehat_general_consent_opt_in_hmac_and_master_fields(): void
    {
        $staff = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM-GC', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'satusehat_org_id' => 'org-sub-umum-01', 'is_active' => true]);
        $patient = Pasien::create([
            'no_rm' => 'RM-GC-SS', 'nama' => 'Pasien SATUSEHAT',
            'nik' => '3171010101900001', 'ihs_number' => 'P10000001',
            'kode_provinsi' => '31', 'kode_kabupaten' => '3171', 'kode_pos' => '10110',
        ]);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-GC-SS', 'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'menunggu',
        ]);

        $this->actingAs($staff)->post(route('general-consent.store', $visit), [
            'in_person' => true,
            'request_id' => (string) Str::uuid(),
            'current_id' => null,
            'decision' => 'accepted',
            'satusehat_sharing_decision' => 'opt_in',
            'text_version' => GeneralConsentService::VERSION,
            'signer_relationship' => 'patient',
            'explained' => true,
        ])->assertSessionHasNoErrors();

        $consent = GeneralConsent::where('pasien_id', $patient->id)->sole();
        $this->assertSame('opt_in', $consent->satusehat_sharing_decision);
        $this->assertTrue(app(GeneralConsentService::class)->verifies($consent));

        $this->actingAs($admin)->put(route('setting.update'), [
            'nama_klinik' => 'Klinik Pratama Kementerian Pariwisata',
            'kode_fasyankes' => '10317101',
            'izin_operasional' => '445/KP-KEMENPAR/2026',
            'satusehat_org_id' => 'org-kemenpar-main',
            'kode_pos' => '10110',
            'pelaksana_ttv' => 'perawat',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('klinik_settings', [
            'kode_fasyankes' => '10317101',
            'satusehat_org_id' => 'org-kemenpar-main',
        ]);
    }
}
