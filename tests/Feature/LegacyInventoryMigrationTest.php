<?php

namespace Tests\Feature;

use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\Resep;
use App\Models\ResepObat;
use App\Models\StokMutasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyInventoryMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_restores_only_unfinished_reservations_and_quarantines_unknown_batches(): void
    {
        $this->freezeTime();
        $migration = require database_path('migrations/2026_10_08_152825_add_batch_inventory_traceability.php');
        $migration->down();

        $medicine = Obat::create(['kode' => 'LEGACY-TEST', 'nama' => 'Saldo lama', 'stok' => 6, 'harga_beli' => 125]);
        $patient = Pasien::create(['no_rm' => 'RM-LEGACY', 'nama' => 'Pasien historis']);
        $clinic = Poliklinik::create(['kode' => 'LEGACY', 'nama' => 'Poli Umum', 'jenis' => 'umum']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-LEGACY', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'farmasi']);
        $pending = Resep::create(['no_resep' => 'RSP-PENDING', 'kunjungan_id' => $visit->id, 'status' => 'menunggu']);
        $reserved = ResepObat::create(['resep_id' => $pending->id, 'obat_id' => $medicine->id, 'nama_obat' => $medicine->nama, 'jumlah' => 4, 'stok_dikurangi' => true]);
        $external = ResepObat::create(['resep_id' => $pending->id, 'nama_obat' => 'Resep luar', 'jumlah' => 3, 'is_resep_luar' => true, 'stok_dikurangi' => true]);
        $completed = Resep::create(['no_resep' => 'RSP-COMPLETED', 'kunjungan_id' => $visit->id, 'status' => 'selesai']);
        $dispensed = ResepObat::create(['resep_id' => $completed->id, 'obat_id' => $medicine->id, 'nama_obat' => $medicine->nama, 'jumlah' => 2, 'stok_dikurangi' => true]);
        $historical = StokMutasi::create(['obat_id' => $medicine->id, 'jenis' => 'keluar', 'referensi_type' => 'Farmasi', 'referensi_id' => 1, 'jumlah' => 2, 'harga' => 125, 'stok_sebelum' => 8, 'stok_sesudah' => 6, 'keterangan' => 'Catatan penyerahan historis']);

        $migration->up();

        $this->assertEquals(10, $medicine->fresh()->stok);
        $this->assertFalse($reserved->fresh()->stok_dikurangi);
        $this->assertFalse($external->fresh()->stok_dikurangi);
        $this->assertTrue($dispensed->fresh()->stok_dikurangi);
        $this->assertDatabaseHas('obat_batch', ['obat_id' => $medicine->id, 'nomor_batch' => 'LEGACY-'.$medicine->id, 'stok' => 10, 'status' => 'karantina', 'expired_at' => null]);
        $this->assertEquals(0, $medicine->batches()->usable()->sum('stok'));
        $this->assertDatabaseHas('stok_mutasi', ['referensi_type' => 'RekonsiliasiReservasi', 'referensi_id' => $reserved->id, 'jumlah' => 4, 'stok_sebelum' => 6, 'stok_sesudah' => 10]);
        $this->assertDatabaseHas('stok_mutasi', ['id' => $historical->id, 'jumlah' => 2, 'stok_sebelum' => 8, 'stok_sesudah' => 6, 'keterangan' => 'Catatan penyerahan historis']);
        $this->assertDatabaseCount('stok_mutasi', 2);
    }
}
