<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\Screening;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SpecialtyScreeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_dental_screening_saves_specialty_assessment_and_displays_it_to_nurse(): void
    {
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'GIGI-SKR', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $this->assignNurse($nurse, $clinic);
        $patient = Pasien::create(['no_rm' => 'RM-GIGI-SKR', 'nama' => 'Pasien Gigi']);
        $visit = $this->makeVisit('KNJ-GIGI-SKR', $patient, $clinic);

        $this->actingAs($nurse)->get(route('pelayanan.screening.show', $visit))
            ->assertInertia(fn (Assert $page) => $page->component('pelayanan/screening/show')->where('visit.clinicType', 'gigi'));

        $this->post(route('pelayanan.kunjungan.screening.store', $visit), [
            ...$this->validScreeningPayload(),
            'keluhan' => 'Nyeri geraham saat mengunyah',
            'skala_nyeri' => 6,
            'lokasi_nyeri_gigi' => 'Geraham kiri bawah',
            'pemicu_nyeri_gigi' => ['dingin', 'mengunyah'],
            'durasi_keluhan_gigi' => 'Sejak kemarin',
            'risiko_medis_gigi' => ['diabetes', 'antikoagulan'],
            'riwayat_infeksi_gigi' => ['hepatitis'],
            'nyeri_dada' => 'tidak', 'kondisi_psikiatri' => 'normal', 'nadi_teraba' => 'teraba',
            'kejang' => 'tidak', 'pola_pernapasan' => 'normal', 'kesadaran' => 'sadar', 'risiko_jatuh_visual' => 'rendah',
        ])->assertRedirect();

        $screening = Screening::where('kunjungan_id', $visit->id)->firstOrFail();
        $this->assertSame(['dingin', 'mengunyah'], $screening->pemicu_nyeri_gigi);
        $this->assertSame(['diabetes', 'antikoagulan'], $screening->risiko_medis_gigi);
        $this->assertSame(['hepatitis'], $screening->riwayat_infeksi_gigi);
        $this->assertSame(6, $screening->skala_nyeri);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'pemeriksaan']);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'screening.view', 'kunjungan_id' => $visit->id]);
        $this->assertDatabaseHas('clinical_audit_events', ['action' => 'screening.create_or_update', 'kunjungan_id' => $visit->id]);
        $this->get(route('pelayanan.pasien.rekam-medis', $patient))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pasien/rekam-medis')
            ->where('visits.0.screening.dentalPainLocation', 'Geraham kiri bawah')
            ->where('visits.0.screening.dentalMedicalRisks.0', 'diabetes')
        );
    }

    public function test_nurse_returns_to_accessible_dashboard_from_screened_visit_detail(): void
    {
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM-BACK', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $this->assignNurse($nurse, $clinic);
        $patient = Pasien::create(['no_rm' => 'RM-UMUM-BACK', 'nama' => 'Pasien Umum']);
        $visit = $this->makeVisit('KNJ-UMUM-BACK', $patient, $clinic);

        $this->actingAs($nurse)->post(route('pelayanan.kunjungan.screening.store', $visit), [
            ...$this->validScreeningPayload(),
            'nyeri_dada' => 'tidak', 'kondisi_psikiatri' => 'normal', 'nadi_teraba' => 'teraba',
            'kejang' => 'tidak', 'pola_pernapasan' => 'normal', 'kesadaran' => 'sadar', 'risiko_jatuh_visual' => 'rendah',
        ])->assertRedirect(route('pelayanan.kunjungan.show', $visit));

        $this->get(route('pelayanan.kunjungan.show', $visit))
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/kunjungan/show')
                ->where('backUrl', route('perawat.dashboard'))
            );
        $this->get(route('perawat.dashboard'))->assertOk();
    }

    public function test_general_screening_keeps_general_history_and_ignores_dental_fields(): void
    {
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM-SKR', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $this->assignNurse($nurse, $clinic);
        $patient = Pasien::create(['no_rm' => 'RM-UMUM-SKR', 'nama' => 'Pasien Umum']);
        $visit = $this->makeVisit('KNJ-UMUM-SKR', $patient, $clinic);

        $this->actingAs($nurse)->post(route('pelayanan.kunjungan.screening.store', $visit), [
            ...$this->validScreeningPayload(),
            'riwayat_penyakit' => 'Asma', 'riwayat_penyakit_keluarga' => 'Hipertensi', 'risiko_jatuh' => 'sedang',
            'berat_badan' => 70, 'tinggi_badan' => 175,
            'lokasi_nyeri_gigi' => 'Payload tidak relevan', 'pemicu_nyeri_gigi' => ['dingin'],
            'nyeri_dada' => 'tidak', 'kondisi_psikiatri' => 'normal', 'nadi_teraba' => 'teraba',
            'kejang' => 'tidak', 'pola_pernapasan' => 'normal', 'kesadaran' => 'sadar', 'risiko_jatuh_visual' => 'rendah',
        ])->assertRedirect();

        $screening = Screening::where('kunjungan_id', $visit->id)->firstOrFail();
        $this->assertSame('Hipertensi', $screening->riwayat_penyakit_keluarga);
        $this->assertSame(22.86, $screening->imt);
        $this->assertNull($screening->lokasi_nyeri_gigi);
        $this->assertNull($screening->pemicu_nyeri_gigi);
    }

    public function test_unanswered_optional_dental_screening_fields_remain_empty(): void
    {
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'GIGI-DEF', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $this->assignNurse($nurse, $clinic);
        $patient = Pasien::create(['no_rm' => 'RM-GIGI-DEF', 'nama' => 'Pasien Gigi']);
        $visit = $this->makeVisit('KNJ-GIGI-DEF', $patient, $clinic);

        $this->actingAs($nurse)->get(route('pelayanan.screening.show', $visit))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visit.screening.keluhan', null)
                ->where('visit.screening.riwayat_penyakit', null)
                ->where('visit.screening.riwayat_alergi', null)
                ->where('visit.screening.riwayat_penyakit_keluarga', null)
                ->where('visit.screening.pemicu_nyeri_gigi', null)
                ->where('visit.screening.nyeri_dada', null)
            );

        $this->actingAs($nurse)->post(route('pelayanan.kunjungan.screening.store', $visit), [
            ...$this->validScreeningPayload(),
            'nyeri_dada' => 'tidak', 'kondisi_psikiatri' => 'normal', 'nadi_teraba' => 'teraba',
            'kejang' => 'tidak', 'pola_pernapasan' => 'normal', 'kesadaran' => 'sadar', 'risiko_jatuh_visual' => 'rendah',
        ])->assertRedirect();

        $screening = Screening::where('kunjungan_id', $visit->id)->firstOrFail();
        $this->assertSame('Keluhan pasien', $screening->keluhan);
        $this->assertNull($screening->riwayat_penyakit);
        $this->assertNull($screening->riwayat_alergi);
        $this->assertNull($screening->riwayat_penyakit_keluarga);
        $this->assertNull($screening->pemicu_nyeri_gigi);
        $this->assertSame(['tidak_ada'], $screening->risiko_medis_gigi);
        $this->assertNull($screening->riwayat_infeksi_gigi);
        $this->assertNull($screening->catatan_medis_gigi);

        $this->actingAs($nurse)->get(route('pelayanan.pasien.rekam-medis', $patient))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visits.0.screening.medicalHistory', 'Belum dicatat')
                ->where('visits.0.screening.allergyHistory', 'Belum dicatat')
                ->where('visits.0.screening.dentalPainTriggers', [])
            );
    }

    public function test_dental_screening_rejects_conflicting_none_infection_selection(): void
    {
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'GIGI-VAL', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $this->assignNurse($nurse, $clinic);
        $patient = Pasien::create(['no_rm' => 'RM-GIGI-VAL', 'nama' => 'Pasien Gigi']);
        $visit = $this->makeVisit('KNJ-GIGI-VAL', $patient, $clinic);

        $this->actingAs($nurse)->post(route('pelayanan.kunjungan.screening.store', $visit), [
            ...$this->validScreeningPayload(),
            'riwayat_infeksi_gigi' => ['tidak_ada', 'hepatitis'],
            'nyeri_dada' => 'tidak', 'kondisi_psikiatri' => 'normal', 'nadi_teraba' => 'teraba',
            'kejang' => 'tidak', 'pola_pernapasan' => 'normal', 'kesadaran' => 'sadar', 'risiko_jatuh_visual' => 'rendah',
        ])->assertSessionHasErrors('riwayat_infeksi_gigi');

        $this->assertDatabaseCount('screening', 0);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'menunggu']);
    }

    public function test_medical_record_filters_visits_and_paginates_the_history(): void
    {
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM-RME', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $otherClinic = Poliklinik::create(['kode' => 'GIGI-RME', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $this->assignNurse($nurse, $clinic);
        $patient = Pasien::create(['no_rm' => 'RM-RME-FILTER', 'nama' => 'Pasien Riwayat']);

        for ($index = 1; $index <= 12; $index++) {
            $visit = $this->makeVisit(sprintf('KNJ-RME-FILTER-%02d', $index), $patient, $clinic);
            $visit->update([
                'tanggal' => today()->subDays($index),
                'status' => $index === 11 ? 'selesai' : 'menunggu',
            ]);
        }
        $this->makeVisit('KNJ-RME-OTHER', $patient, $otherClinic);

        $this->actingAs($nurse)->get(route('pelayanan.pasien.rekam-medis', $patient))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pelayanan/pasien/rekam-medis')
                ->where('pagination.total', 12)
                ->where('pagination.currentPage', 1)
                ->has('visits', 10)
                ->where('filters.status', '')
                ->has('clinics', 1));

        $this->actingAs($nurse)->get(route('pelayanan.pasien.rekam-medis', [
            'pasien' => $patient->id,
            'status' => 'selesai',
            'poliklinik_id' => $clinic->id,
            'search' => 'FILTER-11',
            'dari' => today()->subDays(12)->toDateString(),
            'sampai' => today()->toDateString(),
        ]))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('pagination.total', 1)
            ->where('visits.0.number', 'KNJ-RME-FILTER-11')
            ->where('filters.status', 'selesai')
            ->where('filters.poliklinik_id', (string) $clinic->id));
    }

    public function test_nurse_scope_is_bound_to_assigned_clinic_and_history_is_bound_to_account(): void
    {
        $clinic = Poliklinik::create(['kode' => 'UMUM-SCOPE', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $otherClinic = Poliklinik::create(['kode' => 'GIGI-SCOPE', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $otherNurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->assignNurse($nurse, $clinic);
        $this->assignNurse($otherNurse, $clinic);

        $nursePatient = Pasien::create(['no_rm' => 'RM-SCOPE-NURSE', 'nama' => 'Pasien Perawat Pertama']);
        $otherNursePatient = Pasien::create(['no_rm' => 'RM-SCOPE-OTHER', 'nama' => 'Pasien Perawat Kedua']);
        $foreignPatient = Pasien::create(['no_rm' => 'RM-SCOPE-GIGI', 'nama' => 'Pasien Poli Gigi']);
        $screenedVisit = $this->makeVisit('KNJ-SCOPE-NURSE', $nursePatient, $clinic);
        $otherNurseVisit = $this->makeVisit('KNJ-SCOPE-OTHER', $otherNursePatient, $clinic);
        $foreignVisit = $this->makeVisit('KNJ-SCOPE-GIGI', $foreignPatient, $otherClinic);
        $pendingVisit = $this->makeVisit('KNJ-SCOPE-PENDING', Pasien::create(['no_rm' => 'RM-SCOPE-PENDING', 'nama' => 'Antrean Poli Umum']), $clinic);

        $this->actingAs($nurse)->post(route('pelayanan.kunjungan.screening.store', $screenedVisit), $this->validScreeningPayload())->assertRedirect();
        $this->actingAs($otherNurse)->post(route('pelayanan.kunjungan.screening.store', $otherNurseVisit), $this->validScreeningPayload())->assertRedirect();

        $this->actingAs($nurse)->get(route('pelayanan.screening.index', [
            'poliklinik_id' => $otherClinic->id,
            'tanggal' => today()->toDateString(),
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/screening/index')
            ->where('canSelectClinic', false)
            ->where('filters.poliklinikId', $clinic->id)
            ->where('visits.total', 1)
            ->where('visits.data.0.patient', 'Antrean Poli Umum'));

        $this->actingAs($nurse)->get(route('pelayanan.screening.index', [
            'search' => 'RM-SCOPE-PENDING',
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('filters.search', 'RM-SCOPE-PENDING')
            ->where('visits.total', 1)
            ->where('visits.data.0.patient', 'Antrean Poli Umum'));

        $this->actingAs($nurse)->get(route('pelayanan.screening.index', [
            'search' => $foreignPatient->nama,
        ]))->assertInertia(fn (Assert $page) => $page->where('visits.total', 0));

        $this->actingAs($nurse)->get(route('pelayanan.screening.history'))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/screening/history')
            ->where('screenings.total', 1)
            ->where('screenings.data.0.patient', 'Pasien Perawat Pertama'));

        $this->actingAs($nurse)->get(route('pelayanan.screening.show', $foreignVisit))->assertNotFound();
        $this->actingAs($nurse)->post(route('pelayanan.kunjungan.screening.store', $foreignVisit), $this->validScreeningPayload())->assertNotFound();

        $this->actingAs($admin)->get(route('pelayanan.screening.index', [
            'poliklinik_id' => $otherClinic->id,
            'tanggal' => today()->toDateString(),
        ]))->assertInertia(fn (Assert $page) => $page
            ->where('canSelectClinic', true)
            ->where('visits.total', 1)
            ->where('visits.data.0.patient', 'Pasien Poli Gigi'));
    }

    public function test_dental_draft_can_be_resumed_with_all_specialty_answers(): void
    {
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'GIGI-DRAFT', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $this->assignNurse($nurse, $clinic);
        $patient = Pasien::create(['no_rm' => 'RM-GIGI-DRAFT', 'nama' => 'Pasien Gigi']);
        $visit = $this->makeVisit('KNJ-GIGI-DRAFT', $patient, $clinic);

        $this->actingAs($nurse)->post(route('pelayanan.screening.store', $visit), [
            'tindakan' => 'draf', 'lokasi_nyeri_gigi' => 'Geraham kanan bawah', 'durasi_keluhan_gigi' => 'Saat mengunyah',
            'skala_nyeri' => 0, 'pemicu_nyeri_gigi' => ['dingin'], 'risiko_medis_gigi' => ['belum_diketahui'],
            'riwayat_infeksi_gigi' => ['tidak_ada'], 'catatan_medis_gigi' => 'Riwayat obat perlu dikonfirmasi',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'screening']);
        $this->get(route('pelayanan.screening.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->where('visit.screening.lokasi_nyeri_gigi', 'Geraham kanan bawah')
            ->where('visit.screening.durasi_keluhan_gigi', 'Saat mengunyah')
            ->where('visit.screening.skala_nyeri', 0)
            ->where('visit.screening.pemicu_nyeri_gigi', ['dingin'])
            ->where('visit.screening.risiko_medis_gigi', ['belum_diketahui'])
            ->where('visit.screening.riwayat_infeksi_gigi', ['tidak_ada'])
            ->where('visit.screening.catatan_medis_gigi', 'Riwayat obat perlu dikonfirmasi'));
    }

    private function makeVisit(string $number, Pasien $patient, Poliklinik $clinic): Kunjungan
    {
        return Kunjungan::create([
            'no_kunjungan' => $number,
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today(),
            'status' => 'menunggu',
            'jenis_pasien' => 'lama',
        ]);
    }

    private function assignNurse(User $user, Poliklinik $clinic): Nakes
    {
        return Nakes::create([
            'kode' => 'NURSE-'.$user->id,
            'nama' => $user->name,
            'kategori' => 'medis',
            'jabatan' => 'perawat',
            'poliklinik_id' => $clinic->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function validScreeningPayload(): array
    {
        return [
            'keluhan' => 'Keluhan pasien', 'lama_keluhan' => '2 hari', 'sumber_informasi' => 'pasien',
            'status_alergi' => 'belum_diketahui', 'status_riwayat_penyakit' => 'belum_diketahui', 'konfirmasi_riwayat' => '1',
            'triase_perawat' => 'hijau', 'risiko_medis_gigi' => ['tidak_ada'],
            'td_sistole' => '120', 'td_diastole' => '80', 'nadi' => '80', 'suhu' => '36.5', 'respirasi' => '18', 'berat_badan' => '60', 'tinggi_badan' => '160',
            'nyeri_dada' => 'tidak',
            'kondisi_psikiatri' => 'normal',
            'nadi_teraba' => 'teraba',
            'kejang' => 'tidak',
            'pola_pernapasan' => 'normal',
            'kesadaran' => 'sadar',
            'risiko_jatuh_visual' => 'rendah',
        ];
    }
}
