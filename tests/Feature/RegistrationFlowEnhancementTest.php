<?php

namespace Tests\Feature;

use App\Models\Diagnosa;
use App\Models\Kepesertaan;
use App\Models\KlinikSetting;
use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class RegistrationFlowEnhancementTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['admin'])]
    #[TestWith(['pendaftaran'])]
    public function test_patient_identity_can_be_viewed_and_updated_without_replacing_clinical_history(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $patient = Pasien::create(['no_rm' => 'RM-EDIT', 'nama' => 'Pasien', 'nama_ibu' => 'Ibu Pasien', 'riwayat_alergi' => 'Penisilin']);

        $this->actingAs($user)->get(route('pendaftaran.database-pasien'))->assertInertia(fn (Assert $page) => $page
            ->where('patients.data.0.viewUrl', route('pelayanan.pasien.show', $patient))
            ->where('patients.data.0.editUrl', route('pelayanan.pasien.edit', $patient)));
        $this->get(route('pelayanan.pasien.show', $patient))->assertInertia(fn (Assert $page) => $page
            ->where('permissions.editPatient', true)->where('permissions.viewRme', false)->where('patient.allergies', null));
        $this->get(route('pendaftaran.pendaftaran-baru', ['tab' => 'registered', 'pasien_id' => $patient->id]))
            ->assertInertia(fn (Assert $page) => $page->where('initialTab', 'registered')->where('initialPatient.id', $patient->id));
        $this->get(route('pelayanan.pasien.edit', $patient))->assertInertia(fn (Assert $page) => $page
            ->where('patient.motherName', 'Ibu Pasien')->missing('patient.allergyHistory'));
        $this->put(route('pelayanan.pasien.update', $patient), [
            'registration_type' => 'manual', 'nama' => 'Nama Diperbarui', 'nama_ibu' => 'Ibu Pasien', 'agama' => 'Lainnya',
            'telepon' => '08123456789', 'riwayat_alergi' => 'Tidak ada', 'no_rm' => 'RM-TAMPER', 'kepesertaan_id' => 999,
        ])->assertSessionHasNoErrors()->assertRedirect(route('pelayanan.pasien.show', $patient));

        $this->assertDatabaseHas('pasien', ['id' => $patient->id, 'nama' => 'Nama Diperbarui', 'agama' => 'Lainnya',
            'nama_ibu' => 'Ibu Pasien', 'no_rm' => 'RM-EDIT', 'kepesertaan_id' => null, 'riwayat_alergi' => 'Penisilin']);
        $this->assertDatabaseHas('clinical_audit_events', ['actor_id' => $user->id, 'action' => 'patient.identity_updated', 'patient_id' => $patient->id]);
    }

    public function test_verified_identity_stays_in_sync_without_granting_membership_privileges(): void
    {
        $member = Kepesertaan::factory()->create(['agama' => 'Islam']);
        $patient = Pasien::create(['no_rm' => 'RM-MEMBER', 'nama' => $member->nama, 'nik' => $member->nik, 'nip' => $member->nip, 'kepesertaan_id' => $member->id]);
        $user = User::factory()->create(['role' => 'pendaftaran']);

        $this->actingAs($user)->put(route('pelayanan.pasien.update', $patient), [
            'nama' => $member->nama, 'nik' => $member->nik, 'nip' => $member->nip,
            'agama' => 'Lainnya', 'alamat' => 'Alamat baru', 'hak_layanan' => false, 'kategori' => 'lainnya',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pasien', ['id' => $patient->id, 'agama' => 'Lainnya', 'alamat' => 'Alamat baru']);
        $this->assertDatabaseHas('kepesertaan', ['id' => $member->id, 'agama' => 'Lainnya', 'alamat' => 'Alamat baru', 'hak_layanan' => true, 'kategori' => 'asn']);

        $this->put(route('pelayanan.pasien.update', $patient), ['nama' => 'Tidak boleh tersimpan', 'nik' => '1234567890123456'])
            ->assertSessionHasErrors('nik');
        $this->assertSame($member->nama, $patient->fresh()->nama);
        $this->delete(route('pelayanan.pasien.destroy', $patient))->assertForbidden();
        $this->post(route('pelayanan.pasien.verify-membership', $patient), [])->assertRedirect(route('pendaftaran.dashboard'));
        $this->assertNotSoftDeleted($patient);
    }

    public function test_identity_suggestions_are_relevant_deduplicated_and_do_not_expose_patient_attributes(): void
    {
        $user = User::factory()->create(['role' => 'pendaftaran']);
        Pasien::create(['no_rm' => 'RM-AREA', 'nama' => 'Nama rahasia', 'tempat_lahir' => 'Bandung', 'kelurahan' => 'Sukamaju', 'kecamatan' => 'Cibiru']);
        Kepesertaan::factory()->create(['tempat_lahir' => 'Bandung', 'kelurahan' => 'Sukasari', 'kecamatan' => 'Cicendo']);
        Kepesertaan::factory()->create(['tempat_lahir' => 'Banjar']);

        $this->actingAs($user)->getJson(route('pendaftaran.identity-suggestions', ['field' => 'tempat_lahir', 'q' => 'Ban']))
            ->assertExactJson(['Bandung', 'Banjar']);
        $this->getJson(route('pendaftaran.identity-suggestions', ['field' => 'kelurahan', 'q' => 'Suka', 'kecamatan' => 'Cibiru']))
            ->assertExactJson(['Sukamaju']);
        $this->getJson(route('pendaftaran.identity-suggestions', ['field' => 'riwayat_alergi', 'q' => 'Pen']))
            ->assertUnprocessable()->assertJsonValidationErrors('field');
    }

    #[TestWith(['dokter'])]
    #[TestWith(['perawat'])]
    #[TestWith(['farmasi'])]
    #[TestWith(['manajemen'])]
    public function test_other_roles_cannot_edit_identity_or_export_registration_reports(string $role): void
    {
        $patient = Pasien::create(['no_rm' => 'RM-LOCK', 'nama' => 'Pasien terlindungi']);
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('pelayanan.pasien.edit', $patient))->assertForbidden();
        $this->put(route('pelayanan.pasien.update', $patient), ['nama' => 'Terubah'])->assertForbidden();
        $this->get(route('pendaftaran.laporan-kunjungan.export'))->assertForbidden();
        $this->get(route('pendaftaran.laporan-top-diagnosa.export'))->assertForbidden();
        $this->getJson(route('pendaftaran.identity-suggestions', ['field' => 'kecamatan', 'q' => 'Ci']))->assertForbidden();
        $this->assertSame('Pasien terlindungi', $patient->fresh()->nama);
    }

    #[TestWith(['admin'])]
    #[TestWith(['pendaftaran'])]
    public function test_export_contains_all_matching_rows_and_respects_applied_filters(string $role): void
    {
        $this->travelTo('2026-10-26 09:00:00');
        $visit = $this->visit('EXPORT', '2026-10-26', 'selesai');
        for ($index = 1; $index <= 11; $index++) {
            Kunjungan::create([...$visit->only(['pasien_id', 'poliklinik_id', 'tanggal', 'status']), 'no_kunjungan' => 'EXPORT-'.$index]);
        }
        $this->visit('EXCLUDED', '2026-10-25', 'menunggu');
        $filters = ['tanggal' => '2026-10-26', 'status' => 'selesai', 'search' => 'Pasien EXPORT', 'poliklinik_id' => $visit->poliklinik_id];
        $user = User::factory()->create(['role' => $role]);
        $page = $this->actingAs($user)->get(route('pendaftaran.laporan-kunjungan', $filters));
        $page->assertInertia(fn (Assert $page) => $page->where('visits.total', 12)->has('visits.data', 10));

        $response = $this->get($page->inertiaProps('exportUrl'))->assertDownload('laporan-kunjungan-2026-10-26.xlsx');
        $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $rows = $book->getActiveSheet()->toArray();
        $this->assertCount(13, $rows);
        $this->assertSame(['Pasien EXPORT'], array_values(array_unique(array_column(array_slice($rows, 1), 3))));
        $book->disconnectWorksheets();
        $this->assertDatabaseHas('clinical_audit_events', ['actor_id' => $user->id, 'action' => 'visit_report.export']);
    }

    public function test_diagnosis_export_follows_period_and_does_not_expose_patient_identity(): void
    {
        $this->travelTo('2026-10-26 09:00:00');
        $visit = $this->visit('DIAG', '2026-10-26', 'selesai');
        $exam = Pemeriksaan::create(['kunjungan_id' => $visit->id, 'status' => 'selesai']);
        Diagnosa::create(['pemeriksaan_id' => $exam->id, 'kode_icd10' => 'J00', 'nama_diagnosa' => 'Nasofaringitis', 'jenis' => 'utama']);
        $older = $this->visit('OLD-DIAG', '2026-10-25', 'selesai');
        $oldExam = Pemeriksaan::create(['kunjungan_id' => $older->id, 'status' => 'selesai']);
        Diagnosa::create(['pemeriksaan_id' => $oldExam->id, 'kode_icd10' => 'I10', 'nama_diagnosa' => 'Hipertensi', 'jenis' => 'utama']);

        $page = $this->actingAs(User::factory()->create(['role' => 'pendaftaran']))->get(route('pendaftaran.laporan-top-diagnosa', ['tanggal_mulai' => '2026-10-26']));
        $response = $this->get($page->inertiaProps('exportUrl'))->assertDownload('laporan-diagnosis-2026-10-26.xlsx');
        $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $this->assertSame([['Kode ICD-10', 'Diagnosis', 'Jumlah kasus'], ['J00', 'Nasofaringitis', 1]], $book->getActiveSheet()->toArray(formatData: false));
        $book->disconnectWorksheets();
    }

    public function test_today_summary_ignores_list_filters_and_print_uses_clinic_settings(): void
    {
        $this->travelTo('2026-10-26 09:00:00');
        KlinikSetting::create(['nama_klinik' => 'Klinik Internal', 'alamat' => 'Alamat Klinik', 'telepon' => '021123456']);
        $today = $this->visit('TODAY', '2026-10-26', 'selesai');
        $older = $this->visit('OLD', '2026-10-25', 'menunggu');
        $this->visit('FUTURE', '2026-10-27', 'menunggu');
        $user = User::factory()->create(['role' => 'pendaftaran']);

        $this->actingAs($user)->get(route('pendaftaran.kunjungan-per-poli', ['tanggal' => '2026-10-25', 'status' => 'menunggu']))
            ->assertInertia(fn (Assert $page) => $page->where('stats.completedToday', 1)->where('stats.total', 3)->where('stats.waiting', 1)
                ->where('visits.total', 1)->where('visits.data.0.id', $older->id)
                ->where('recentVisits.total', 1)->where('recentVisits.data.0.id', $today->id));
        $this->get(route('pendaftaran.cetak-antrian', $today))->assertInertia(fn (Assert $page) => $page
            ->where('clinic.name', 'Klinik Internal')->where('clinic.address', 'Alamat Klinik')->where('ticket.isReservation', false));
    }

    private function visit(string $number, string $date, string $status): Kunjungan
    {
        $patient = Pasien::create(['no_rm' => 'RM-'.$number, 'nama' => 'Pasien '.$number]);
        $clinic = Poliklinik::firstOrCreate(['kode' => 'FLOW-UMUM'], ['nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);

        return Kunjungan::create(['no_kunjungan' => $number, 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id,
            'tanggal' => $date, 'status' => $status, 'jenis_pasien' => 'lama']);
    }
}
