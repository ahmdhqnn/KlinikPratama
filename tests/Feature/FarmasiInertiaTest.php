<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\ObatBatch;
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
        ObatBatch::factory()->create(['obat_id' => $medicine->id, 'stok' => $medicine->stok]);
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
            'items' => [['resep_obat_id' => $clinicItem->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 1.5]],
        ])->assertSessionHasErrors('items.0.jumlah_diberikan');

        $this->post(route('pelayanan.farmasi.store', $visit), [
            'items' => [
                ['resep_obat_id' => $clinicItem->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 3, 'aturan_pakai' => '3 x 1'],
                ['resep_obat_id' => $externalItem->id, 'obat_id' => null, 'jumlah_diberikan' => 0, 'aturan_pakai' => '1 x 1'],
            ],
            'catatan' => 'Sudah diverifikasi',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('farmasi', ['kunjungan_id' => $visit->id, 'status' => 'diproses']);
        $this->assertDatabaseCount('farmasi_item', 1);

        $storedItem = $visit->farmasi->items->sole();
        $storedItem->update(['resep_obat_id' => $externalItem->id]);
        $this->post(route('pelayanan.farmasi.selesai', $visit))->assertSessionHasErrors('items');
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 8]);
        $this->assertDatabaseCount('stok_mutasi', 0);
        $storedItem->update(['resep_obat_id' => $clinicItem->id]);

        $this->post(route('pelayanan.farmasi.selesai', $visit))->assertRedirect(route('pelayanan.farmasi.index'));
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 5]);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'selesai']);
        $this->assertDatabaseHas('farmasi', ['kunjungan_id' => $visit->id, 'status' => 'selesai']);
        $this->assertDatabaseHas('resep', ['id' => $prescription->id, 'status' => 'selesai']);

        $this->post(route('pelayanan.farmasi.store', $visit), [
            'items' => [
                ['resep_obat_id' => $clinicItem->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 3],
            ],
        ])->assertUnprocessable();

        $this->post(route('pelayanan.farmasi.selesai', $visit))->assertRedirect(route('pelayanan.farmasi.index'));
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 5]);
        $this->assertDatabaseHas('farmasi', ['kunjungan_id' => $visit->id, 'status' => 'selesai']);
        $this->assertDatabaseCount('stok_mutasi', 1);

    }

    public function test_finalization_rolls_back_all_items_if_one_batch_expires_after_draft_is_saved(): void
    {
        $pharmacist = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'ATOMIC', 'nama' => 'Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-ATOMIC', 'nama' => 'Pasien Uji']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-ATOMIC', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'farmasi']);
        $prescription = Resep::create(['no_resep' => 'RSP-ATOMIC', 'kunjungan_id' => $visit->id, 'status' => 'menunggu']);
        $items = [];
        $medicines = [];
        foreach ([1, 2] as $index) {
            $medicine = Obat::create(['kode' => 'ATOMIC-'.$index, 'nama' => 'Obat '.$index, 'jenis' => 'obat', 'stok' => 5, 'is_active' => true]);
            ObatBatch::factory()->create(['obat_id' => $medicine->id, 'stok' => 5]);
            $item = $prescription->resepObat()->create(['obat_id' => $medicine->id, 'nama_obat' => $medicine->nama, 'jumlah' => 3, 'jenis' => 'jadi']);
            $items[] = ['resep_obat_id' => $item->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 3];
            $medicines[] = $medicine;
        }
        $this->actingAs($pharmacist)->post(route('pelayanan.farmasi.store', $visit), ['items' => $items])->assertSessionHasNoErrors();
        $medicines[1]->batches()->update(['expired_at' => today()]);
        $this->post(route('pelayanan.farmasi.selesai', $visit))->assertSessionHasErrors('items');
        foreach ($medicines as $medicine) {
            $this->assertEquals(5, $medicine->fresh()->stok);
            $this->assertEquals(5, $medicine->batches()->sum('stok'));
        }
        $this->assertDatabaseCount('stok_mutasi', 0);
        $this->assertDatabaseHas('farmasi', ['kunjungan_id' => $visit->id, 'status' => 'diproses', 'dispensed_at' => null]);
        $this->assertDatabaseHas('kunjungan', ['id' => $visit->id, 'status' => 'farmasi']);
    }

    public function test_partial_dispensing_only_deducts_the_actual_quantity_with_follow_up_notes(): void
    {
        $pharmacist = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'UMUM', 'nama' => 'Poli Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-PARTIAL-1', 'nama' => 'Pasien Parsial']);
        $visit = Kunjungan::create([
            'no_kunjungan' => 'KNJ-PARTIAL-1',
            'pasien_id' => $patient->id,
            'poliklinik_id' => $clinic->id,
            'tanggal' => today(),
            'status' => 'farmasi',
        ]);
        $medicine = Obat::create([
            'kode' => 'OBT-PARTIAL-1',
            'nama' => 'Obat Uji',
            'satuan_kecil' => 'tablet',
            'stok' => 10,
            'harga_jual' => 1000,
            'is_active' => true,
        ]);
        ObatBatch::factory()->create(['obat_id' => $medicine->id, 'stok' => $medicine->stok]);
        $prescription = Resep::create(['no_resep' => 'RSP-PARTIAL-1', 'kunjungan_id' => $visit->id, 'status' => 'menunggu']);
        $item = ResepObat::create([
            'resep_id' => $prescription->id,
            'obat_id' => $medicine->id,
            'nama_obat' => $medicine->nama,
            'jumlah' => 3,
            'satuan' => 'tablet',
            'jenis' => 'jadi',
            'stok_dikurangi' => false,
        ]);

        $this->actingAs($pharmacist)->post(route('pelayanan.farmasi.store', $visit), [
            'items' => [['resep_obat_id' => $item->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 4]],
        ])->assertSessionHasErrors('items.0.jumlah_diberikan');
        $this->assertDatabaseCount('farmasi_item', 0);

        $this->actingAs($pharmacist)->post(route('pelayanan.farmasi.store', $visit), [
            'items' => [['resep_obat_id' => $item->id, 'obat_id' => $medicine->id, 'jumlah_diberikan' => 1]],
            'catatan' => 'Dua tablet belum diambil; pasien akan kembali setelah konfirmasi dokter.',
        ])->assertSessionHasNoErrors();

        $this->post(route('pelayanan.farmasi.selesai', $visit))->assertRedirect(route('pelayanan.farmasi.index'));
        $this->assertDatabaseHas('obat', ['id' => $medicine->id, 'stok' => 9]);
        $this->assertDatabaseHas('stok_mutasi', ['obat_id' => $medicine->id, 'jenis' => 'keluar', 'jumlah' => 1]);
    }
}
