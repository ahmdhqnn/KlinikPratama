<?php

namespace Tests\Feature;

use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DoctorExaminationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_only_sees_assigned_visits_and_does_not_deduct_stock_when_prescribing(): void
    {
        $user = User::create([
            'name' => 'Dokter Umum',
            'email' => 'dokter@klinik.test',
            'password' => 'password',
            'role' => 'dokter',
            'is_active' => true,
        ]);
        $doctor = Nakes::create([
            'kode' => 'DOK-001',
            'nama' => 'dr. Umum',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'user_id' => $user->id,
            'is_active' => true,
        ]);
        $otherDoctor = Nakes::create([
            'kode' => 'DOK-002',
            'nama' => 'dr. Gigi',
            'kategori' => 'medis',
            'jabatan' => 'dokter',
            'is_active' => true,
        ]);
        $poli = Poliklinik::create([
            'kode' => 'UMUM',
            'nama' => 'Poli Umum',
            'jenis' => 'umum',
            'is_active' => true,
        ]);
        $patient = Pasien::create(['no_rm' => 'RM-000001', 'nama' => 'Pasien Satu']);
        $otherPatient = Pasien::create(['no_rm' => 'RM-000002', 'nama' => 'Pasien Dua']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-001',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $poli->id,
            'dokter_id' => $doctor->id,
            'tanggal' => today(),
            'status' => 'pemeriksaan',
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
        ]);
        $otherVisit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-002',
            'pasien_id' => $otherPatient->id,
            'poliklinik_id' => $poli->id,
            'dokter_id' => $otherDoctor->id,
            'tanggal' => today(),
            'status' => 'pemeriksaan',
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
        ]);
        $obat = Obat::create([
            'kode' => 'OBT-001',
            'nama' => 'Paracetamol',
            'satuan_kecil' => 'tablet',
            'stok' => 10,
            'harga_jual' => 1000,
            'is_active' => true,
        ]);
        $examination = Pemeriksaan::create(['kunjungan_id' => $visit->id]);
        Diagnosa::create([
            'pemeriksaan_id' => $examination->id,
            'kode_icd10' => 'R50.9',
            'nama_diagnosa' => 'Demam tidak spesifik',
            'jenis' => 'utama',
        ]);

        $this->actingAs($user)->get(route('dokter.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->component('dokter/dashboard')
            ->where('auth.user.role', 'dokter')
            ->where('stats.visitsToday', 1)
            ->has('schedule')
            ->has('recentVisits', 1)
        );
        $visit->update(['status' => 'selesai']);
        $this->actingAs($user)->get(route('dokter.dashboard'))->assertInertia(fn (Assert $page) => $page
            ->where('stats.visitsToday', 1)
            ->where('stats.completedToday', 1)
            ->has('recentVisits', 1)
            ->where('recentVisits.0.status', 'selesai')
        );
        $visit->update(['status' => 'pemeriksaan']);
        $this->actingAs($user)->get(route('dokter.janji-kunjungan', [
            'dari' => today()->toDateString(),
            'sampai' => today()->addDay()->toDateString(),
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('dokter/appointments')
            ->where('filters.dari', today()->toDateString())
            ->where('filters.sampai', today()->addDay()->toDateString())
            ->has('visits.data', 1)
            ->where('visits.data.0.number', $visit->no_kunjungan)
        );
        $this->actingAs($user)->get(route('dokter.janji-kunjungan', [
            'dari' => 'invalid-date',
        ]))->assertSessionHasErrors('dari');
        $this->actingAs($user)->get(route('dokter.pasien'))->assertInertia(fn (Assert $page) => $page
            ->component('dokter/patients')
            ->has('patients.data', 1)
            ->where('patients.data.0.medicalRecordNumber', $patient->no_rm)
        );
        $this->actingAs($user)->get(route('dokter.stok-obat'))->assertInertia(fn (Assert $page) => $page
            ->component('dokter/stock')
            ->has('medicines.data', 1)
            ->where('medicines.data.0.code', $obat->kode)
        );
        $this->actingAs($user)->get(route('dokter.laporan-top-diagnosa', [
            'dari' => today()->toDateString(),
            'sampai' => today()->toDateString(),
        ]))->assertInertia(fn (Assert $page) => $page
            ->component('dokter/top-diagnoses')
            ->where('filters.dari', today()->toDateString())
            ->has('diagnoses.data', 1)
            ->where('diagnoses.data.0.code', 'R50.9')
            ->where('diagnoses.data.0.count', 1)
        );
        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.index'))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pemeriksaan/index')
            ->has('visits.data', 1)
            ->where('visits.data.0.number', $visit->no_kunjungan)
            ->where('visits.data.0.diagnosisCount', 1)
        );
        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/pemeriksaan/show')
            ->where('visit.patient.name', 'Pasien Satu')
            ->where('visit.diagnoses.0.code', 'R50.9')
            ->where('medicines.0.name', 'Paracetamol')
        );
        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', $otherVisit))
            ->assertForbidden();
        $this->actingAs($user)->get(route('pelayanan.pasien.rekam-medis', $otherPatient))
            ->assertForbidden();

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.store', $visit), [
            'anamnesis' => 'Demam sejak kemarin',
            'pemeriksaan_fisik' => 'Kondisi umum baik',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('pemeriksaan', ['kunjungan_id' => $visit->id, 'anamnesis' => 'Demam sejak kemarin']);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.diagnosa.store', $visit), [
            'kode_icd10' => 'R05',
            'nama_diagnosa' => 'Batuk',
            'jenis' => 'tambahan',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('diagnosa', ['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'R05']);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'obat_id' => $obat->id,
            'jumlah' => 0.5,
            'jenis' => 'jadi',
        ])->assertSessionHasErrors('jumlah');
        $this->assertDatabaseHas('obat', ['id' => $obat->id, 'stok' => 10]);

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'obat_id' => $obat->id,
            'jumlah' => 3,
            'aturan_pakai' => '3 x 1',
            'jenis' => 'jadi',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('obat', ['id' => $obat->id, 'stok' => 10]);
        $this->assertDatabaseHas('resep_obat', ['obat_id' => $obat->id, 'stok_dikurangi' => false]);

        $this->post(route('pelayanan.pemeriksaan.selesai', $visit))->assertRedirect();
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'farmasi']);

        $this->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'obat_id' => $obat->id,
            'jumlah' => 2,
            'jenis' => 'jadi',
        ])->assertUnprocessable();

        $this->assertDatabaseHas('obat', ['id' => $obat->id, 'stok' => 10]);
        $this->assertDatabaseCount('resep_obat', 1);
    }
}
