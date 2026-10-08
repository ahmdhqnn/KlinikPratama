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
use Inertia\Testing\AssertableInertia as Assert;
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

        $this->get(route('pendaftaran.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/dashboard')
            ->where('auth.user.role', 'pendaftaran')
            ->has('stats.visitsToday')
            ->has('stats.newPatientsToday')
        );

        $this->get(route('users.index'))->assertRedirect(route('pendaftaran.dashboard'));
    }

    public function test_registration_user_can_open_visit_editor_and_print_queue_ticket_as_inertia_pages(): void
    {
        $user = User::create([
            'name' => 'Petugas Pendaftaran',
            'email' => 'pendaftaran-tiket@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'pendaftaran',
            'is_active' => true,
        ]);
        $patient = Pasien::create(['no_rm' => 'RM-009901', 'nama' => 'Pasien Uji', 'jenis_kelamin' => 'P']);
        $clinic = Poliklinik::create([
            'kode' => 'POLI-09',
            'nama' => 'Poli Umum',
            'jenis' => 'umum',
            'is_active' => true,
        ]);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-009901',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today(),
            'status' => 'menunggu',
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
        ]);

        $this->actingAs($user)->get(route('pendaftaran.edit-kunjungan', $visit))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pendaftaran/edit-kunjungan')
                ->where('visit.patient.name', 'Pasien Uji')
                ->where('visit.clinicId', (string) $clinic->id)
                ->has('doctorsUrl')
            );

        $this->get(route('pendaftaran.cetak-antrian', $visit))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pendaftaran/cetak-antrian')
                ->where('ticket.queueNumber', 1)
                ->where('ticket.patient', 'Pasien Uji')
            );

        $visit->update(['status' => 'pemeriksaan']);
        $this->get(route('pendaftaran.edit-kunjungan', $visit))->assertUnprocessable();
        $this->post(route('pendaftaran.batal-kunjungan', $visit))->assertUnprocessable();
        $this->get(route('pendaftaran.laporan-kunjungan'))->assertInertia(fn (Assert $page) => $page
            ->where('visits.data.0.editUrl', null)
            ->where('visits.data.0.cancelUrl', null)
        );
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
        $clinic = Poliklinik::create([
            'kode' => 'UMUM',
            'nama' => 'Poli Umum',
            'jenis' => 'umum',
            'is_active' => true,
        ]);
        $patient = Pasien::create(['no_rm' => 'RM-000010', 'nama' => 'Pasien Skrining']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-0010',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today(),
            'status' => 'menunggu',
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
        ]);

        $this->actingAs($user)->get(route('perawat.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('perawat/dashboard')
            ->where('auth.user.role', 'perawat')
            ->has('stats.menunggu_ttv')
            ->has('visits')
        );
        $this->actingAs($user)->get(route('pelayanan.screening.index'))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/screening/index')
            ->where('auth.user.role', 'perawat')
            ->has('visits', 1)
        );
        $this->actingAs($user)->get(route('pelayanan.screening.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/screening/show')
            ->where('visit.patient.name', 'Pasien Skrining')
            ->has('visit.screening')
            ->has('staff')
        );
        $this->actingAs($user)->post(route('pelayanan.kunjungan.screening.store', $visit), [
            'nyeri_dada' => 'tidak',
            'kondisi_psikiatri' => 'normal',
            'nadi_teraba' => 'teraba',
            'kejang' => 'tidak',
            'pola_pernapasan' => 'normal',
            'kesadaran' => 'sadar',
            'risiko_jatuh_visual' => 'rendah',
        ])->assertRedirect(route('pelayanan.kunjungan.show', $visit));
        $this->assertDatabaseHas('screening', [
            'kunjungan_id' => $visit->id,
            'kesimpulan_triase' => 'hijau',
            'prioritas_layanan' => 'normal',
        ]);
        $this->assertDatabaseHas('kunjungan', [
            'id' => $visit->id,
            'status' => 'pemeriksaan',
        ]);
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
        $this->actingAs($user)->get(route('pendaftaran.laporan-kunjungan', ['tanggal' => today()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pendaftaran/laporan-kunjungan')
                ->has('visits.data', 1)
                ->where('visits.data.0.patient', 'Siti Pasien')
                ->where('visits.data.0.status', 'menunggu')
            );
        $this->actingAs($user)->get(route('pendaftaran.database-pasien', ['search' => 'RM-000001']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pendaftaran/database-pasien')
                ->has('patients.data', 1)
                ->where('patients.data.0.name', 'Siti Pasien')
            );
        $this->actingAs($user)->get(route('pendaftaran.kunjungan-per-poli', [
            'poliklinik_id' => $poliklinik->id,
            'tanggal' => today()->toDateString(),
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/kunjungan-per-poli')
            ->where('stats.total', 1)
            ->where('stats.waiting', 1)
            ->where('visits.0.patient', 'Siti Pasien')
        );
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

        $this->actingAs($user)->get(route('pendaftaran.laporan-top-diagnosa'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/laporan-top-diagnosa')
            ->where('summary.diagnosisCount', 0)
            ->where('summary.caseCount', 0)
            ->has('diagnoses', 0)
        );
        $this->get(route('pendaftaran.laporan-kunjungan'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/laporan-kunjungan')
            ->has('clinics')
            ->has('visits.data', 0)
        );
        $this->get(route('pendaftaran.pendaftaran-lama'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/pendaftaran-lama')
            ->has('clinics')
            ->has('insuranceProviders')
        );
        $this->get(route('pendaftaran.pendaftaran-baru'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/pendaftaran-baru')
            ->has('clinics')
            ->has('insuranceProviders')
            ->where('today', today()->toDateString())
        );
        $this->get(route('pendaftaran.database-pasien'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/database-pasien')
            ->has('patients.data', 0)
        );
        $this->get(route('pendaftaran.kunjungan-per-poli'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/kunjungan-per-poli')
            ->has('stats.total')
            ->has('visits', 0)
        );
        $this->get(route('pendaftaran.jadwal-praktik'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/jadwal-praktik')
            ->has('days', 7)
            ->has('schedules', 0)
        );
    }

    public function test_nurse_monitoring_pages_do_not_offer_registration_only_actions(): void
    {
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create([
            'kode' => 'MON-UMUM',
            'nama' => 'Poli Umum',
            'jenis' => 'umum',
            'is_active' => true,
        ]);
        $patient = Pasien::create(['no_rm' => 'RM-MON-001', 'nama' => 'Pasien Pantau']);
        Kunjungan::create([
            'no_kunjungan' => 'KNJ-MON-001',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today(),
            'status' => 'menunggu',
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
        ]);

        $this->actingAs($nurse)->get(route('pendaftaran.kunjungan-per-poli', ['poliklinik_id' => $clinic->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visits.0.ticketUrl', null)
            );
        $this->get(route('pendaftaran.jadwal-praktik'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', false)
            );
        $this->post(route('pendaftaran.store-jadwal-praktik'))->assertForbidden();
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
            ->assertInertia(fn (Assert $page) => $page
                ->component('pendaftaran/laporan-top-diagnosa')
                ->where('summary.diagnosisCount', 1)
                ->where('summary.caseCount', 1)
                ->where('diagnoses.0.code', 'R50.9')
                ->where('diagnoses.0.percentage', 100)
            );
    }
}
