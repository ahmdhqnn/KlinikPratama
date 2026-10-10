<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
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
        $patient = Pasien::create(['no_rm' => 'RM-GIGI-SKR', 'nama' => 'Pasien Gigi']);
        $visit = $this->makeVisit('KNJ-GIGI-SKR', $patient, $clinic);

        $this->actingAs($nurse)->get(route('pelayanan.screening.show', $visit))
            ->assertInertia(fn (Assert $page) => $page->component('pelayanan/screening/show')->where('visit.clinicType', 'gigi'));

        $this->post(route('pelayanan.kunjungan.screening.store', $visit), [
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

    public function test_general_screening_keeps_general_history_and_ignores_dental_fields(): void
    {
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM-SKR', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-UMUM-SKR', 'nama' => 'Pasien Umum']);
        $visit = $this->makeVisit('KNJ-UMUM-SKR', $patient, $clinic);

        $this->actingAs($nurse)->post(route('pelayanan.kunjungan.screening.store', $visit), [
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

    public function test_dental_screening_rejects_conflicting_none_infection_selection(): void
    {
        $nurse = User::factory()->create(['role' => 'perawat', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'GIGI-VAL', 'nama' => 'Poli Gigi', 'jenis' => 'gigi', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-GIGI-VAL', 'nama' => 'Pasien Gigi']);
        $visit = $this->makeVisit('KNJ-GIGI-VAL', $patient, $clinic);

        $this->actingAs($nurse)->post(route('pelayanan.kunjungan.screening.store', $visit), [
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
                ->where('pagination.total', 13)
                ->where('pagination.currentPage', 1)
                ->has('visits', 10)
                ->where('filters.status', '')
                ->has('clinics', 2));

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

    private function makeVisit(string $number, Pasien $patient, Poliklinik $clinic): Kunjungan
    {
        return Kunjungan::create([
            'no_kunjungan' => $number,
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today(),
            'status' => 'menunggu',
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
        ]);
    }
}
