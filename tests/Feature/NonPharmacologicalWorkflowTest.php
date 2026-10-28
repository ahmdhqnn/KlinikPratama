<?php

namespace Tests\Feature;

use App\ClinicalNoteRecorder;
use App\Models\ClinicalNoteVersion;
use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\OdontogramFinding;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Tindakan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class NonPharmacologicalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['umum', true, true])]
    #[TestWith(['umum', true, false])]
    #[TestWith(['umum', false, true])]
    #[TestWith(['umum', false, false])]
    #[TestWith(['gigi', true, true])]
    #[TestWith(['gigi', true, false])]
    #[TestWith(['gigi', false, true])]
    #[TestWith(['gigi', false, false])]
    public function test_four_care_paths_reach_completion_in_both_clinics(string $clinic, bool $medication, bool $procedure): void
    {
        [$doctor, $nurse, $visit, $examination] = $this->context($clinic);
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'section' => 'rencana', 'non_farmakologis' => 'Latihan sesuai toleransi', 'edukasi' => 'Penjelasan perawatan dan tanda bahaya',
        ])->assertSessionHasNoErrors();
        if ($procedure) {
            $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), [
                'nama_tindakan_manual' => 'Perawatan luka', 'jumlah' => 1, 'catatan' => 'Periksa dan rawat lokasi keluhan',
            ])->assertSessionHasNoErrors();
        }
        if ($medication) {
            $medicine = Obat::create(['kode' => 'OB-CARE', 'nama' => 'Obat uji', 'satuan_kecil' => 'tablet', 'stok' => 20, 'jenis' => 'obat', 'is_active' => true]);
            ObatBatch::factory()->create(['obat_id' => $medicine->id, 'stok' => 20]);
            $this->post(route('pelayanan.pemeriksaan.resep.store', $visit), ['obat_id' => $medicine->id, 'jumlah' => 2, 'jenis' => 'jadi', 'aturan_pakai' => 'Sesuai instruksi dokter'])->assertSessionHasNoErrors();
        }
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertRedirect()->assertSessionHasNoErrors();
        $hash = $examination->fresh()->latest_note_hash;
        $this->assertSame($procedure ? 'tindakan' : ($medication ? 'farmasi' : 'selesai'), $visit->fresh()->status);
        $this->assertSame('Latihan sesuai toleransi', ClinicalNoteVersion::sole()->payload['non_farmakologis']);
        $pharmacist = User::factory()->create(['role' => 'farmasi']);
        if ($procedure) {
            $this->assertDatabaseMissing('farmasi', ['kunjungan_id' => $visit->id]);
            $this->actingAs($pharmacist)->get(route('pelayanan.farmasi.show', $visit))->assertNotFound();
            $this->post(route('pelayanan.farmasi.selesai', $visit), ['items' => []])->assertUnprocessable();
            $treatment = $visit->tindakanKunjungan()->sole();
            $this->actingAs($nurse)->get(route('pelayanan.tindakan.index'))->assertInertia(fn (Assert $page) => $page
                ->has('todayVisits.data', 1)->has('visits.data', 1)
                ->where('stats.todayPending', 1)->where('stats.allPending', 1)
                ->where('visits.data.0.pendingCount', 1));
            $this->get(route('pelayanan.tindakan.show', $visit))->assertInertia(fn (Assert $page) => $page->where('visit.treatments.0.status', 'pending'));
            $this->post(route('pelayanan.tindakan.complete', [$visit, $treatment]), ['execution_result' => 'Tindakan selesai, pasien toleransi baik'])->assertSessionHasNoErrors();
            $this->assertDatabaseHas('tindakan_kunjungan', ['id' => $treatment->id, 'execution_status' => 'completed', 'performed_by' => $nurse->id]);
            $this->assertSame($medication ? 'farmasi' : 'selesai', $visit->fresh()->status);
            $this->get(route('pelayanan.tindakan.index', ['status' => 'completed', 'search' => $visit->no_kunjungan]))
                ->assertInertia(fn (Assert $page) => $page->where('stats.todayPending', 0)
                    ->where('stats.todayCompleted', 1)->where('visits.total', 1)
                    ->where('visits.data.0.pendingCount', 0)->where('visits.data.0.completedCount', 1));
            $this->get(route('pelayanan.tindakan.index', ['status' => 'pending']))
                ->assertInertia(fn (Assert $page) => $page->where('visits.total', 0)->where('todayVisits.total', 1));
            $this->assertSame($hash, $examination->fresh()->latest_note_hash);
            $this->assertTrue(app(ClinicalNoteRecorder::class)->verify($examination->fresh()));
            $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertInertia(fn (Assert $page) => $page
                ->where('visits.0.procedureExecutions.0.result', 'Tindakan selesai, pasien toleransi baik')
                ->where('visits.0.examination.nonPharmacological', 'Latihan sesuai toleransi'));
        }
        if ($medication) {
            $item = $visit->resep()->firstOrFail()->resepObat()->sole();
            $this->actingAs($pharmacist)->post(route('pelayanan.farmasi.selesai', $visit), ['items' => [['resep_obat_id' => $item->id, 'obat_id' => $item->obat_id, 'jumlah_diberikan' => 2]]])->assertSessionHasNoErrors();
            $this->assertSame(18, (int) $medicine->fresh()->stok);
        }
        $this->assertSame('selesai', $visit->fresh()->status);
    }

    public function test_each_procedure_must_finish_and_supplies_are_consumed_once_at_execution(): void
    {
        [$doctor, $nurse, $visit] = $this->context();
        $supply = Obat::create(['kode' => 'BHP-CARE', 'nama' => 'Kasa uji', 'satuan_kecil' => 'lembar', 'jenis' => 'bhp', 'stok' => 10, 'is_active' => true]);
        ObatBatch::factory()->create(['obat_id' => $supply->id, 'stok' => 10]);
        $catalog = Tindakan::create(['kode' => 'TD-CARE', 'nama' => 'Perawatan luka', 'kategori' => 'medis', 'poliklinik_id' => $visit->poliklinik_id, 'is_active' => true]);
        $catalog->bhp()->create(['obat_id' => $supply->id, 'jumlah' => 2]);
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['tindakan_id' => $catalog->id, 'jumlah' => 2])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Evaluasi luka', 'jumlah' => 1])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame(10, (int) $supply->fresh()->stok);
        [$first, $second] = $visit->tindakanKunjungan()->orderBy('id')->get()->all();
        $this->actingAs($nurse)->post(route('pelayanan.tindakan.complete', [$visit, $first]), ['execution_result' => 'Luka dibersihkan dan ditutup'])->assertSessionHasNoErrors();
        $this->assertSame('tindakan', $visit->fresh()->status);
        $this->assertSame(6, (int) $supply->fresh()->stok);
        $this->post(route('pelayanan.tindakan.complete', [$visit, $first]), ['execution_result' => 'Tidak boleh menimpa'])->assertSessionHasNoErrors();
        $this->assertSame('Luka dibersihkan dan ditutup', $first->fresh()->execution_result);
        $this->assertSame(6, (int) $supply->fresh()->stok);
        $this->post(route('pelayanan.tindakan.complete', [$visit, $second]), ['execution_result' => 'Evaluasi selesai'])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $visit->fresh()->status);
        $this->assertDatabaseCount('clinical_note_versions', 1);
        $this->assertSame(2, DB::table('clinical_audit_events')->where('action', 'nursing_procedure.complete')->count());
    }

    #[TestWith(['admin'])]
    #[TestWith(['dokter'])]
    #[TestWith(['farmasi'])]
    #[TestWith(['manajemen'])]
    #[TestWith(['pendaftaran'])]
    public function test_other_roles_cannot_record_nursing_execution(string $role): void
    {
        [$doctor, , $visit] = $this->context();
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Tindakan uji', 'jumlah' => 1]);
        $treatment = $visit->tindakanKunjungan()->sole();
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password']);
        $actor = User::factory()->create(['role' => $role]);
        $response = $this->actingAs($actor)->post(route('pelayanan.tindakan.complete', [$visit, $treatment]), ['execution_result' => 'Tidak sah']);
        if ($role === 'pendaftaran') {
            $response->assertRedirect(route('pendaftaran.dashboard'));
        } else {
            $response->assertForbidden();
        }
        $this->assertNull($treatment->fresh()->performed_by);
        $this->assertSame('tindakan', $visit->fresh()->status);
    }

    public function test_nurse_cannot_access_another_clinic_or_complete_before_signature(): void
    {
        [$doctor, $nurse, $visit] = $this->context();
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Tindakan uji', 'jumlah' => 1]);
        $treatment = $visit->tindakanKunjungan()->sole();
        $this->actingAs($nurse)->post(route('pelayanan.tindakan.complete', [$visit, $treatment]), ['execution_result' => 'Terlalu dini'])->assertUnprocessable();
        $other = Poliklinik::create(['kode' => 'OTHER', 'nama' => 'Poli Lain', 'jenis' => 'gigi', 'is_active' => true]);
        $nurse->nakes()->update(['poliklinik_id' => $other->id]);
        $this->get(route('pelayanan.tindakan.show', $visit))->assertForbidden();
        $this->post(route('pelayanan.tindakan.complete', [$visit, $treatment]), ['execution_result' => 'Lintas poli'])->assertForbidden();
        $this->get(route('pelayanan.tindakan.index'))->assertInertia(fn (Assert $page) => $page->has('visits.data', 0));
        $this->assertNull($treatment->fresh()->performed_at);
    }

    public function test_doctor_completed_treatment_and_external_prescription_do_not_create_nurse_or_pharmacy_queue(): void
    {
        [$doctor, , $visit] = $this->context('gigi');
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Tindakan oleh dokter', 'jumlah' => 1, 'execution_role' => 'dokter', 'catatan' => 'Hasil baik'])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.resep.store', $visit), ['nama_obat' => 'Obat luar', 'jumlah' => 1, 'jenis' => 'jadi', 'is_resep_luar' => true])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $visit->fresh()->status);
        $this->assertDatabaseHas('tindakan_kunjungan', ['kunjungan_id' => $visit->id, 'execution_status' => 'completed', 'performed_by' => $doctor->id]);
        $this->assertDatabaseMissing('farmasi', ['kunjungan_id' => $visit->id]);
    }

    public function test_insufficient_supplies_roll_back_execution_and_visit_transition(): void
    {
        [$doctor, $nurse, $visit] = $this->context();
        $supply = Obat::create(['kode' => 'BHP-LOW', 'nama' => 'BHP terbatas', 'satuan_kecil' => 'lembar', 'jenis' => 'bhp', 'stok' => 1, 'is_active' => true]);
        ObatBatch::factory()->create(['obat_id' => $supply->id, 'stok' => 1]);
        $catalog = Tindakan::create(['kode' => 'TD-LOW', 'nama' => 'Tindakan uji', 'kategori' => 'medis', 'poliklinik_id' => $visit->poliklinik_id, 'is_active' => true]);
        $catalog->bhp()->create(['obat_id' => $supply->id, 'jumlah' => 2]);
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['tindakan_id' => $catalog->id, 'jumlah' => 1])->assertSessionHasNoErrors();
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertSessionHasNoErrors();
        $procedure = $visit->tindakanKunjungan()->sole();
        $this->actingAs($nurse)->post(route('pelayanan.tindakan.complete', [$visit, $procedure]), ['execution_result' => 'Hasil uji'])->assertSessionHasErrors('items');
        $this->assertSame('tindakan', $visit->fresh()->status);
        $this->assertSame('pending', $procedure->fresh()->execution_status);
        $this->assertNull($procedure->performed_at);
        $this->assertNull($procedure->bhp_consumed_at);
        $this->assertSame(1, (int) $supply->fresh()->stok);
        $this->assertDatabaseCount('stok_mutasi', 0);
    }

    public function test_execution_requires_a_result_and_an_intact_signed_note(): void
    {
        [$doctor, $nurse, $visit, $examination] = $this->context();
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Tindakan uji', 'jumlah' => 1]);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertSessionHasNoErrors();
        $procedure = $visit->tindakanKunjungan()->sole();
        $this->actingAs($nurse)->post(route('pelayanan.tindakan.complete', [$visit, $procedure]), ['execution_result' => ' '])->assertSessionHasErrors('execution_result');
        $version = $examination->clinicalNoteVersions()->firstOrFail();
        $payload = $version->payload;
        $payload['edukasi'] = 'Perubahan tidak sah';
        DB::table('clinical_note_versions')->where('id', $version->id)->update(['payload' => json_encode($payload)]);
        $this->get(route('pelayanan.tindakan.show', $visit))->assertUnprocessable();
        $this->post(route('pelayanan.tindakan.complete', [$visit, $procedure]), ['execution_result' => 'Hasil uji'])->assertUnprocessable();
        $this->assertSame('pending', $procedure->fresh()->execution_status);
        $this->assertSame('tindakan', $visit->fresh()->status);
    }

    public function test_nonpharmacological_addendum_is_versioned_and_visible_to_nurse_and_medical_record(): void
    {
        [$doctor, $nurse, $visit, $examination] = $this->context();
        $this->actingAs($doctor)->post(route('pelayanan.pemeriksaan.tindakan.store', $visit), ['nama_tindakan_manual' => 'Tindakan uji', 'jumlah' => 1]);
        $this->post(route('pelayanan.pemeriksaan.selesai', $visit), ['signature_password' => 'password'])->assertSessionHasNoErrors();
        $original = $examination->clinicalNoteVersions()->firstOrFail();
        $this->post(route('pelayanan.pemeriksaan.addendum', $visit), [
            'reason' => 'Melengkapi rencana tanpa obat',
            'non_farmakologis' => 'Rencana nonfarmakologis yang diperbarui',
            'edukasi' => 'Anjuran yang diperbarui',
        ])->assertSessionHasNoErrors();
        $this->assertSame($original->payload, $original->fresh()->payload);
        $this->assertSame('tindakan', $visit->fresh()->status);
        $this->actingAs($nurse)->get(route('pelayanan.tindakan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.nonPharmacological', 'Rencana nonfarmakologis yang diperbarui')
            ->where('visit.education', 'Anjuran yang diperbarui'));
        $this->get(route('pelayanan.pasien.rekam-medis', $visit->pasien_id))->assertInertia(fn (Assert $page) => $page
            ->where('visits.0.examination.nonPharmacological', 'Rencana nonfarmakologis yang diperbarui')
            ->has('visits.0.examination.versions', 2)
            ->where('visits.0.examination.versions.1.nonPharmacological', 'Rencana nonfarmakologis yang diperbarui')
            ->where('visits.0.examination.versions.1.education', 'Anjuran yang diperbarui'));
    }

    /** @return array{User, User, Kunjungan, Pemeriksaan} */
    private function context(string $type = 'umum'): array
    {
        $doctorUser = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'POL-CARE', 'nama' => 'Poli '.$type, 'jenis' => $type, 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DOC-CARE', 'nama' => 'Dokter Uji', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $doctorUser->id, 'is_active' => true]);
        Nakes::create(['kode' => 'NUR-CARE', 'nama' => 'Perawat Uji', 'kategori' => 'medis', 'jabatan' => 'perawat', 'user_id' => $nurse->id, 'poliklinik_id' => $clinic->id, 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-CARE', 'nama' => 'Pasien Sintetis']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-CARE', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'dokter_id' => $doctor->id, 'tanggal' => today(), 'status' => 'pemeriksaan']);
        $examination = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'dokter_id' => $doctor->id, 'anamnesis' => 'Keluhan uji', 'pemeriksaan_fisik' => 'Temuan uji']);
        Diagnosa::create(['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'R50.9', 'nama_diagnosa' => 'Diagnosis uji', 'jenis' => 'utama']);
        if ($type === 'gigi') {
            OdontogramFinding::create(['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O', 'finding_code' => 'caries', 'recorded_by_user_id' => $doctorUser->id]);
        }

        return [$doctorUser, $nurse, $visit, $examination];
    }
}
