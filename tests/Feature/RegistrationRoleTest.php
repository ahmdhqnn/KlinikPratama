<?php

namespace Tests\Feature;

use App\Models\Diagnosa;
use App\Models\JadwalDokter;
use App\Models\Kepesertaan;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\Screening;
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
            ->has('stats.totalVisits')
        );

        $this->get(route('users.index'))->assertRedirect(route('pendaftaran.dashboard'));
    }

    public function test_registration_dashboard_counts_all_visits_across_clinics_and_dates(): void
    {
        $user = User::create([
            'name' => 'Petugas Pendaftaran',
            'email' => 'pendaftaran-total@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'pendaftaran',
            'is_active' => true,
        ]);
        $patient = Pasien::create(['no_rm' => 'RM-009998', 'nama' => 'Pasien Uji']);
        foreach (['umum', 'gigi'] as $clinicType) {
            $clinic = Poliklinik::create([
                'kode' => strtoupper($clinicType),
                'nama' => 'Poli '.ucfirst($clinicType),
                'jenis' => $clinicType,
                'is_active' => true,
            ]);
            Kunjungan::create([
                'no_kunjungan' => 'KNJ-'.strtoupper($clinicType),
                'pasien_id' => $patient->id,
                'poliklinik_id' => $clinic->id,
                'tanggal' => $clinicType === 'umum' ? today() : today()->subDay(),
                'status' => 'menunggu',
                'jenis_pasien' => 'lama',
            ]);
        }

        $this->actingAs($user)->get(route('pendaftaran.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.visitsToday', 1)
            ->where('stats.totalVisits', 2)
            ->missing('stats.newPatientsToday')
        );
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
        ]);
        $list = $this->actingAs($user)->get(route('pendaftaran.kunjungan-per-poli'));
        $list->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('visits.data.0.actionUrl', route('pendaftaran.kunjungan.show', $visit))
            ->where('recentVisits.data.0.actionUrl', route('pendaftaran.kunjungan.show', $visit))
        );
        $detail = $this->get($list->inertiaProps('visits.data.0.actionUrl'));
        $detail->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/kunjungan/show')
            ->where('visit.number', $visit->no_kunjungan)
            ->where('backUrl', route('pendaftaran.kunjungan-per-poli'))
            ->where('permissions.editVisit', true)
            ->where('permissions.viewRme', false)
            ->where('permissions.processScreening', false)
            ->where('links.edit', route('pendaftaran.edit-kunjungan', $visit))
            ->where('links.patient', route('pendaftaran.database-pasien', ['search' => $patient->no_rm]))
            ->where('links.medicalRecord', null)
            ->where('links.screening', null)
            ->where('visit.patient.allergies', null)
            ->missing('visit.pemeriksaan')
        );
        $this->get($detail->inertiaProps('links.edit'))->assertOk();
        $this->get($detail->inertiaProps('links.patient'))->assertOk();
        $this->get($detail->inertiaProps('backUrl'))->assertOk();
        $this->get(route('pelayanan.kunjungan.show', $visit))
            ->assertRedirect(route('pendaftaran.kunjungan.show', $visit));
        $this->get(route('pelayanan.pasien.rekam-medis', $patient))->assertForbidden();

        $editor = $this->actingAs($user)->get(route('pendaftaran.edit-kunjungan', $visit));
        $editor->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/edit-kunjungan')
            ->where('visit.patient.name', 'Pasien Uji')
            ->where('visit.clinicId', (string) $clinic->id)
            ->where('backUrl', route('pendaftaran.kunjungan.show', $visit))
            ->missing('cancelUrl')
            ->has('doctorsUrl')
        );
        $this->get($editor->inertiaProps('backUrl'))->assertOk();

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
        $this->get(route('pendaftaran.kunjungan.show', $visit))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('permissions.editVisit', false)
            ->where('links.edit', null)
        );

        $visit->update(['status' => 'batal']);
        $this->get(route('pendaftaran.cetak-antrian', $visit))->assertUnprocessable();
        $this->get(route('pendaftaran.kunjungan-per-poli'))->assertInertia(fn (Assert $page) => $page
            ->where('visits.data.0.ticketUrl', null)
        );
        $this->get(route('pendaftaran.kunjungan.show', $visit))->assertOk();

        foreach (['dokter', 'perawat', 'farmasi'] as $role) {
            $staff = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($staff)->get(route('pendaftaran.kunjungan.show', $visit))->assertForbidden();
        }
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
        Nakes::create([
            'kode' => 'NURSE-ROLE-1',
            'nama' => $user->name,
            'kategori' => 'medis',
            'jabatan' => 'perawat',
            'poliklinik_id' => $clinic->id,
            'user_id' => $user->id,
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
        ]);
        $historicalPatient = Pasien::create(['no_rm' => 'RM-000011', 'nama' => 'Pasien Riwayat']);
        Kunjungan::create([
            'no_kunjungan' => 'KNJ-0011',
            'pasien_id' => $historicalPatient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today()->subDay(),
            'status' => 'pemeriksaan',
            'jenis_pasien' => 'lama',
        ]);
        $draftPatient = Pasien::create(['no_rm' => 'RM-000012', 'nama' => 'Pasien Pengkajian Draf']);
        $draftVisit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-0012',
            'pasien_id' => $draftPatient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today()->subDay(),
            'status' => 'screening',
            'jenis_pasien' => 'lama',
        ]);
        Screening::create([
            'kunjungan_id' => $draftVisit->id,
            'petugas_id' => Nakes::where('user_id', $user->id)->value('id'),
            'started_at' => now()->subDay(),
        ]);

        $this->actingAs($user)->get(route('perawat.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('perawat/dashboard')
            ->where('auth.user.role', 'perawat')
            ->where('stats.total_kunjungan', 3)
            ->where('stats.total_kunjungan_hari_ini', 1)
            ->where('stats.menunggu_ttv', 1)
            ->where('stats.menunggu_ttv_hari_ini', 1)
            ->where('stats.siap_dokter', 1)
            ->where('stats.siap_dokter_hari_ini', 0)
            ->has('visits.data', 1)
            ->where('visits.data.0.actionUrl', route('pelayanan.screening.show', $visit))
            ->where('visits.data.0.actionLabel', 'Mulai pengkajian')
        );
        $this->actingAs($user)->get(route('pelayanan.screening.index'))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/screening/index')
            ->where('auth.user.role', 'perawat')
            ->has('todayVisits.data', 1)
            ->where('todayVisits.data.0.patient', 'Pasien Skrining')
            ->has('visits.data', 3)
            ->where('visits.data.1.patient', 'Pasien Pengkajian Draf')
            ->where('visits.data.1.status', 'screening')
            ->where('visits.data.1.hasScreening', true)
            ->where('visits.data.1.screeningUrl', route('pelayanan.screening.show', $draftVisit))
            ->where('visits.data.2.patient', 'Pasien Riwayat')
            ->where('visits.data.2.actionLabel', 'Lihat RME')
        );
        $this->get(route('pelayanan.screening.index', ['tanggal' => today()->subDay()->toDateString()]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visits.total', 2)->where('todayVisits.total', 1)
                ->where('visits.data.0.patient', 'Pasien Pengkajian Draf'));
        $this->actingAs($user)->get(route('pelayanan.screening.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/screening/show')
            ->where('visit.patient.name', 'Pasien Skrining')
            ->has('visit.screening')
            ->has('staff')
        );
        $this->actingAs($user)->post(route('pelayanan.kunjungan.screening.store', $visit), [
            'keluhan' => 'Keluhan pasien', 'lama_keluhan' => '2 hari', 'sumber_informasi' => 'pasien',
            'status_alergi' => 'belum_diketahui', 'status_riwayat_penyakit' => 'belum_diketahui', 'konfirmasi_riwayat' => true,
            'triase_perawat' => 'hijau',
            'td_sistole' => 120, 'td_diastole' => 80, 'nadi' => 80, 'suhu' => 36.5, 'respirasi' => 18, 'berat_badan' => 60, 'tinggi_badan' => 160,
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
        $this->get(route('pelayanan.pasien.rekam-medis', $patient))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pasien/rekam-medis'));
        $this->get(route('perawat.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('visits.total', 0));
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
        $clinic = Poliklinik::create([
            'kode' => 'USER-PRW-UMUM',
            'nama' => 'Poli Umum',
            'jenis' => 'umum',
            'is_active' => true,
        ]);

        $accountData = [
            'name' => 'Perawat Baru',
            'email' => 'perawat-baru@klinik.test',
            'password' => 'password',
            'role' => 'perawat',
            'is_active' => '1',
        ];
        $this->actingAs($admin)->post(route('users.store'), $accountData)->assertSessionHasErrors('poliklinik_id');
        $this->assertDatabaseMissing('users', ['email' => 'perawat-baru@klinik.test']);

        $response = $this->actingAs($admin)->post(route('users.store'), [
            ...$accountData,
            'poliklinik_id' => $clinic->id,
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
            'poliklinik_id' => $clinic->id,
            'is_active' => true,
        ]);
    }

    public function test_new_patient_registration_saves_identity_and_creates_a_new_visit(): void
    {
        $this->travelTo('2026-10-16 09:00:00');
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
        $doctor = Nakes::create(['kode' => 'DR-REG', 'nama' => 'Dokter Pendaftaran', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $poliklinik->id, 'hari' => 'jumat', 'berlaku_mulai' => '2026-10-16', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true]);

        $member = Kepesertaan::factory()->create([
            'nama' => 'Siti Pasien', 'nik' => '3201234567890001', 'tempat_lahir' => 'Bandung',
            'tanggal_lahir' => '1995-08-17', 'jenis_kelamin' => 'P', 'golongan_darah' => 'O', 'agama' => 'Islam',
            'alamat' => 'Jl. Melati', 'rt' => '001', 'rw' => '002', 'kelurahan' => 'Sukamaju', 'kecamatan' => 'Cibeunying',
        ]);
        $response = $this->actingAs($user)->post(route('pendaftaran.store-pasien-baru'), [
            'kepesertaan_id' => $member->id,
            'nama' => 'IDENTITAS PALSU',
            'nik' => '9999999999999999',
            'tanggal_lahir' => '2000-01-01',
            'jenis_kelamin' => 'L',
            'nama_ibu' => 'Ibu Siti',
            'telepon' => '081234567890',
            'poliklinik_id' => $poliklinik->id,
        ]);

        $pasien = Pasien::firstOrFail();
        $response->assertRedirect(route('pendaftaran.kunjungan-per-poli', ['tanggal' => today()->toDateString()]));
        $this->assertSame('RM-000001', $pasien->no_rm);
        $this->assertSame('3201234567890001', $pasien->nik);
        $this->assertSame('1995-08-17', $pasien->tanggal_lahir?->toDateString());
        $this->assertSame('P', $pasien->jenis_kelamin);
        $this->assertSame('Jl. Melati', $pasien->alamat);
        $this->assertSame('001', $pasien->rt);
        $this->assertSame('081234567890', $pasien->telepon);
        $this->assertNull($pasien->nama_ibu);
        $this->assertDatabaseHas('kunjungan', [
            'pasien_id' => $pasien->id,
            'poliklinik_id' => $poliklinik->id,
            'jenis_pasien' => 'baru',
            'status' => 'menunggu',
        ]);
        $kunjungan = Kunjungan::where('pasien_id', $pasien->id)->firstOrFail();
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
            ->where('visits.data.0.patient', 'Siti Pasien')
            ->where('visits.data.0.actionLabel', 'Detail')
            ->where('visits.data.0.editUrl', route('pendaftaran.edit-kunjungan', $kunjungan))
            ->where('visits.data.0.cancelUrl', route('pendaftaran.batal-kunjungan', $kunjungan))
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
            ->has('diagnoses.data', 0)
        );
        $this->get(route('pendaftaran.laporan-kunjungan'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/laporan-kunjungan')
            ->has('clinics')
            ->has('visits.data', 0)
        );
        $this->get(route('pendaftaran.pendaftaran-lama'))->assertRedirect(route('pendaftaran.pendaftaran-baru', ['tab' => 'registered']));
        $this->get(route('pendaftaran.pendaftaran-baru'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/pendaftaran-baru')
            ->has('clinics')
            ->missing('insuranceProviders')
            ->where('today', today()->toDateString())
        );
        $this->get(route('pendaftaran.database-pasien'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/database-pasien')
            ->has('patients.data', 0)
        );
        $this->get(route('pendaftaran.kunjungan-per-poli'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/kunjungan-per-poli')
            ->has('stats.total')
            ->has('visits.data', 0)
        );
        $this->get(route('pendaftaran.jadwal-praktik'))->assertInertia(fn (Assert $page) => $page
            ->component('pendaftaran/jadwal-praktik')
            ->has('days', 7)
            ->has('schedules.data', 0)
        );
    }

    public function test_admin_can_access_the_same_registration_pages_and_enhanced_patient_directory(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get(route('pendaftaran.dashboard'))->assertOk();
        $this->get(route('pendaftaran.pendaftaran-baru'))->assertOk();
        $this->get(route('pendaftaran.pendaftaran-lama'))->assertRedirect(route('pendaftaran.pendaftaran-baru', ['tab' => 'registered']));
        $this->get(route('pelayanan.pasien.index'))->assertOk();
        $this->get(route('kepesertaan.index'))->assertOk();
    }

    public function test_clinic_visit_monitoring_separates_all_history_from_today(): void
    {
        $this->travelTo('2026-10-19 09:00:00');
        $user = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $clinic = Poliklinik::create([
            'kode' => 'RECENT-UMUM',
            'nama' => 'Poli Umum',
            'jenis' => 'umum',
            'is_active' => true,
        ]);
        $patientToday = Pasien::create(['no_rm' => 'RM-RECENT-01', 'nama' => 'Pasien Hari Ini']);
        $patientPrevious = Pasien::create(['no_rm' => 'RM-RECENT-02', 'nama' => 'Pasien Sebelumnya']);

        Kunjungan::create([
            'no_kunjungan' => 'KNJ-RECENT-01', 'pasien_id' => $patientToday->id, 'poliklinik_id' => $clinic->id,
            'tanggal' => '2026-10-19', 'status' => 'menunggu', 'jenis_pasien' => 'lama',
        ]);
        $previousVisit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-RECENT-02', 'pasien_id' => $patientPrevious->id, 'poliklinik_id' => $clinic->id,
            'tanggal' => '2026-10-18', 'status' => 'selesai', 'jenis_pasien' => 'lama',
        ]);
        $previousVisit->forceFill(['created_at' => now()->subDay()])->save();

        $this->actingAs($user)->get(route('pendaftaran.kunjungan-per-poli', ['poliklinik_id' => $clinic->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pendaftaran/kunjungan-per-poli')
                ->has('visits.data', 2)
                ->where('visits.data.0.patient', 'Pasien Hari Ini')
                ->has('recentVisits.data', 1)
                ->where('recentVisits.data.0.patient', 'Pasien Hari Ini')
                ->where('visits.data.1.patient', 'Pasien Sebelumnya')
                ->where('visits.data.1.date', '18/10/2026')
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
        Nakes::create([
            'kode' => 'NURSE-MON-1',
            'nama' => $nurse->name,
            'kategori' => 'medis',
            'jabatan' => 'perawat',
            'poliklinik_id' => $clinic->id,
            'user_id' => $nurse->id,
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
        ]);
        $doctor = Nakes::create(['kode' => 'MON-DR', 'nama' => 'Dokter Pantau', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        $schedule = JadwalDokter::create([
            'dokter_id' => $doctor->id,
            'poliklinik_id' => $clinic->id,
            'hari' => 'senin',
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
        ]);

        $this->actingAs($nurse)->get(route('pendaftaran.kunjungan-per-poli', ['poliklinik_id' => $clinic->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visits.data.0.ticketUrl', null)
                ->where('visits.data.0.actionLabel', 'Mulai pengkajian')
                ->where('visits.data.0.actionUrl', route('pelayanan.screening.show', Kunjungan::firstOrFail()))
                ->where('visits.data.0.editUrl', null)
                ->where('visits.data.0.cancelUrl', null)
            );
        $this->get(route('pendaftaran.jadwal-praktik'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', true)
                ->has('schedules.data', 1)
            );
        $this->post(route('pendaftaran.store-jadwal-praktik'))->assertSessionHasErrors();
        $this->put(route('pendaftaran.update-jadwal-praktik', $schedule))->assertSessionHasErrors();
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
        $member = Kepesertaan::factory()->create(['nama' => $pasien->nama]);
        $pasien->update(['kepesertaan_id' => $member->id, 'nik' => $member->nik]);
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
        ]);

        $response->assertRedirect(route('pendaftaran.kunjungan-per-poli', ['tanggal' => today()->toDateString()]));
        $this->assertDatabaseHas('kunjungan', [
            'pasien_id' => $pasien->id,
            'dokter_id' => $dokter->id,
            'jenis_pasien' => 'lama',
        ]);
    }

    public function test_future_practice_schedules_keep_their_validity_period_and_reject_overlaps(): void
    {
        $this->travelTo('2026-10-16 09:00:00');
        $registrationUser = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DR-10', 'nama' => 'Dokter Jadwal', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);
        $scheduleData = [
            'dokter_id' => $doctor->id,
            'poliklinik_id' => $clinic->id,
            'hari' => 'jumat',
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
            'berlaku_mulai' => '2026-10-16',
            'berlaku_sampai' => '2026-10-30',
        ];

        $this->actingAs($registrationUser)->post(route('pendaftaran.store-jadwal-praktik'), $scheduleData)
            ->assertSessionHasNoErrors();

        $schedule = JadwalDokter::where('dokter_id', $doctor->id)->firstOrFail();
        $this->assertSame('2026-10-16', $schedule->berlaku_mulai?->toDateString());
        $this->assertSame('2026-10-30', $schedule->berlaku_sampai?->toDateString());

        $this->post(route('pendaftaran.store-jadwal-praktik'), [...$scheduleData, 'berlaku_mulai' => '2026-10-23', 'berlaku_sampai' => '2026-11-06'])
            ->assertSessionHasErrors('hari');
        $this->assertDatabaseCount('jadwal_dokter', 1);

        JadwalDokter::create([...$scheduleData, 'berlaku_mulai' => '2026-11-01', 'berlaku_sampai' => '2026-11-10']);
        $this->put(route('pendaftaran.update-jadwal-praktik', $schedule), [
            ...$scheduleData,
            'berlaku_mulai' => '2026-10-23',
            'berlaku_sampai' => '2026-11-06',
        ])->assertSessionHasErrors('hari');
        $this->assertSame('2026-10-16', $schedule->fresh()->berlaku_mulai?->toDateString());
        $this->assertSame('2026-10-30', $schedule->fresh()->berlaku_sampai?->toDateString());
    }

    public function test_admin_can_open_and_manage_practice_schedules(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'ADMIN-JADWAL', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DR-ADMIN-JADWAL', 'nama' => 'Dokter Jadwal Admin', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true]);

        $this->actingAs($admin)->get(route('pendaftaran.jadwal-praktik'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pendaftaran/jadwal-praktik')
                ->where('canManage', true)
            );

        $this->post(route('pendaftaran.store-jadwal-praktik'), [
            'dokter_id' => $doctor->id,
            'poliklinik_id' => $clinic->id,
            'hari' => 'senin',
            'jam_mulai' => '08:00',
            'jam_selesai' => '12:00',
            'berlaku_mulai' => today()->addWeek()->toDateString(),
            'berlaku_sampai' => today()->addWeeks(3)->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('jadwal_dokter', [
            'dokter_id' => $doctor->id,
            'poliklinik_id' => $clinic->id,
            'hari' => 'senin',
        ]);

        $schedule = JadwalDokter::where('dokter_id', $doctor->id)->firstOrFail();
        $this->get(route('pendaftaran.jadwal-praktik'))->assertInertia(fn (Assert $page) => $page
            ->where('schedules.data.0.doctorId', $doctor->id)
            ->where('schedules.data.0.updateUrl', route('pendaftaran.update-jadwal-praktik', $schedule))
        );

        $this->put(route('pendaftaran.update-jadwal-praktik', $schedule), [
            'dokter_id' => $doctor->id,
            'poliklinik_id' => $clinic->id,
            'hari' => 'selasa',
            'jam_mulai' => '09:00',
            'jam_selesai' => '13:00',
            'berlaku_mulai' => today()->addWeek()->toDateString(),
            'berlaku_sampai' => today()->addWeeks(3)->toDateString(),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('jadwal_dokter', ['id' => $schedule->id, 'hari' => 'selasa', 'jam_mulai' => '09:00']);
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
                ->where('diagnoses.data.0.code', 'R50.9')
                ->where('diagnoses.data.0.percentage', 100)
            );
    }
}
