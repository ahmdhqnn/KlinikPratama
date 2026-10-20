<?php

namespace Tests\Feature;

use App\Exports\KunjunganExport;
use App\Models\JadwalDokter;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SharedDomainFilteringTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function historicalLists(): array
    {
        return [
            'visits' => ['pelayanan.kunjungan.index', 'admin'],
            'reports' => ['laporan.kunjungan', 'admin'],
            'registration' => ['pendaftaran.laporan-kunjungan', 'pendaftaran'],
            'clinics' => ['pendaftaran.kunjungan-per-poli', 'pendaftaran'],
            'pharmacy' => ['pelayanan.farmasi.index', 'farmasi'],
        ];
    }

    #[DataProvider('historicalLists')]
    public function test_lists_include_historical_data_without_a_date_filter(string $route, string $role): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        $user = User::factory()->create(['role' => $role, 'is_active' => true]);
        $this->visit('OLD', '2026-09-01', 'farmasi');
        $this->visit('TODAY', '2026-10-20', 'farmasi');

        $this->actingAs($user)->get(route($route))->assertInertia(fn (Assert $page) => $page->where('visits.total', 2));
    }

    public function test_visit_date_and_clinic_filters_use_the_frontend_contract(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $wanted = $this->visit('MATCH', '2026-10-19');
        $this->visit('OTHER', '2026-10-19');

        $this->actingAs($user)->get(route('pelayanan.kunjungan.index', [
            'tanggal' => '2026-10-19', 'poliklinik_id' => $wanted->poliklinik_id,
        ]))->assertInertia(fn (Assert $page) => $page->where('visits.total', 1)->where('visits.data.0.id', $wanted->id));
    }

    public function test_today_scope_ignores_list_filters(): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->visit('OLD', '2026-09-01');
        $today = $this->visit('TODAY', '2026-10-20');

        $this->actingAs($user)->get(route('pelayanan.kunjungan.index', [
            'scope' => 'today', 'tanggal' => '2026-09-01', 'search' => 'No match', 'status' => 'batal',
        ]))->assertInertia(fn (Assert $page) => $page->where('visits.total', 1)->where('visits.data.0.id', $today->id));
    }

    public function test_report_accepts_only_an_end_date_and_export_matches(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $old = $this->visit('OLD', '2026-09-01');
        $this->visit('NEW', '2026-10-20');

        $this->actingAs($user)->get(route('laporan.kunjungan', ['sampai' => '2026-09-30']))
            ->assertInertia(fn (Assert $page) => $page->where('visits.total', 1)->where('visits.data.0.id', $old->id)->where('stats.total', 1));

        $this->assertSame([$old->id], (new KunjunganExport(null, '2026-09-30'))->query()->pluck('id')->all());
    }

    public function test_report_rejects_an_inverted_period(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->actingAs($user)->get(route('laporan.kunjungan', ['dari' => '2026-10-20', 'sampai' => '2026-10-01']))
            ->assertSessionHasErrors(['sampai' => 'Tanggal akhir harus sesudah atau sama dengan tanggal mulai.']);
    }

    public function test_doctor_cannot_claim_an_unassigned_visit_on_a_holiday(): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        $user = User::factory()->create(['role' => 'dokter', 'is_active' => true]);
        $doctor = Nakes::create(['kode' => 'DR-HOL', 'nama' => 'Dokter', 'kategori' => 'medis', 'jabatan' => 'dokter', 'is_active' => true, 'user_id' => $user->id]);
        $visit = $this->visit('HOLIDAY', '2026-10-20', 'pemeriksaan');
        JadwalDokter::create(['dokter_id' => $doctor->id, 'poliklinik_id' => $visit->poliklinik_id, 'hari' => 'selasa', 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true]);
        DB::table('hari_libur_praktik')->insert(['tanggal' => '2026-10-20', 'nama' => 'Libur instansi', 'jenis' => 'instansi']);

        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.index'))->assertInertia(fn (Assert $page) => $page->where('visits.total', 0));
        $this->post(route('pelayanan.pemeriksaan.claim', $visit))->assertForbidden();
        $this->assertNull($visit->fresh()->dokter_id);
    }

    public function test_pharmacy_cannot_open_a_visit_before_the_pharmacy_stage(): void
    {
        $user = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $visit = $this->visit('PRIVATE', '2026-10-20');

        $this->actingAs($user)->get(route('pelayanan.farmasi.show', $visit))->assertNotFound();
        $this->assertDatabaseMissing('clinical_audit_events', ['action' => 'pharmacy.view', 'kunjungan_id' => $visit->id]);
    }

    public function test_screening_update_records_the_audit_and_advances_the_visit(): void
    {
        $this->travelTo('2026-10-20 10:00:00');
        $user = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $visit = $this->visit('UPDATE', '2026-10-20', 'screening');

        $this->actingAs($user)->put(route('pelayanan.screening.update', $visit), [
            'nyeri_dada' => 'tidak', 'kejang' => 'tidak', 'nadi_teraba' => 'teraba',
            'kesadaran' => 'sadar', 'pola_pernapasan' => 'normal',
            'kondisi_psikiatri' => 'normal', 'risiko_jatuh_visual' => 'rendah',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame('pemeriksaan', $visit->fresh()->status);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'screening.create_or_update', 'kunjungan_id' => $visit->id]);
    }

    public function test_top_diagnoses_include_history_and_accept_an_open_ended_period(): void
    {
        $user = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        foreach (['OLD' => '2026-09-01', 'CURRENT' => '2026-10-20'] as $code => $date) {
            $visit = $this->visit($code, $date, 'selesai');
            $examination = $visit->pemeriksaan()->create(['status' => 'selesai']);
            $examination->diagnosa()->create(['kode_icd10' => 'J00', 'nama_diagnosa' => 'Diagnosis uji', 'jenis' => 'utama']);
        }

        $this->actingAs($user)->get(route('pendaftaran.laporan-top-diagnosa'))->assertInertia(fn (Assert $page) => $page
            ->where('filters.tanggalMulai', '')->where('filters.tanggalSelesai', '')
            ->where('summary.diagnosisCount', 1)->where('summary.caseCount', 2)->where('diagnoses.data.0.count', 2));
        $this->get(route('pendaftaran.laporan-top-diagnosa', ['tanggal_selesai' => '2026-09-30']))->assertInertia(fn (Assert $page) => $page
            ->where('summary.caseCount', 1)->where('diagnoses.data.0.count', 1));
    }

    public function test_patient_directory_filters_gender_and_searches_nip(): void
    {
        $user = User::factory()->create(['role' => 'pendaftaran', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-FILTER', 'nama' => 'Pasien Uji', 'nip' => '001234567890123456', 'jenis_kelamin' => 'P']);
        Pasien::create(['no_rm' => 'RM-OTHER', 'nama' => 'Pasien Lain', 'jenis_kelamin' => 'L']);

        $this->actingAs($user)->get(route('pendaftaran.database-pasien', ['jenis_kelamin' => 'P', 'search' => '001234567890']))
            ->assertInertia(fn (Assert $page) => $page->where('patients.total', 1)->where('patients.data.0.id', $patient->id));
    }

    private function visit(string $code, string $date, string $status = 'menunggu'): Kunjungan
    {
        $clinic = Poliklinik::create(['kode' => $code, 'nama' => 'Poli '.$code, 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-'.$code, 'nama' => 'Pasien '.$code]);

        return Kunjungan::create(['no_kunjungan' => 'KNJ-'.$code, 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => $date, 'status' => $status, 'jenis_pasien' => 'lama']);
    }
}
