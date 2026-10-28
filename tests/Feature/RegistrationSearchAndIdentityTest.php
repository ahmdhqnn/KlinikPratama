<?php

namespace Tests\Feature;

use App\Models\JadwalDokter;
use App\Models\Kepesertaan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationSearchAndIdentityTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string}> */
    public static function searchTerms(): array
    {
        return ['name' => ['Pasien Terdaftar'], 'medical record' => ['RM-SEARCH'], 'nik' => ['3273015205900001'], 'nip' => ['1990052020101001']];
    }

    #[DataProvider('searchTerms')]
    public function test_registered_member_is_searchable_with_complete_demographics(string $query): void
    {
        $staff = User::factory()->create(['role' => 'pendaftaran']);
        $member = Kepesertaan::factory()->create([
            'nama' => 'Pasien Terdaftar', 'nik' => '3273015205900001', 'nip' => '1990052020101001',
            'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '1990-05-20', 'jenis_kelamin' => 'P',
            'alamat' => 'Alamat direktori', 'unit_kerja' => 'Biro Umum',
        ]);
        $patient = Pasien::create([
            'no_rm' => 'RM-SEARCH', 'nama' => $member->nama, 'nik' => $member->nik,
            'kepesertaan_id' => $member->id, 'telepon' => '081234567890', 'riwayat_alergi' => 'Catatan klinis',
        ]);

        $this->actingAs($staff)->getJson(route('pendaftaran.pasien.search', ['q' => $query]))
            ->assertOk()->assertJsonCount(1)->assertJsonPath('0.id', $patient->id)
            ->assertJsonPath('0.nip', '1990052020101001')->assertJsonPath('0.nik', '3273015205900001')
            ->assertJsonPath('0.tanggal_lahir', '1990-05-20')->assertJsonPath('0.tanggal_lahir_tampil', '20 Mei 1990')->assertJsonPath('0.tempat_lahir', 'Bandung')
            ->assertJsonPath('0.alamat', 'Alamat direktori')->assertJsonPath('0.unit_kerja', 'Biro Umum')
            ->assertJsonPath('0.telepon', '081234567890')->assertJsonPath('0.eligibilityReason', null)
            ->assertJsonMissingPath('0.riwayat_alergi');
    }

    public function test_identity_mismatch_is_reported_in_search_and_cannot_create_a_visit(): void
    {
        $this->travelTo('2026-10-20 09:00:00');
        $staff = User::factory()->create(['role' => 'pendaftaran']);
        $member = Kepesertaan::factory()->create(['nip' => '1990052020101001']);
        $patient = Pasien::create(['no_rm' => 'RM-MISMATCH', 'nama' => 'Identitas berbeda', 'nik' => $member->nik, 'nip' => '1990052020101002', 'kepesertaan_id' => $member->id]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Umum', 'jenis' => 'umum', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'D-SEARCH', 'nama' => 'Dokter', 'jabatan' => 'dokter', 'kategori' => 'medis', 'is_active' => true]);
        JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $clinic->id, 'hari' => 'selasa', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true]);

        $this->actingAs($staff)->getJson(route('pendaftaran.pasien.search', ['q' => 'RM-MISMATCH']))
            ->assertOk()->assertJsonPath('0.eligibilityReason', 'NIP pasien tidak sesuai direktori kepesertaan. Perbaiki tautan identitas terlebih dahulu.');
        $this->post(route('pendaftaran.store-pasien-lama'), ['pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id])
            ->assertSessionHasErrors('pasien_id');
        $this->assertDatabaseCount('kunjungan', 0);
    }

    public function test_ineligible_members_do_not_crash_search(): void
    {
        $staff = User::factory()->create(['role' => 'pendaftaran']);
        $member = Kepesertaan::factory()->create(['hak_layanan' => false]);
        Pasien::create(['no_rm' => 'RM-INACTIVE', 'nama' => 'Peserta nonaktif', 'nik' => $member->nik, 'kepesertaan_id' => $member->id]);

        $this->actingAs($staff)->getJson(route('pendaftaran.pasien.search', ['q' => 'RM-INACTIVE']))
            ->assertOk()->assertJsonPath('0.eligibilityReason', 'Peserta tidak memiliki hak layanan aktif.');
    }

    public function test_directory_registration_identifies_members_who_already_have_records(): void
    {
        $staff = User::factory()->create(['role' => 'pendaftaran']);
        $member = Kepesertaan::factory()->create(['nama' => 'Peserta Sudah Terdaftar']);
        Pasien::create(['no_rm' => 'RM-EXISTING', 'nama' => $member->nama, 'nik' => $member->nik, 'kepesertaan_id' => $member->id]);

        $this->actingAs($staff)->getJson(route('kepesertaan.search', ['q' => 'Peserta Sudah', 'registration' => 1]))
            ->assertOk()->assertJsonPath('0.reason', 'Peserta sudah memiliki rekam medis. Gunakan tab Pasien Terdaftar.');
        $this->getJson(route('kepesertaan.search', ['q' => 'Peserta Sudah']))->assertOk()->assertJsonPath('0.reason', null);
    }

    public function test_patient_search_rejects_invalid_input_and_unauthorized_roles(): void
    {
        $staff = User::factory()->create(['role' => 'pendaftaran']);
        $this->actingAs($staff)->getJson(route('pendaftaran.pasien.search', ['q' => 'a']))->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->actingAs(User::factory()->create(['role' => 'farmasi']))
            ->getJson(route('pendaftaran.pasien.search', ['q' => 'Pasien']))->assertForbidden();
    }
}
