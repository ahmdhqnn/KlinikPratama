<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\Models\ClinicalNoteVersion;
use App\Models\ClinicalTerminology;
use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\OdontogramFinding;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Tindakan;
use App\Models\TindakanKunjungan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ClinicalProcedureFlowTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['23.09'])]
    #[TestWith(['2309'])]
    #[TestWith(['Extraction'])]
    #[TestWith(['tooth extraction'])]
    public function test_search_returns_only_active_icd9cm_procedures_with_their_release(string $query): void
    {
        [$user] = $this->clinicalContext('gigi');
        $code = $this->procedure();
        ClinicalTerminology::create(['code_system' => 'icd9cm_procedure', 'release' => 'Inactive', 'code' => '23.01', 'display' => 'Tooth extraction inactive', 'code_type' => 'procedure', 'is_active' => false]);
        ClinicalTerminology::create(['code_system' => 'icd9cm_procedure', 'release' => 'Wrong type', 'code' => '23.09', 'display' => 'Tooth extraction diagnosis', 'code_type' => 'diagnosis', 'is_active' => true]);
        ClinicalTerminology::create(['code_system' => 'icd9cm_diagnosis', 'release' => 'Wrong system', 'code' => '23.09', 'display' => 'Tooth extraction diagnosis', 'code_type' => 'diagnosis', 'is_active' => true]);

        $this->actingAs($user)->getJson(route('pelayanan.icd9cm.search', ['q' => $query]))
            ->assertOk()->assertExactJson([
                ['id' => $code->id, 'code' => '23.09', 'display' => 'Extraction of other tooth', 'release' => 'FY 2015'],
            ]);
    }

    public function test_search_keeps_distinct_releases_available_for_selection(): void
    {
        [$user] = $this->clinicalContext('umum');
        $this->procedure();
        $this->procedure(['release' => 'FY 2014']);

        $this->actingAs($user)->getJson(route('pelayanan.icd9cm.search', ['q' => '23.09']))
            ->assertOk()->assertJsonCount(2)->assertJsonFragment(['release' => 'FY 2015'])->assertJsonFragment(['release' => 'FY 2014']);
    }

    #[TestWith(['umum'])]
    #[TestWith(['gigi'])]
    public function test_both_clinics_save_canonical_procedure_code_name_release_and_notes(string $clinicType): void
    {
        [$user, $visit] = $this->clinicalContext($clinicType);
        $code = $this->procedure();
        $this->procedure(['release' => 'FY 2014', 'display' => 'Different release description']);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), [
            'terminology_id' => $code->id, 'jumlah' => 2, 'catatan' => "Catatan tindakan\nEvaluasi setelah tindakan",
            'kode_icd9cm' => 'FAKE', 'nama_tindakan' => 'Nama palsu', 'code_release' => 'Rilis palsu',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tindakan_kunjungan', [
            'kunjungan_id' => $visit->id, 'dokter_id' => $visit->dokter_id,
            'nama_tindakan' => 'Extraction of other tooth', 'nama_tindakan_manual' => null,
            'kode_icd9cm' => '23.09', 'code_system' => 'icd9cm_procedure', 'code_release' => 'FY 2015',
            'jumlah' => 2, 'catatan' => "Catatan tindakan\nEvaluasi setelah tindakan",
        ]);
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('procedureCatalogAvailable', true)
            ->where('visit.treatments.0.code', '23.09')
            ->where('visit.treatments.0.codeRelease', 'FY 2015')
            ->where('visit.treatments.0.note', "Catatan tindakan\nEvaluasi setelah tindakan")
        );
        $this->assertDatabaseHas('clinical_audit_events', ['actor_id' => $user->id, 'kunjungan_id' => $visit->id, 'action' => 'clinical_draft.storeTindakan']);
    }

    public function test_manual_entry_remains_available_when_a_catalog_exists(): void
    {
        [$user, $visit] = $this->clinicalContext('umum');
        $this->procedure();

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), [
            'nama_tindakan_manual' => 'Tindakan lokal manual', 'jumlah' => 1, 'catatan' => 'Catatan manual tetap disimpan.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tindakan_kunjungan', [
            'nama_tindakan_manual' => 'Tindakan lokal manual', 'nama_tindakan' => 'Tindakan lokal manual',
            'kode_icd9cm' => null, 'code_system' => null, 'code_release' => null, 'catatan' => 'Catatan manual tetap disimpan.',
        ]);
    }

    public function test_procedure_codes_cannot_be_saved_as_diagnoses(): void
    {
        [$user, $visit] = $this->clinicalContext('umum');
        $this->procedure();

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [
            'code_system' => 'icd9cm_procedure', 'kode_icd10' => '23.09', 'nama_diagnosa' => 'Extraction of other tooth', 'jenis' => 'utama',
        ])->assertSessionHasErrors(['code_system' => 'Kode prosedur ICD-9-CM harus dicatat pada tindakan, bukan diagnosis.']);

        $this->assertDatabaseCount('diagnosa', 0);
    }

    #[TestWith(['icd10_who'])]
    #[TestWith(['icd10_cm'])]
    public function test_manual_numeric_procedure_codes_cannot_be_labelled_as_icd10_diagnoses(string $system): void
    {
        [$user, $visit] = $this->clinicalContext('umum');

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [
            'code_system' => $system, 'kode_icd10' => '23.09', 'nama_diagnosa' => 'Extraction of other tooth', 'jenis' => 'utama',
        ])->assertSessionHasErrors(['kode_icd10' => 'Kode diagnosis ICD-10 harus diawali huruf. Catat kode prosedur ICD-9-CM pada tindakan.']);

        $this->assertDatabaseCount('diagnosa', 0);
    }

    public function test_an_empty_catalog_is_reported_and_manual_entry_can_still_be_saved(): void
    {
        [$user, $visit] = $this->clinicalContext('umum');

        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', $visit))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('procedureCatalogAvailable', false));
        $this->getJson(route('pelayanan.icd9cm.search', ['q' => 'suture']))->assertOk()->assertExactJson([]);
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), [
            'nama_tindakan_manual' => 'Tindakan manual tanpa katalog', 'jumlah' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tindakan_kunjungan', ['kunjungan_id' => $visit->id, 'kode_icd9cm' => null]);
    }

    #[TestWith(['icd9cm_diagnosis', 'diagnosis', true])]
    #[TestWith(['icd9cm_procedure', 'diagnosis', true])]
    #[TestWith(['icd9cm_procedure', 'procedure', false])]
    public function test_invalid_catalog_selections_do_not_create_a_treatment(string $system, string $type, bool $active): void
    {
        [$user, $visit] = $this->clinicalContext('umum');
        $code = $this->procedure(['code_system' => $system, 'code_type' => $type, 'is_active' => $active]);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['terminology_id' => $code->id, 'jumlah' => 1])
            ->assertSessionHasErrors(['terminology_id' => 'Pilih kode prosedur ICD-9-CM yang masih aktif dari hasil pencarian.']);

        $this->assertDatabaseCount('tindakan_kunjungan', 0);
    }

    public function test_a_deleted_catalog_selection_is_rejected_without_losing_other_draft_data(): void
    {
        [$user, $visit] = $this->clinicalContext('umum');
        $code = $this->procedure();
        $code->delete();

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['terminology_id' => $code->id, 'jumlah' => 1])
            ->assertSessionHasErrors('terminology_id');

        $this->assertDatabaseCount('tindakan_kunjungan', 0);
        $this->assertDatabaseHas('pemeriksaan', ['kunjungan_id' => $visit->id, 'catatan' => 'Catatan dokter tetap ada']);
    }

    public function test_missing_or_conflicting_sources_are_rejected(): void
    {
        [$user, $visit] = $this->clinicalContext('umum');
        $code = $this->procedure();

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['jumlah' => 1])->assertSessionHasErrors('tindakan_id');
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), [
            'terminology_id' => $code->id, 'nama_tindakan_manual' => 'Nama yang bertentangan', 'jumlah' => 1,
        ])->assertSessionHasErrors('tindakan_id');

        $this->assertDatabaseCount('tindakan_kunjungan', 0);
    }

    public function test_coded_treatments_can_link_local_supplies_and_each_selected_dental_tooth(): void
    {
        [$user, $visit] = $this->clinicalContext('gigi');
        $code = $this->procedure();
        $treatment = Tindakan::create(['kode' => 'LOCAL', 'nama' => 'Tindakan gigi lokal', 'poliklinik_id' => $visit->poliklinik_id, 'is_active' => true]);
        OdontogramFinding::factory()->create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46']);
        OdontogramFinding::factory()->create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '36']);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), [
            'terminology_id' => $code->id, 'tindakan_id' => $treatment->id, 'jumlah' => 1, 'tooth_fdis' => ['46', '36'], 'catatan' => 'Catatan per gigi',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseCount('tindakan_kunjungan', 2);
        $this->assertDatabaseHas('tindakan_kunjungan', ['tooth_fdi' => '46', 'tindakan_id' => $treatment->id, 'kode_icd9cm' => '23.09', 'catatan' => 'Catatan per gigi']);
        $this->assertDatabaseHas('tindakan_kunjungan', ['tooth_fdi' => '36', 'tindakan_id' => $treatment->id, 'kode_icd9cm' => '23.09', 'catatan' => 'Catatan per gigi']);
    }

    public function test_local_supply_links_from_other_clinics_are_rejected(): void
    {
        [$user, $visit] = $this->clinicalContext('umum');
        [, $dentalVisit] = $this->clinicalContext('gigi', 'DENTAL');
        $code = $this->procedure();
        $treatment = Tindakan::create(['kode' => 'DENTAL', 'nama' => 'Tindakan gigi lokal', 'poliklinik_id' => $dentalVisit->poliklinik_id, 'is_active' => true]);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), [
            'terminology_id' => $code->id, 'tindakan_id' => $treatment->id, 'jumlah' => 1,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('tindakan_kunjungan', 0);
    }

    #[TestWith(['umum'])]
    #[TestWith(['gigi'])]
    public function test_finalized_rme_preserves_procedures_after_catalog_deletion_and_uses_the_signed_snapshot(string $clinicType): void
    {
        [$user, $visit] = $this->clinicalContext($clinicType);
        $code = $this->procedure();
        $examination = $visit->pemeriksaan;
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'Z00.0', 'nama_diagnosa' => 'Pemeriksaan', 'jenis' => 'utama']);
        OdontogramFinding::factory()->create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46']);
        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), [
            'terminology_id' => $code->id, 'jumlah' => 1, 'catatan' => 'Catatan asli tindakan',
        ])->assertSessionHasNoErrors();

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertRedirect();

        $final = ClinicalNoteVersion::where('pemeriksaan_id', $examination->id)->sole();
        $this->assertSame('23.09', $final->payload['treatments'][0]['code']);
        $this->assertSame('FY 2015', $final->payload['treatments'][0]['code_release']);
        $this->assertSame('Catatan asli tindakan', $final->payload['treatments'][0]['note']);
        $code->delete();
        $treatment = TindakanKunjungan::where('kunjungan_id', $visit->id)->sole();
        $treatment->update(['nama_tindakan' => 'Perubahan di luar catatan final', 'kode_icd9cm' => 'FAKE', 'catatan' => 'Catatan diubah']);
        $this->get(route('pelayanan.pemeriksaan.show', $visit))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('visit.examination.integrityValid', true)->where('visit.treatments.0.code', '23.09')
            ->where('visit.treatments.0.name', 'Extraction of other tooth')->where('visit.treatments.0.note', 'Catatan asli tindakan')
        );
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('visits.0.treatments.0.code', '23.09')->where('visits.0.treatments.0.codeRelease', 'FY 2015')
            ->where('visits.0.treatments.0.note', 'Catatan asli tindakan')
        );
        $this->delete(route('pelayanan.pemeriksaan.tindakan.destroy', $treatment))->assertUnprocessable();
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Sesudah final', 'jumlah' => 1])->assertUnprocessable();
        $this->assertDatabaseCount('clinical_note_versions', 1);
    }

    public function test_legacy_signed_treatments_remain_readable_without_coding_fields(): void
    {
        [$user, $visit] = $this->clinicalContext('umum');
        $examination = $visit->pemeriksaan;
        $recorder = app(ClinicalNoteRecorder::class);
        $payload = $recorder->snapshot($visit, $examination);
        $payload['treatments'] = [['name' => 'Tindakan historis', 'quantity' => 1, 'note' => 'Catatan historis', 'tooth_fdi' => null]];
        $recorder->recordFinal($examination, $payload, $user->id);
        $examination->update(['status' => 'selesai', 'signed_at' => now(), 'signed_by_user_id' => $user->id]);

        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', $visit))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('visit.treatments.0.code', null)->where('visit.treatments.0.name', 'Tindakan historis')->where('visit.treatments.0.note', 'Catatan historis')
        );
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('visits.0.treatments.0.code', null)->where('visits.0.treatments.0.name', 'Tindakan historis')->where('visits.0.treatments.0.note', 'Catatan historis')
        );
    }

    public function test_other_doctors_cannot_add_or_delete_treatments_from_an_assigned_visit(): void
    {
        [$owner, $visit] = $this->clinicalContext('umum');
        [$other] = $this->clinicalContext('umum', 'OTHER');
        $code = $this->procedure();
        $this->actingAs($owner)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['terminology_id' => $code->id, 'jumlah' => 1])->assertSessionHasNoErrors();
        $treatment = TindakanKunjungan::sole();

        $this->actingAs($other)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['terminology_id' => $code->id, 'jumlah' => 1])->assertForbidden();
        $this->delete(route('pelayanan.pemeriksaan.tindakan.destroy', $treatment))->assertForbidden();

        $this->assertDatabaseCount('tindakan_kunjungan', 1);
        $this->assertModelExists($treatment);
    }

    #[TestWith(['admin'])]
    #[TestWith(['perawat'])]
    #[TestWith(['farmasi'])]
    #[TestWith(['kasir'])]
    #[TestWith(['manajemen'])]
    public function test_other_roles_cannot_search_or_record_doctor_procedures(string $role): void
    {
        [, $visit] = $this->clinicalContext('umum');
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);

        $this->actingAs($user)->getJson(route('pelayanan.icd9cm.search', ['q' => '23']))->assertForbidden();
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Tidak berwenang', 'jumlah' => 1])->assertForbidden();

        $this->assertDatabaseCount('tindakan_kunjungan', 0);
    }

    public function test_registration_role_is_redirected_away_from_doctor_procedure_routes(): void
    {
        [, $visit] = $this->clinicalContext('umum');
        $user = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);

        $this->actingAs($user)->get(route('pelayanan.icd9cm.search', ['q' => '23']))->assertRedirect(route('pendaftaran.dashboard'));
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Tidak berwenang', 'jumlah' => 1])->assertRedirect(route('pendaftaran.dashboard'));

        $this->assertDatabaseCount('tindakan_kunjungan', 0);
    }

    public function test_guests_cannot_search_procedure_codes(): void
    {
        $this->get(route('pelayanan.icd9cm.search', ['q' => '23']))->assertRedirect(route('login'));
    }

    /** @return array{User, Kunjungan} */
    private function clinicalContext(string $clinicType, string $suffix = 'PRIMARY'): array
    {
        $user = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DOK-'.$suffix, 'nama' => 'Dokter '.$suffix, 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $user->id, 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'POLI-'.$suffix, 'nama' => 'Poli '.$clinicType, 'jenis' => $clinicType, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-'.$suffix, 'nama' => 'Pasien '.$suffix]);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-'.$suffix, 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'anamnesis' => 'Keluhan pasien', 'pemeriksaan_fisik' => 'Temuan pemeriksaan', 'catatan' => 'Catatan dokter tetap ada']);

        return [$user, $visit];
    }

    /** @param array<string, mixed> $attributes */
    private function procedure(array $attributes = []): ClinicalTerminology
    {
        return ClinicalTerminology::create(array_replace([
            'code_system' => 'icd9cm_procedure', 'release' => 'FY 2015', 'code' => '23.09',
            'display' => 'Extraction of other tooth', 'code_type' => 'procedure', 'is_active' => true,
        ], $attributes));
    }
}
