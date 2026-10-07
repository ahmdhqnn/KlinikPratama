<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\Resep;
use App\Models\ResepObat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FarmasiInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispensing_pages_process_clinic_stock_and_allow_external_prescriptions(): void
    {
        $pharmacist = User::create([
            'name' => 'Petugas Farmasi',
            'email' => 'farmasi-test@klinik.test',
            'password' => bcrypt('password'),
            'role' => 'farmasi',
            'is_active' => true,
        ]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-000001', 'nama' => 'Pasien Farmasi', 'riwayat_alergi' => 'Alergi penisilin']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-20261007-0001',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today(),
            'status' => 'farmasi',
            'jenis_pasien' => 'lama',
            'jenis_bayar' => 'umum',
        ]);
        $medicine = Obat::create([
            'kode' => 'OBT-001',
            'nama' => 'Paracetamol',
            'satuan_kecil' => 'tablet',
            'stok' => 8,
            'harga_jual' => 1000,
            'is_active' => true,
        ]);
        $prescription = Resep::create([
            'no_resep' => 'RSP-20261007-0001',
            'kunjungan_id' => $visit->id,
            'status' => 'menunggu',
        ]);
        $clinicItem = ResepObat::create([
            'resep_id' => $prescription->id,
            'obat_id' => $medicine->id,
            'nama_obat' => $medicine->nama,
            'jumlah' => 3,
            'satuan' => 'tablet',
            'aturan_pakai' => '3 x 1',
            'jenis' => 'jadi',
            'is_resep_luar' => false,
            'stok_dikurangi' => false,
        ]);
        $externalItem = ResepObat::create([
            'resep_id' => $prescription->id,
            'nama_obat' => 'Vitamin luar',
            'jumlah' => 1,
            'satuan' => 'tablet',
            'aturan_pakai' => '1 x 1',
            'jenis' => 'jadi',
            'is_resep_luar' => true,
            'stok_dikurangi' => false,
        ]);

        $this->actingAs($pharmacist)->get(route('pelayanan.farmasi.index'))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/farmasi/index')
            ->has('visits.data', 1)
            ->where('visits.data.0.patient', 'Pasien Farmasi')
        );
        $this->get(route('pelayanan.farmasi.show', $visit))->assertInertia(fn (Assert $page) => $page
            ->component('pelayanan/farmasi/show')
            ->where('visit.patient.name', 'Pasien Farmasi')
            ->where('visit.prescriptionItems.1.external', true)
        );

        $this->post(route('pelayanan.farmasi.store', $visit), [
            'items' => [
                ['resep_obat_id' => $clinicItem->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 3, 'aturan_pakai' => '3 x 1'],
                ['resep_obat_id' => $externalItem->id, 'obat_id' => null, 'jumlah_diberikan' => 0, 'aturan_pakai' => '1 x 1'],
            ],
            'catatan' => 'Sudah diverifikasi',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('farmasi', ['kunjungan_id' => $visit->id, 'status' => 'diproses']);
        $this->assertDatabaseCount('farmasi_item', 1);

        $this->post(route('pelayanan.farmasi.selesai', $visit))->assertRedirect(route('pelayanan.farmasi.index'));
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 5]);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'kasir']);
        $this->assertDatabaseHas('farmasi', ['kunjungan_id' => $visit->id, 'status' => 'selesai']);
        $this->assertDatabaseHas('resep', ['id' => $prescription->id, 'status' => 'selesai']);

    }
}
