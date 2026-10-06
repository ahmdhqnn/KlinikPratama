<?php

namespace Tests\Feature;

use App\Models\Diagnosa;
use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_user_lands_on_a_limited_dashboard_and_cannot_open_general_routes(): void
    {
        $user = User::create([
            'name' => 'Petugas Pendaftaran',
            'email' => 'pendaftaran@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'pendaftaran',
            'is_active' => true,
        ]);

        $dashboard = $this->actingAs($user)->get('/');
        $dashboard->assertRedirect(route('pendaftaran.dashboard'));

        $response = $this->get(route('pendaftaran.dashboard'));
        $response->assertSee('Laporan Kunjungan');
        $response->assertDontSee('Master Data');

        $this->get(route('users.index'))->assertRedirect(route('pendaftaran.dashboard'));
    }

    public function test_other_staff_role_cannot_open_registration_routes(): void
    {
        $user = User::create([
            'name' => 'Petugas Farmasi',
            'email' => 'farmasi@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'farmasi',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('pendaftaran.dashboard'))->assertForbidden();
    }

    public function test_other_staff_role_cannot_manage_users(): void
    {
        $user = User::create([
            'name' => 'Petugas Farmasi',
            'email' => 'farmasi@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'farmasi',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    }

    public function test_perawat_can_access_its_dashboard_and_screening_but_not_registration_management(): void
    {
        $user = User::create([
            'name' => 'Perawat Klinik',
            'email' => 'perawat@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'perawat',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('perawat.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('pelayanan.screening.index'))->assertOk();
        $this->actingAs($user)->get(route('pendaftaran.pendaftaran-baru'))->assertForbidden();
        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    }

    public function test_admin_can_manage_users(): void
    {
        $user = User::create([
            'name' => 'Administrator',
            'email' => 'admin@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('users.index'))->assertOk();
    }

    public function test_admin_can_create_perawat_account_and_matching_nakes_profile(): void
    {
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin-create@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Perawat Baru',
            'email' => 'perawat-baru@klinik.test',
            'password' => 'password',
            'role' => 'perawat',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'email' => 'perawat-baru@klinik.test',
            'role' => 'perawat',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('nakes', [
            'nama' => 'Perawat Baru',
            'jabatan' => 'perawat',
            'user_id' => User::where('email', 'perawat-baru@klinik.test')->value('id'),
            'is_active' => true,
        ]);
    }

    public function test_new_patient_registration_saves_identity_and_creates_a_new_visit(): void
    {
        $user = User::create([
            'name' => 'Petugas Pendaftaran',
            'email' => 'pendaftaran@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'pendaftaran',
            'is_active' => true,
        ]);
        $poliklinik = Poliklinik::create([
            'kode' => 'UMUM',
            'nama' => 'Poli Umum',
            'jenis' => 'umum',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('pendaftaran.store-pasien-baru'), [
            'nama' => 'Siti Pasien',
            'nik' => '3201234567890001',
            'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1995-08-17',
            'jenis_kelamin' => 'P',
            'golongan_darah' => 'O',
            'nama_ibu' => 'Ibu Siti',
            'alamat' => 'Jl. Melati',
            'rt' => '001',
            'rw' => '002',
            'kelurahan' => 'Sukamaju',
            'kecamatan' => 'Cibeunying',
            'telepon' => '081234567890',
            'poliklinik_id' => $poliklinik->id,
            'jenis_bayar' => 'umum',
        ]);

        $pasien = Pasien::firstOrFail();
        $response->assertRedirect(route('pendaftaran.laporan-kunjungan'));
        $this->assertSame('RM-000001', $pasien->no_rm);
        $this->assertSame('3201234567890001', $pasien->nik);
        $this->assertSame('081234567890', $pasien->telepon);
        $this->assertDatabaseHas('kunjungan', [
            'pasien_id' => $pasien->id,
            'poliklinik_id' => $poliklinik->id,
            'jenis_pasien' => 'baru',
            'jenis_bayar' => 'umum',
            'status' => 'menunggu',
        ]);
    }

    public function test_registration_reports_render_with_empty_data(): void
    {
        $user = User::create([
            'name' => 'Petugas Pendaftaran',
            'email' => 'pendaftaran@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'pendaftaran',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('pendaftaran.laporan-top-diagnosa'))->assertSee('Laporan Top Diagnosa');
        $this->get(route('pendaftaran.laporan-kunjungan'))->assertSee('Laporan Kunjungan Pasien');
        $this->get(route('pendaftaran.pendaftaran-lama'))->assertSee('Pendaftaran Pasien Lama');
        $this->get(route('pendaftaran.database-pasien'))->assertSee('Database Pasien');
        $this->get(route('pendaftaran.kunjungan-per-poli'))->assertSee('Kunjungan Per-Poli');
        $this->get(route('pendaftaran.jadwal-praktik'))->assertSee('Tambah Jadwal Praktik');
    }

    public function test_existing_patient_registration_uses_the_active_doctor_schedule(): void
    {
        $user = User::create([
            'name' => 'Petugas Pendaftaran',
            'email' => 'pendaftaran@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'pendaftaran',
            'is_active' => true,
        ]);
        $poliklinik = Poliklinik::create([
            'kode' => 'UMUM',
            'nama' => 'Poli Umum',
            'jenis' => 'umum',
            'is_active' => true,
        ]);
        $pasien = Pasien::create([
            'no_rm' => 'RM-000123',
            'nama' => 'Pasien Lama',
            'jenis_kelamin' => 'L',
            'tanggal_lahir' => '1980-05-10',
        ]);
        $dokter = Nakes::create([
            'kode' => 'DR-01',
            'nama' => 'Dokter Aktif',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'is_active' => true,
        ]);
        JadwalDokter::create([
            'dokter_id' => $dokter->id,
            'poliklinik_id' => $poliklinik->id,
            'hari' => strtolower(now()->locale('id')->dayName),
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('pendaftaran.store-pasien-lama'), [
            'pasien_id' => $pasien->id,
            'poliklinik_id' => $poliklinik->id,
            'dokter_id' => $dokter->id,
            'jenis_bayar' => 'umum',
        ]);

        $response->assertRedirect(route('pendaftaran.laporan-kunjungan'));
        $this->assertDatabaseHas('kunjungan', [
            'pasien_id' => $pasien->id,
            'dokter_id' => $dokter->id,
            'jenis_pasien' => 'lama',
        ]);
    }

    public function test_top_diagnosis_report_uses_recorded_diagnosis_rows(): void
    {
        $user = User::create([
            'name' => 'Petugas Pendaftaran',
            'email' => 'pendaftaran@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'pendaftaran',
            'is_active' => true,
        ]);
        $poliklinik = Poliklinik::create([
            'kode' => 'UMUM',
            'nama' => 'Poli Umum',
            'jenis' => 'umum',
            'is_active' => true,
        ]);
        $pasien = Pasien::create(['no_rm' => 'RM-000123', 'nama' => 'Pasien', 'tanggal_lahir' => '1980-05-10']);
        $kunjungan = Kunjungan::create([
            'no_kunjungan' => 'KNJ-20261006-0001',
            'pasien_id' => $pasien->id,
            'poliklinik_id' => $poliklinik->id,
            'tanggal' => today(),
            'status' => 'selesai',
        ]);
        $pemeriksaan = Pemeriksaan::create(['kunjungan_id' => $kunjungan->id]);
        Diagnosa::create([
            'pemeriksaan_id' => $pemeriksaan->id,
            'kode_icd10' => 'R50.9',
            'nama_diagnosa' => 'Demam tidak spesifik',
            'jenis' => 'utama',
        ]);

        $this->actingAs($user)
            ->get(route('pendaftaran.laporan-top-diagnosa', [
                'tanggal_mulai' => today()->toDateString(),
                'tanggal_selesai' => today()->toDateString(),
            ]))
            ->assertSee('R50.9')
            ->assertSee('Demam tidak spesifik')
            ->assertSee('Jumlah Kasus');
    }
}
