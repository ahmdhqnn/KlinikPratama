<?php

namespace Tests\Feature;

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

        $this->actingAs($doctorUser)->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertUnprocessable();
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '19', 'surface' => 'O', 'finding_code' => 'caries',
        ])->assertSessionHasErrors('tooth_fdi');
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'missing',
        ])->assertSessionHasErrors('surface');

        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'notes' => 'Kavitas kecil',
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
            'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'notes' => 'Kavitas kecil',
        ])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.odontogram.store', $visit), [
            'tooth_fdi' => '36', 'surface' => 'D', 'finding_code' => 'caries',
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

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertRedirect();
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
            'surface' => 'O', 'finding_code' => 'caries',
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
            'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries',
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
            'kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'recorded_by_user_id' => $user->id,
        ]);
        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', ['kunjungan' => $visit->id, 'tab' => 'odontogram']))
            ->assertInertia(fn (Assert $page) => $page->where('initialTab', 'odontogram')->where('auth.user.clinicType', 'gigi'));

        $this->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'nama_obat' => 'Obat luar', 'jumlah' => 3, 'satuan' => 'tablet',
            'aturan_pakai' => 'Sesuai instruksi dokter', 'jenis' => 'jadi', 'is_resep_luar' => true,
        ])->assertSessionHasNoErrors();

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])
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
        OdontogramFinding::create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'recorded_by_user_id' => $user->id]);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertUnprocessable();

        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'pemeriksaan']);
        $this->assertDatabaseMissing('clinical_note_versions', ['pemeriksaan_id' => $examination->id]);
    }

    public function test_retired_ohis_field_cannot_change_a_signed_record_via_addendum(): void
    {
        [$user, $visit, $examination] = $this->dentalExamination();
        $examination->update(['oral_hygiene_index' => 1.25]);
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9', 'nama_diagnosa' => 'Karies', 'jenis' => 'utama']);
        OdontogramFinding::create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'recorded_by_user_id' => $user->id]);
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertRedirect();

        $this->post(route('pelayanan.pemeriksaan.addendum', $visit), [
            'reason' => 'Koreksi nilai kebersihan mulut', 'oral_hygiene_index' => 4.5,
        ])->assertSessionHasErrors(['reason' => 'Isi setidaknya satu bagian catatan yang dikoreksi.']);

        $this->assertDatabaseCount('clinical_note_versions', 1);
        $this->assertSame('1.25', $examination->fresh()->oral_hygiene_index);
    }

    /**
     * @return array{User, Kunjungan, Pemeriksaan}
     */
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
            'anamnesis' => 'Nyeri geraham', 'pemeriksaan_fisik' => 'Kavitas pada gigi 46',
        ]);

        return [$user, $visit, $examination];
    }
}
