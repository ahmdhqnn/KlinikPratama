<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\Models\ClinicalNoteVersion;
use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\OdontogramFinding;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OdontogramFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_dental_findings_and_tooth_location_are_versioned_after_finalization(): void
    {
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create([
            'kode' => 'DOK-GIGI-UAT', 'nama' => 'drg. UAT', 'kategori' => 'medis',
            'jabatan' => 'dokter', 'user_id' => $doctorUser->id, 'is_active' => true,
        ]);
        $clinic = Poliklinik::create(['kode' => 'GIGI', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-GIGI-UAT', 'nama' => 'Pasien Gigi UAT']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-GIGI-UAT', 'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id,
            'tanggal' => today(), 'status' => 'pemeriksaan',
        ]);
        $examination = Pemeriksaan::create([
            'kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id,
            'anamnesis' => 'Nyeri geraham kanan bawah',
            'pemeriksaan_fisik' => 'Karies oklusal gigi 46',
            'pemeriksaan_ekstraoral' => 'Tidak ada pembengkakan wajah',
            'oral_hygiene_index' => 1.25,
        ]);
        Screening::create([
            'kunjungan_id' => $visit->id,
            'keluhan' => 'Sakit gigi saat makan manis',
            'submitted_at' => now(),
            'lokasi_nyeri_gigi' => 'Geraham kanan bawah',
            'pemicu_nyeri_gigi' => ['manis', 'dingin'],
            'durasi_keluhan_gigi' => '3 hari',
            'risiko_medis_gigi' => ['diabetes', 'hipertensi'],
            'riwayat_infeksi_gigi' => ['hepatitis'],
        ]);
        Diagnosa::create([
            'pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9',
            'nama_diagnosa' => 'Karies gigi', 'jenis' => 'utama',
        ]);

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertUnprocessable();
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '19', 'surface' => 'O', 'finding_code' => 'caries', 'details' => $this->cariesDetails(),
        ])->assertSessionHasErrors('tooth_fdi');
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'missing',
        ])->assertSessionHasErrors('surface');

        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => $this->cariesDetails(), 'notes' => 'Kavitas kecil',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('odontogram_findings', [
            'kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'recorded_by_user_id' => $doctorUser->id,
        ]);
        $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), [
            'tooth_fdi' => '46', 'surface' => 'O',
        ])->assertRedirect();
        $this->assertDatabaseMissing('odontogram_findings', [
            'kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O',
        ]);
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => $this->cariesDetails(), 'notes' => 'Kavitas kecil',
        ])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '36', 'surface' => 'D', 'finding_code' => 'caries', 'details' => $this->cariesDetails(),
        ])->assertSessionHasNoErrors();

        $treatment = Tindakan::create([
            'kode' => 'TDK-GIGI-UAT', 'nama' => 'Restorasi gigi', 'kategori' => 'medis',
            'poliklinik_id' => $clinic->id, 'is_active' => true,
        ]);
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), [
            'tindakan_id' => $treatment->id, 'jumlah' => 1, 'tooth_fdis' => ['46', '36'],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tindakan_kunjungan', ['kunjungan_id' => $visit->id, 'tooth_fdi' => '46']);
        $this->assertDatabaseHas('tindakan_kunjungan', ['kunjungan_id' => $visit->id, 'tooth_fdi' => '36']);
        $this->assertDatabaseCount('tindakan_kunjungan', 2);
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), [
            'tindakan_id' => $treatment->id, 'jumlah' => 1, 'tooth_fdis' => ['48'],
        ])->assertSessionHasErrors('tooth_fdis');
        $this->assertDatabaseCount('tindakan_kunjungan', 2);

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertRedirect();
        $final = ClinicalNoteVersion::where('pemeriksaan_id', $examination->id)->sole();
        $this->assertSame('46', $final->payload['odontogram'][1]['tooth_fdi']);
        $this->assertSame('caries', $final->payload['odontogram'][1]['finding_code']);
        $this->assertSame('46', $final->payload['treatments'][0]['tooth_fdi']);

        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'restored',
        ])->assertUnprocessable();
        $this->post(route('pelayanan.pemeriksaan.odontogram.addendum', $visit), [
            'reason' => 'Koreksi setelah evaluasi ulang', 'tooth_fdi' => '46',
            'surface' => 'O', 'finding_code' => 'restored', 'notes' => 'Telah direstorasi',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('odontogram_findings', ['kunjungan_id' => $visit->id, 'finding_code' => 'caries']);
        $this->assertDatabaseCount('clinical_note_versions', 2);
        $addendum = ClinicalNoteVersion::where('version', 2)->sole();
        $this->assertSame('restored', $addendum->payload['odontogram'][1]['finding_code']);
        $this->assertSame($final->content_hash, $addendum->previous_hash);

        $otherDoctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        Nakes::create([
            'kode' => 'DOK-GIGI-LAIN', 'nama' => 'drg. Lain', 'kategori' => 'medis',
            'jabatan' => 'dokter', 'user_id' => $otherDoctorUser->id, 'is_active' => true,
        ]);
        $unauthorizedAddendum = [
            'reason' => 'Koreksi tanpa penugasan klinis', 'tooth_fdi' => '46',
            'surface' => 'O', 'finding_code' => 'caries', 'details' => $this->cariesDetails(),
        ];
        $this->actingAs($otherDoctorUser)->post(route('pelayanan.pemeriksaan.odontogram.addendum', $visit), $unauthorizedAddendum)->assertForbidden();
        $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'reason' => 'Salah catat temuan gigi ini',
        ])->assertForbidden();
        $adminUser = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($adminUser)->post(route('pelayanan.pemeriksaan.odontogram.addendum', $visit), $unauthorizedAddendum)->assertForbidden();
        $this->actingAs($doctorUser);

        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.odontogram.1.toothFdi', '46')
            ->where('visit.odontogram.1.findingCode', 'restored')
            ->where('visit.screening.dentalPainLocation', 'Geraham kanan bawah')
            ->where('visit.screening.dentalMedicalRisks.0', 'diabetes')
            ->where('visit.examination.extraoralExam', 'Tidak ada pembengkakan wajah')
            ->where('visit.examination.oralHygieneIndex', 1.25)
            ->where('visit.examination.versions.0.odontogram.0.findingCode', 'caries')
            ->where('visit.examination.versions.1.odontogram.1.findingCode', 'restored')
        );
        $this->get(route('pelayanan.pasien.rekam-medis', $patient))->assertInertia(fn (Assert $page) => $page
            ->where('patient.age', null)
            ->where('visits.0.clinicType', 'gigi')
            ->where('visits.0.odontogram.1.findingCode', 'restored')
            ->where('visits.0.examination.versions.0.odontogram.0.findingCode', 'caries')
            ->where('visits.0.treatments.0.toothFdi', '46')
        );

        $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'reason' => 'salah',
        ])->assertSessionHasErrors('reason');
        $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'reason' => 'Dokter keliru mencatat permukaan ini',
        ])->assertSessionHasNoErrors();
        $correctedVersion = ClinicalNoteVersion::where('pemeriksaan_id', $examination->id)->latest('version')->firstOrFail();
        $this->assertCount(1, $correctedVersion->payload['odontogram']);
        $this->assertSame('36', $correctedVersion->payload['odontogram'][0]['tooth_fdi']);
        $this->assertSame('restored', ClinicalNoteVersion::where('pemeriksaan_id', $examination->id)->where('version', 2)->sole()->payload['odontogram'][1]['finding_code']);
        $this->assertSame($addendum->content_hash, $correctedVersion->previous_hash);

        $otherClinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $otherVisit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-UMUM-UAT', 'pasien_id' => $patient->id,
            'poliklinik_id' => $otherClinic->id, 'dokter_id' => $doctor->id,
            'tanggal' => today(), 'status' => 'pemeriksaan',
        ]);
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $otherVisit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => $this->cariesDetails(),
        ])->assertUnprocessable();

        $this->assertSame(2, OdontogramFinding::count());
    }

    public function test_dental_external_prescription_finishes_without_an_internal_pharmacy_queue(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        Diagnosa::create([
            'pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9',
            'nama_diagnosa' => 'Karies', 'jenis' => 'utama',
        ]);
        OdontogramFinding::create([
            'kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => $this->cariesDetails(), 'recorded_by_user_id' => $user->id,
        ]);
        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', ['kunjungan' => $visit->id, 'tab' => 'odontogram']))
            ->assertInertia(fn (Assert $page) => $page->where('initialTab', 'odontogram')->where('auth.user.clinicType', 'gigi'));

        $this->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'nama_obat' => 'Obat luar', 'jumlah' => 3, 'satuan' => 'tablet',
            'aturan_pakai' => 'Sesuai instruksi dokter', 'jenis' => 'jadi', 'is_resep_luar' => true,
        ])->assertSessionHasNoErrors();

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])
            ->assertRedirect(route('pelayanan.pemeriksaan.index'));

        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'selesai']);
        $this->assertDatabaseHas('resep', ['kunjungan_id' => $visit->id, 'status' => 'selesai']);
        $this->assertDatabaseMissing('farmasi', ['kunjungan_id' => $visit->id]);
        $this->assertDatabaseHas('pemeriksaan', ['id' => $examination->id, 'status' => 'selesai']);
        $this->assertSame('Obat luar', ClinicalNoteVersion::sole()->payload['prescriptions'][0]['name']);
    }

    public function test_new_primary_diagnosis_replaces_the_previous_primary_without_losing_it(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        $previous = Diagnosa::create([
            'pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9',
            'nama_diagnosa' => 'Karies', 'jenis' => 'utama',
        ]);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [
            'kode_icd10' => 'K04.0', 'code_system' => 'icd10_who', 'nama_diagnosa' => 'Pulpitis', 'jenis' => 'utama',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('diagnosa', ['id' => $previous->id, 'jenis' => 'tambahan']);
        $this->assertDatabaseHas('diagnosa', ['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K04.0', 'jenis' => 'utama']);
        $this->assertSame(1, $examination->diagnosa()->where('jenis', 'utama')->count());
    }

    public function test_duplicate_diagnosis_is_rejected_without_demoting_the_existing_primary(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        $primary = Diagnosa::create([
            'pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9', 'code_system' => 'icd10_who',
            'nama_diagnosa' => 'Karies', 'jenis' => 'utama',
        ]);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [
            'kode_icd10' => 'K02.9', 'code_system' => 'icd10_who', 'nama_diagnosa' => 'Karies', 'jenis' => 'utama',
        ])->assertSessionHasErrors(['kode_icd10' => 'Diagnosis ini sudah tercatat. Hapus entri sebelumnya jika perlu dikoreksi.']);

        $this->assertDatabaseHas('diagnosa', ['id' => $primary->id, 'jenis' => 'utama']);
        $this->assertDatabaseCount('diagnosa', 1);
    }

    public function test_processed_prescription_cannot_accept_new_items_during_examination(): void
    {
        [$user, $visit] = $this->dentalExamination();
        $prescription = $visit->resep()->create(['no_resep' => 'RSP-PROCESSED', 'status' => 'diproses']);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'nama_obat' => 'Obat luar', 'jumlah' => 3, 'jenis' => 'jadi', 'is_resep_luar' => true,
        ])->assertUnprocessable();

        $this->assertDatabaseMissing('resep_obat', ['resep_id' => $prescription->id]);
        $this->assertDatabaseHas('resep', ['id' => $prescription->id, 'status' => 'diproses']);
    }

    public function test_ambiguous_legacy_primary_diagnoses_cannot_be_finalized(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9', 'nama_diagnosa' => 'Karies', 'jenis' => 'utama']);
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K04.0', 'nama_diagnosa' => 'Pulpitis', 'jenis' => 'utama']);
        OdontogramFinding::create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => $this->cariesDetails(), 'recorded_by_user_id' => $user->id]);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertUnprocessable();

        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'pemeriksaan']);
        $this->assertDatabaseMissing('clinical_note_versions', ['pemeriksaan_id' => $examination->id]);
    }

    public function test_retired_ohis_field_cannot_change_a_signed_record_via_addendum(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        $examination->update(['oral_hygiene_index' => 1.25]);
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9', 'nama_diagnosa' => 'Karies', 'jenis' => 'utama']);
        OdontogramFinding::create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => $this->cariesDetails(), 'recorded_by_user_id' => $user->id]);
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertRedirect();

        $this->post(route('pelayanan.pemeriksaan.addendum', $visit), [
            'reason' => 'Koreksi nilai kebersihan mulut', 'oral_hygiene_index' => 4.5,
        ])->assertSessionHasErrors(['reason' => 'Isi setidaknya satu bagian catatan yang dikoreksi.']);

        $this->assertDatabaseCount('clinical_note_versions', 1);
        $this->assertSame('1.25', $examination->fresh()->oral_hygiene_index);
    }

    /**
     * @return array{User, Kunjungan, Pemeriksaan}
     */
    public function test_dental_signing_requires_full_oral_examination_review_and_complete_lesion_details(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9', 'nama_diagnosa' => 'Karies', 'jenis' => 'utama']);
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries'])->assertSessionHasNoErrors();
        $examination->update(['pemeriksaan_ekstraoral' => null]);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertUnprocessable();
        $examination->update(['pemeriksaan_ekstraoral' => 'Simetris, tanpa pembengkakan, bukaan mulut baik']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password'])->assertSessionHasErrors('review_dental_assessment');
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertSessionHasErrors('dental_findings');
        $this->assertDatabaseMissing('clinical_note_versions', ['pemeriksaan_id' => $examination->id]);
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => $this->cariesDetails()])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertSessionHasNoErrors();
        $final = ClinicalNoteVersion::sole();
        $this->assertSame($user->id, $final->payload['dental_review']['reviewed_by']);
        $this->assertSame('5', $final->payload['odontogram'][0]['details']['icdas']);
        $this->assertSame('selesai', $visit->fresh()->status);
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertInertia(fn (Assert $page) => $page->where('visits.0.odontogram.0.details.activity', 'active'));
        $this->post(route('pelayanan.pemeriksaan.odontogram.addendum', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'reason' => 'Evaluasi ulang temuan klinis'])->assertSessionHasErrors('details');
        $this->post(route('pelayanan.pemeriksaan.odontogram.addendum', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'reason' => 'Evaluasi ulang aktivitas lesi', 'details' => [...$this->cariesDetails(), 'activity' => 'inactive']])->assertSessionHasNoErrors();
        $this->assertSame('active', $final->fresh()->payload['odontogram'][0]['details']['activity']);
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page->where('visit.odontogram.0.details.activity', 'inactive'));
    }

    public function test_dental_findings_reject_invalid_details_and_conflicting_whole_tooth_status(): void
    {
        [$user, $visit] = $this->dentalExamination();
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => ['icdas' => '9']])->assertSessionHasErrors('details.icdas');
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'W', 'finding_code' => 'missing'])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries'])->assertSessionHasErrors('finding_code');
        $this->assertDatabaseCount('odontogram_findings', 1);
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '36', 'surface' => 'O', 'finding_code' => 'restored'])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '36', 'surface' => 'W', 'finding_code' => 'unerupted'])->assertSessionHasErrors('finding_code');
        $this->assertDatabaseCount('odontogram_findings', 2);
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $this->actingAs($nurse)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '36', 'surface' => 'O', 'finding_code' => 'caries'])->assertForbidden();
    }

    public function test_sound_batch_preserves_existing_findings_and_rejects_unconfirmed_or_invalid_teeth(): void
    {
        [$user, $visit] = $this->dentalExamination();
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => [...$this->cariesDetails(), 'vitality' => 'positive', 'percussion' => 'negative', 'mobility' => 'M0']])->assertSessionHasNoErrors();
        $this->assertSame('positive', $visit->odontogramFindings()->sole()->details['vitality']);
        $this->post(route('pelayanan.pemeriksaan.odontogram.sound', $visit), ['teeth' => ['45'], 'confirmed' => false])->assertSessionHasErrors('confirmed');
        $this->post(route('pelayanan.pemeriksaan.odontogram.sound', $visit), ['teeth' => ['19'], 'confirmed' => true])->assertSessionHasErrors('teeth.0');
        $this->post(route('pelayanan.pemeriksaan.odontogram.sound', $visit), ['teeth' => ['46', '45', '44'], 'confirmed' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('odontogram_findings', ['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'finding_code' => 'caries']);
        $this->assertDatabaseHas('odontogram_findings', ['kunjungan_id' => $visit->id, 'tooth_fdi' => '45', 'finding_code' => 'sound']);
        $this->post(route('pelayanan.pemeriksaan.odontogram.sound', $visit), ['teeth' => ['45'], 'confirmed' => true])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('odontogram_findings', 3);
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '44', 'surface' => 'W', 'finding_code' => 'sound', 'details' => ['vitality' => 'guessed']])->assertSessionHasErrors('details.vitality');
        $this->actingAs(User::factory()->create(['role' => 'perawat', 'is_active' => true]))->post(route('pelayanan.pemeriksaan.odontogram.sound', $visit), ['teeth' => ['43'], 'confirmed' => true])->assertForbidden();
    }

    public function test_multiple_surfaces_are_saved_atomically_and_corrected_in_one_signed_version(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.1', 'nama_diagnosa' => 'Karies dentin', 'jenis' => 'utama']);
        $payload = ['tooth_fdi' => '46', 'surface' => 'M', 'surfaces' => ['M', 'O', 'D'], 'finding_code' => 'caries', 'details' => $this->cariesDetails()];
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [...$payload, 'surfaces' => ['M', 'W']])->assertSessionHasErrors('surface');
        $this->assertDatabaseCount('odontogram_findings', 0);
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->assertSame(['D', 'M', 'O'], $visit->odontogramFindings()->orderBy('surface')->pluck('surface')->all());
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.odontogram.addendum', $visit), [...$payload, 'finding_code' => 'restored', 'reason' => 'Restorasi MOD telah dievaluasi ulang'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('clinical_note_versions', 2);
        $versions = $examination->clinicalNoteVersions()->orderBy('version')->get();
        $this->assertSame(['caries', 'caries', 'caries'], array_column($versions[0]->payload['odontogram'], 'finding_code'));
        $this->assertSame(['restored', 'restored', 'restored'], array_column($versions[1]->payload['odontogram'], 'finding_code'));
    }

    public function test_dental_signing_allows_omitting_optional_icdas_and_black_class(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9', 'nama_diagnosa' => 'Karies', 'jenis' => 'utama']);
        $details = $this->cariesDetails();
        unset($details['icdas']);
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'W', 'finding_code' => 'caries'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => $details])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('odontogram_findings', ['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'W']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertSessionHasNoErrors();
        $this->assertNotNull($examination->fresh()->signed_at);
        $this->assertSame($details, ClinicalNoteVersion::sole()->payload['odontogram'][0]['details']);
    }

    public function test_dental_signing_identifies_the_tooth_and_missing_fields_without_signing(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9', 'nama_diagnosa' => 'Karies', 'jenis' => 'utama']);
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '28', 'surface' => 'W', 'finding_code' => 'caries'])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])
            ->assertSessionHasErrors(['dental_findings' => 'Gigi 28: lengkapi permukaan spesifik (M/O/D/V/L), aktivitas lesi, jenis / lokasi lesi, kedalaman, diagnosis pulpa, diagnosis apikal, hasil pemeriksaan dan rencana perawatan.']);
        $this->assertNull($examination->fresh()->signed_at);
        $this->assertDatabaseCount('clinical_note_versions', 0);
    }

    public function test_selected_odontogram_removal_requires_confirmation_and_preserves_unselected_findings(): void
    {
        [$user, $visit] = $this->dentalExamination();
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => $this->cariesDetails()])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '36', 'surface' => 'W', 'finding_code' => 'sound'])->assertSessionHasNoErrors();
        $otherVisit = Kunjungan::create(['no_kunjungan' => 'KNJ-GIGI-LAIN', 'pasien_id' => $visit->pasien_id, 'poliklinik_id' => $visit->poliklinik_id, 'dokter_id' => $visit->dokter_id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        $other = OdontogramFinding::factory()->create(['kunjungan_id' => $otherVisit->id, 'tooth_fdi' => '35']);
        $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), ['findings' => [['tooth_fdi' => '46', 'surface' => 'O']]])->assertSessionHasErrors('confirmed');
        $this->assertSame(2, $visit->odontogramFindings()->count());
        $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), ['all' => true, 'confirmed' => true])->assertSessionHasErrors('all');
        $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), ['findings' => [['tooth_fdi' => '46', 'surface' => 'O'], ['tooth_fdi' => '35', 'surface' => 'O']], 'confirmed' => true])->assertSessionHasErrors('findings');
        $this->assertSame(2, $visit->odontogramFindings()->count());
        $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), ['findings' => [['tooth_fdi' => '46', 'surface' => 'O']], 'confirmed' => true])->assertSessionHasNoErrors();
        $this->assertSame(1, $visit->odontogramFindings()->count());
        $this->assertDatabaseHas('odontogram_findings', ['kunjungan_id' => $visit->id, 'tooth_fdi' => '36', 'surface' => 'W']);
        $this->assertModelExists($other);
        $this->assertDatabaseHas('clinical_audit_events', ['kunjungan_id' => $visit->id, 'action' => 'odontogram.draft_undo_selected']);
    }

    public function test_bulk_removal_of_final_odontogram_records_one_addendum_and_preserves_original(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9', 'nama_diagnosa' => 'Karies', 'jenis' => 'utama']);
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'M', 'surfaces' => ['M', 'O', 'D'], 'finding_code' => 'caries', 'details' => $this->cariesDetails()])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertSessionHasNoErrors();
        $final = ClinicalNoteVersion::sole();
        $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), ['findings' => [['tooth_fdi' => '46', 'surface' => 'M'], ['tooth_fdi' => '46', 'surface' => 'O']], 'confirmed' => true])->assertSessionHasErrors('reason');
        $this->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), ['findings' => [['tooth_fdi' => '46', 'surface' => 'M'], ['tooth_fdi' => '46', 'surface' => 'O']], 'confirmed' => true, 'reason' => 'Pembatalan temuan terpilih setelah verifikasi ulang'])->assertSessionHasNoErrors();
        $this->assertCount(3, $final->fresh()->payload['odontogram']);
        $this->assertSame(['D'], array_column(ClinicalNoteVersion::latest('version')->firstOrFail()->payload['odontogram'], 'surface'));
        $this->assertDatabaseCount('clinical_note_versions', 2);
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page->has('visit.odontogram', 1));
    }

    public function test_bulk_odontogram_removal_rejects_nurses_and_other_doctors(): void
    {
        [, $visit] = $this->dentalExamination();
        foreach (['perawat', 'dokter'] as $role) {
            $other = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($other)->delete(route('pelayanan.pemeriksaan.odontogram.destroy', $visit), ['findings' => [['tooth_fdi' => '46', 'surface' => 'O']], 'confirmed' => true])->assertForbidden();
        }
    }

    public function test_dental_diagnoses_are_separate_per_tooth_and_merge_surfaces_for_the_same_code(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        OdontogramFinding::factory()->create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'D', 'details' => $this->cariesDetails()]);
        OdontogramFinding::factory()->create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'details' => $this->cariesDetails()]);
        OdontogramFinding::factory()->create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '35', 'surface' => 'D', 'details' => $this->cariesDetails()]);
        $payload = ['kode_icd10' => 'K02.1', 'nama_diagnosa' => 'Caries of dentine', 'code_system' => 'icd10_who', 'jenis' => 'utama', 'tooth_fdi' => '46', 'surfaces' => ['D'], 'keterangan' => 'Dentin terlihat'];
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), $payload)->assertSessionHasNoErrors();
        $first = $examination->diagnosa()->sole();
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [...$payload, 'surfaces' => ['O'], 'jenis' => 'tambahan', 'keterangan' => 'Gigi 46 (O) — Dentin terlihat'])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [...$payload, 'surfaces' => ['O'], 'jenis' => 'tambahan'])->assertSessionHasNoErrors();
        $this->assertSame(1, $examination->diagnosa()->count());
        $this->assertSame(['O', 'D'], $first->fresh()->surfaces);
        $this->assertSame('Gigi 46 (DO) — Dentin terlihat', $first->fresh()->keterangan);
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [...$payload, 'tooth_fdi' => '35', 'jenis' => 'tambahan'])->assertSessionHasNoErrors();
        $this->assertSame(2, $examination->diagnosa()->count());
        $this->assertSame(1, $examination->diagnosa()->where('jenis', 'utama')->count());
        $second = $examination->diagnosa()->where('tooth_fdi', '35')->sole();
        $this->patch(route('pelayanan.pemeriksaan.diagnosa.primary', $second))->assertSessionHasNoErrors();
        $this->assertSame('utama', $second->fresh()->jenis);
        $this->assertSame('tambahan', $first->fresh()->jenis);
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page->where('visit.diagnoses.0.toothFdi', '46')->where('visit.diagnoses.0.surfaces', ['O', 'D']));
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => $this->cariesDetails()])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertSessionHasNoErrors();
        $this->assertSame('46', ClinicalNoteVersion::sole()->payload['diagnoses'][0]['tooth_fdi']);
        $this->assertSame(['O', 'D'], ClinicalNoteVersion::sole()->payload['diagnoses'][0]['surfaces']);
        $this->patch(route('pelayanan.pemeriksaan.diagnosa.primary', $first))->assertUnprocessable();
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertInertia(fn (Assert $page) => $page->where('visits.0.examination.diagnoses.0.toothFdi', '46'));
    }

    public function test_dental_location_rejects_invalid_surfaces_and_primary_switch_rejects_another_doctor(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        OdontogramFinding::factory()->create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'details' => $this->cariesDetails()]);
        $payload = ['kode_icd10' => 'K02.1', 'nama_diagnosa' => 'Caries of dentine', 'jenis' => 'utama', 'tooth_fdi' => '46', 'surfaces' => ['W', 'O']];
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), $payload)->assertSessionHasErrors('surfaces');
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [...$payload, 'tooth_fdi' => '99', 'surfaces' => ['O']])->assertSessionHasErrors('tooth_fdi');
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [...$payload, 'tooth_fdi' => null, 'surfaces' => ['O']])->assertSessionHasErrors('surfaces');
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [...$payload, 'surfaces' => ['O']])->assertSessionHasNoErrors();
        $diagnosis = $examination->diagnosa()->sole();
        $this->actingAs(User::factory()->create(['role' => 'dokter', 'is_active' => true]))->patch(route('pelayanan.pemeriksaan.diagnosa.primary', $diagnosis))->assertForbidden();
        $this->assertSame('utama', $diagnosis->fresh()->jenis);
        $visit->poliklinik->update(['jenis' => 'umum']);
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [...$payload, 'surfaces' => ['O']])->assertSessionHasErrors('tooth_fdi');
    }

    public function test_specific_dental_disease_replaces_whole_tooth_sound_in_draft_and_addendum(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        $payload = ['tooth_fdi' => '35', 'surface' => 'W', 'finding_code' => 'sound'];
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [...$payload, 'surface' => 'M', 'finding_code' => 'fractured_crown'])->assertSessionHasNoErrors();
        $this->assertSame(1, $visit->odontogramFindings()->count());
        $this->assertDatabaseMissing('odontogram_findings', ['kunjungan_id' => $visit->id, 'tooth_fdi' => '35', 'finding_code' => 'sound']);
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'W', 'finding_code' => 'sound'])->assertSessionHasNoErrors();
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'nama_diagnosa' => 'Fraktur mahkota', 'kode_icd10' => 'S02.5', 'jenis' => 'utama']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertSessionHasNoErrors();
        $final = ClinicalNoteVersion::sole();
        $this->post(route('pelayanan.pemeriksaan.odontogram.addendum', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => $this->cariesDetails(), 'reason' => 'Temuan baru setelah pemeriksaan ulang'])->assertSessionHasNoErrors();
        $this->assertSame(['fractured_crown', 'caries'], array_column(ClinicalNoteVersion::latest('version')->firstOrFail()->payload['odontogram'], 'finding_code'));
        $this->assertSame('sound', $final->fresh()->payload['odontogram'][1]['finding_code']);
    }

    public function test_dental_diagnosis_context_is_checked_again_before_signing(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        $payload = ['kode_icd10' => 'K02.1', 'nama_diagnosa' => 'Caries of dentine', 'jenis' => 'utama', 'tooth_fdi' => '46', 'surfaces' => ['D']];
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), $payload)->assertSessionHasErrors('tooth_fdi');
        $finding = OdontogramFinding::factory()->create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'details' => $this->cariesDetails()]);
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), $payload)->assertSessionHasErrors('surfaces');
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [...$payload, 'surfaces' => ['O']])->assertSessionHasNoErrors();
        $finding->delete();
        OdontogramFinding::factory()->create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '35', 'surface' => 'W', 'finding_code' => 'sound']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertSessionHasErrors('dental_diagnoses');
        $this->assertNull($examination->fresh()->signed_at);
        $this->assertDatabaseCount('clinical_note_versions', 0);
        OdontogramFinding::factory()->create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'details' => $this->cariesDetails()]);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertSessionHasNoErrors();
        $this->assertNotNull($examination->fresh()->signed_at);
    }

    public function test_oral_examination_can_finish_without_a_tooth_specific_diagnosis_or_procedure(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        $examination->update(['pemeriksaan_fisik' => 'Temuan jaringan lunak mukosa bukal', 'non_farmakologis' => 'Perawatan sesuai evaluasi dokter', 'edukasi' => 'Anjuran dan jadwal evaluasi dijelaskan']);
        OdontogramFinding::factory()->create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'W', 'finding_code' => 'sound']);
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), ['is_manual' => true, 'nama_diagnosa' => 'Kelainan mukosa bukal', 'jenis' => 'utama', 'keterangan' => 'Temuan berasal dari pemeriksaan jaringan mulut', 'surfaces' => []])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $visit->fresh()->status);
        $this->assertNull(ClinicalNoteVersion::sole()->payload['diagnoses'][0]['tooth_fdi']);
        $this->assertSame([], ClinicalNoteVersion::sole()->payload['treatments']);
    }

    public function test_dental_procedures_validate_legacy_tooth_input_and_default_to_the_dentist(): void
    {
        [$user, $visit] = $this->dentalExamination();
        $payload = ['nama_tindakan_manual' => 'Tindakan dokter sesuai pemeriksaan', 'jumlah' => 1, 'tooth_fdi' => '46'];
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), $payload)->assertSessionHasErrors('tooth_fdis');
        OdontogramFinding::factory()->create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'details' => $this->cariesDetails()]);
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tindakan_kunjungan', ['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'execution_role' => 'dokter']);
    }

    public function test_extended_dental_findings_are_persisted_and_versioned_without_becoming_procedures(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        $cases = [
            ['11', 'partial_erupted', []], ['12', 'impacted', []], ['51', 'retained_primary', []],
            ['13', 'malposition', ['position' => 'rotation']], ['14', 'non_vital', []], ['15', 'root_canal', []],
            ['16', 'crown', ['material' => 'porcelain']], ['17', 'bridge_pontic', []], ['18', 'bridge_abutment', []],
            ['21', 'implant', []], ['22', 'removable_denture', ['prosthesis' => 'partial_denture']],
            ['23', 'sealant', []], ['24', 'temporary_restoration', []], ['25', 'anomaly', ['morphology' => 'microdontia']],
        ];
        foreach ($cases as [$tooth, $code, $details]) {
            $this->actingAs($user)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => $tooth, 'surface' => 'W', 'finding_code' => $code, 'details' => $details])->assertSessionHasNoErrors();
            $this->assertDatabaseHas('odontogram_findings', ['kunjungan_id' => $visit->id, 'tooth_fdi' => $tooth, 'finding_code' => $code]);
        }
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'details' => [...$this->cariesDetails(), 'pulpal' => 'previously_treated', 'endodontic' => 'treated', 'prosthesis' => 'crown', 'material' => 'porcelain']])->assertSessionHasNoErrors();
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'nama_diagnosa' => 'Diagnosis dokter', 'kode_icd10' => 'K02.1', 'jenis' => 'utama']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertSessionHasNoErrors();
        $payload = ClinicalNoteVersion::sole()->payload;
        $this->assertCount(15, $payload['odontogram']);
        $caries = collect($payload['odontogram'])->firstWhere('tooth_fdi', '46');
        $this->assertSame('treated', $caries['details']['endodontic']);
        $this->assertSame('crown', $caries['details']['prosthesis']);
        $this->assertSame([], $payload['treatments']);
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertInertia(fn (Assert $page) => $page->has('visits.0.odontogram', 15));
    }

    public function test_extended_findings_reject_wrong_scope_and_inconsistent_details(): void
    {
        [$user, $visit] = $this->dentalExamination();
        $payload = ['tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'implant'];
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [...$payload, 'surface' => 'W', 'details' => ['percussion' => 'positive']])
            ->assertSessionHasErrors('details');
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), $payload)->assertSessionHasErrors('surface');
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [...$payload, 'surface' => 'W', 'finding_code' => 'retained_primary'])->assertSessionHasErrors('tooth_fdi');
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [...$payload, 'surface' => 'W', 'finding_code' => 'non_vital', 'details' => ['pulpal' => 'reversible']])->assertSessionHasErrors('details.pulpal');
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [...$payload, 'surface' => 'W', 'finding_code' => 'anomaly'])->assertSessionHasErrors('details.morphology');
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [...$payload, 'surface' => 'W', 'finding_code' => 'removable_denture', 'details' => ['prosthesis' => 'implant']])->assertSessionHasErrors('details.prosthesis');
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [...$payload, 'surface' => 'W', 'finding_code' => 'root_canal', 'details' => ['endodontic' => 'non_vital']])->assertSessionHasErrors('details.endodontic');
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [...$payload, 'finding_code' => 'caries', 'details' => [...$this->cariesDetails(), 'prosthesis' => 'implant']])->assertSessionHasErrors('details.prosthesis');
    }

    public function test_diagnosis_source_finding_survives_signing_and_rejects_an_unrelated_source(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        OdontogramFinding::factory()->create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'details' => $this->cariesDetails()]);
        $payload = ['kode_icd10' => 'K02.1', 'nama_diagnosa' => 'Karies dentin', 'jenis' => 'utama', 'tooth_fdi' => '46', 'surfaces' => ['O'], 'source_finding_code' => 'implant'];
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), $payload)->assertSessionHasErrors('source_finding_code');
        $this->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [...$payload, 'source_finding_code' => 'caries'])->assertSessionHasNoErrors();
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page->where('visit.diagnoses.0.sourceFindingCode', 'caries'));
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertSessionHasNoErrors();
        $this->assertSame('caries', ClinicalNoteVersion::sole()->payload['diagnoses'][0]['source_finding_code']);
    }

    public function test_prosthesis_span_is_validated_and_new_conditions_survive_an_addendum(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        $data = ['tooth_fdi' => '15', 'surface' => 'W', 'finding_code' => 'bridge_pontic', 'details' => ['material' => 'porcelain', 'prosthesis_region' => 'upper', 'prosthesis_span' => '14,15,16']];
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), $data)->assertSessionHasNoErrors();
        $this->assertSame('14, 15, 16', $visit->odontogramFindings()->sole()->details['prosthesis_span']);
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [...$data, 'details' => ['prosthesis_span' => '14,15,46']])->assertSessionHasErrors('details.prosthesis_span');
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [...$data, 'details' => ['prosthesis_region' => 'lower']])->assertSessionHasErrors('details.prosthesis_region');
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'nama_diagnosa' => 'Evaluasi prostesis', 'kode_icd10' => null, 'code_system' => 'manual', 'jenis' => 'utama']);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['review_final_plan' => true, 'review_nursing_assessment' => true, 'screening_revision' => $visit->fresh()->screening?->revisionToken(), 'signature_password' => 'password', 'review_dental_assessment' => true])->assertSessionHasNoErrors();
        $final = ClinicalNoteVersion::sole();
        $this->post(route('pelayanan.pemeriksaan.odontogram.addendum', $visit), ['tooth_fdi' => '35', 'surface' => 'W', 'finding_code' => 'implant', 'details' => ['material' => 'porcelain'], 'reason' => 'Melengkapi riwayat implan setelah verifikasi'])->assertSessionHasNoErrors();
        $this->assertCount(1, $final->fresh()->payload['odontogram']);
        $this->assertCount(2, ClinicalNoteVersion::latest('version')->firstOrFail()->payload['odontogram']);
        $this->assertTrue(app(ClinicalNoteRecorder::class)->verify($examination->fresh()));
    }

    private function cariesDetails(): array
    {
        return ['icdas' => '5', 'activity' => 'active', 'site' => 'primary', 'depth' => 'dentin', 'pulpal' => 'reversible', 'periapical' => 'normal', 'assessment' => 'Dentin terlihat, respons dingin singkat, perkusi negatif. Rencana restorasi dan evaluasi.'];
    }

    public function test_focused_dental_visit_records_scope_and_escalation_without_full_chart(): void
    {
        [$doctor, $visit, $exam] = $this->dentalExamination();
        $visit->screening()->update(['dental_vitals_unmeasurable' => true]);
        $exam->update(['riwayat_penyakit_sekarang' => 'Nyeri akut sejak pagi']);
        $exam->diagnosa()->create(['kode_icd10' => 'K04.0', 'code_system' => 'icd10_who', 'nama_diagnosa' => 'Pulpitis', 'jenis' => 'utama']);
        $payload = [
            'review_final_plan' => true, 'signature_password' => 'password', 'review_dental_assessment' => true,
            'dental_scope' => 'emergency', 'dental_scope_reason' => 'Prioritas penilaian keluhan akut',
            'dental_focus' => 'Gigi 46: kavitas dan nyeri akut', 'dental_follow_up_plan' => 'Lengkapi odontogram saat kontrol',
        ];
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.selesai', $visit), $payload)
            ->assertSessionHasErrors('dental_escalation_decision');
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), [
            ...$payload, 'dental_escalation_decision' => 'reassessed', 'dental_escalation_note' => 'Penilaian ulang dilakukan oleh DPJP.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $visit->fresh()->status);
        $review = $exam->clinicalNoteVersions()->firstOrFail()->payload['dental_review'];
        $this->assertSame('emergency', $review['scope']['dental_scope']);
        $this->assertSame('reassessed', $review['escalation']['dental_escalation_decision']);
    }

    private function dentalExamination(): array
    {
        $user = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'GIGI-AUDIT', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $doctor = Nakes::create([
            'kode' => 'DOK-GIGI-AUDIT', 'nama' => 'drg. Audit', 'kategori' => 'medis', 'jabatan' => 'dokter',
            'user_id' => $user->id, 'poliklinik_id' => $clinic->id, 'is_active' => true,
        ]);
        $patient = Pasien::create(['no_rm' => 'RM-GIGI-AUDIT', 'nama' => 'Pasien Gigi']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-GIGI-AUDIT', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id,
            'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'pemeriksaan',
        ]);
        $examination = Pemeriksaan::create([
            'kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id,
            'anamnesis' => 'Nyeri geraham', 'pemeriksaan_fisik' => 'Kavitas pada gigi 46', 'pemeriksaan_ekstraoral' => 'Wajah simetris, tidak tampak pembengkakan',
        ]);
        Screening::create(['kunjungan_id' => $visit->id, 'submitted_at' => now()]);

        return [$user, $visit, $examination];
    }
}
