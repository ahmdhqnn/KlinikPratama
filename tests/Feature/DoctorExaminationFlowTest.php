<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DoctorExaminationFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_doctor_only_sees_assigned_visits_and_reserves_stock_once(): void
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

        $this->actingAs($user)->get(route('dokter.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('dokter.janji-kunjungan', [
            'dari' => today()->toDateString(),
            'sampai' => today()->addDay()->toDateString(),
        ]))->assertOk();
        $this->actingAs($user)->get(route('dokter.janji-kunjungan', [
            'dari' => 'invalid-date',
        ]))->assertSessionHasErrors('dari');
        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.index'))
            ->assertOk()
            ->assertSee($visit->no_kunjungan)
            ->assertDontSee($otherVisit->no_kunjungan);
        $this->actingAs($user)->get(route('pelayanan.pemeriksaan.show', $otherVisit))
            ->assertForbidden();
        $this->actingAs($user)->get(route('pelayanan.pasien.rekam-medis', $otherPatient))
            ->assertForbidden();

        $this->actingAs($user)->post(route('pelayanan.pemeriksaan.resep.store', $visit), [
            'obat_id' => $obat->id,
            'jumlah' => 3,
            'aturan_pakai' => '3 x 1',
            'jenis' => 'jadi',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('obat', ['id' => $obat->id, 'stok' => 7]);
        $this->assertDatabaseHas('resep_obat', ['obat_id' => $obat->id, 'stok_dikurangi' => true]);
    }
}
