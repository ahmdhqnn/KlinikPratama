<?php

namespace Tests\Feature;

use App\Models\DepoObat;
use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\Pasien;
use App\Models\Poliklinik;
use App\Models\StokMutasi;
use App\Models\User;
use App\PersediaanRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryTraceabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_uses_fefo_actual_batch_cost_and_excludes_expiry_and_quarantine(): void
    {
        [$actor, $medicine, $depot, $visit] = $this->context();
        $inventory = app(PersediaanRecorder::class);
        $later = $inventory->receive($medicine->id, $this->receipt($depot, 'LATER', 5, 150, today()->addYear()->toDateString()), $actor);
        $early = $inventory->receive($medicine->id, $this->receipt($depot, 'EARLY', 2, 100, today()->addMonth()->toDateString()), $actor);
        $expired = $inventory->receive($medicine->id, $this->receipt($depot, 'EXPIRED', 3, 100, today()->addYear()->toDateString()), $actor);
        $expired->update(['expired_at' => today()]);
        $quarantine = $inventory->receive($medicine->id, $this->receipt($depot, 'QUARANTINE', 4, 100, today()->addYear()->toDateString()), $actor);
        $quarantine->update(['status' => 'karantina']);
        $inventory->issue($medicine->id, 4, $actor, $visit, 'Farmasi', 1);
        $this->assertSame('0.00', $early->fresh()->stok);
        $this->assertSame('3.00', $later->fresh()->stok);
        $this->assertSame('3.00', $expired->fresh()->stok);
        $this->assertSame('4.00', $quarantine->fresh()->stok);
        $this->assertEquals(10, $medicine->fresh()->stok);
        $this->assertDatabaseHas('stok_mutasi', ['batch_id' => $early->id, 'jenis' => 'keluar', 'jumlah' => 2, 'harga' => 100, 'actor_id' => $actor->id, 'cost_center' => 'CC-TEST']);
        try {
            $inventory->issue($medicine->id, 4, $actor, $visit, 'Farmasi', 1);
            $this->fail('Expired stock must not fulfil a prescription.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }
        $this->assertEquals(10, $medicine->fresh()->stok);
        $this->assertDatabaseCount('stok_mutasi', 6);
    }

    public function test_return_is_bounded_enters_quarantine_and_can_be_physically_reconciled(): void
    {
        [$actor, $medicine, $depot, $visit] = $this->context();
        $inventory = app(PersediaanRecorder::class);
        $batch = $inventory->receive($medicine->id, $this->receipt($depot, 'B-01', 5, 100, today()->addYear()->toDateString()), $actor);
        $inventory->issue($medicine->id, 3, $actor, $visit, 'Farmasi', 1);
        $out = StokMutasi::where('jenis', 'keluar')->firstOrFail();
        $visit->update(['cost_center' => 'CC-NEW']);
        $inventory->returnDispensing($out, 2, 'Retur diperiksa oleh petugas', $actor);
        $this->assertDatabaseHas('stok_mutasi', ['mutasi_asal_id' => $out->id, 'jenis' => 'retur', 'cost_center' => 'CC-TEST']);
        $this->assertEquals(4, $medicine->fresh()->stok);
        $returned = ObatBatch::where('sumber', 'retur')->firstOrFail();
        $this->assertSame('karantina', $returned->status);
        try {
            $inventory->returnDispensing($out, 2, 'Retur duplikat', $actor);
            $this->fail('Return exceeds remaining dispensed quantity.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('jumlah', $exception->errors());
        }
        $this->assertEquals(4, $medicine->fresh()->stok);
        $inventory->reconcile($returned, $this->receipt($depot, 'B-01', 1, 100, today()->addYear()->toDateString()), $actor);
        $this->assertSame('1.00', $returned->fresh()->stok);
        $this->assertEquals(3, $medicine->batches()->usable()->sum('stok'));
        $this->assertEquals(4, $medicine->fresh()->stok);
    }

    public function test_transfer_opname_damage_and_donation_keep_aggregate_batch_balance(): void
    {
        [$actor, $medicine, $depot] = $this->context();
        $other = DepoObat::create(['kode' => 'DEP-OTHER', 'nama' => 'Gudang', 'is_active' => true]);
        $inventory = app(PersediaanRecorder::class);
        $batch = $inventory->receive($medicine->id, [...$this->receipt($depot, 'HIBAH-01', 8, 0, today()->addYear()->toDateString()), 'sumber' => 'hibah'], $actor);
        $inventory->transfer($batch, ['depo_id' => $other->id, 'jumlah' => 3, 'alasan' => 'Pindah gudang fisik'], $actor);
        $this->assertEquals(8, $medicine->fresh()->stok);
        $inventory->adjust($batch, ['jenis' => 'opname', 'jumlah' => 4, 'alasan' => 'Selisih hitung fisik'], $actor);
        $inventory->adjust($batch, ['jenis' => 'rusak', 'jumlah' => 1, 'alasan' => 'Kemasan rusak diperiksa'], $actor);
        $this->assertEquals(6, $medicine->fresh()->stok);
        $this->assertEquals(6, $medicine->batches()->sum('stok'));
        $this->assertDatabaseCount('stok_mutasi', 5);
        $this->actingAs($actor)->post(route('stok.batch.adjust', $batch), ['jenis' => 'rusak', 'jumlah' => 99, 'alasan' => 'Jumlah tidak benar'])->assertSessionHasErrors('jumlah');
        $this->assertEquals(6, $medicine->fresh()->stok);
    }

    private function context(): array
    {
        $actor = User::factory()->create(['role' => 'farmasi', 'is_active' => true]);
        $medicine = Obat::create(['kode' => 'TEST-01', 'nama' => 'Obat Test', 'jenis' => 'obat', 'stok' => 0, 'is_active' => true, 'satuan_kecil' => 'Tablet']);
        $depot = DepoObat::create(['kode' => 'DEP-TEST', 'nama' => 'Apotek', 'is_active' => true]);
        $clinic = Poliklinik::create(['kode' => 'P-TEST', 'nama' => 'Umum', 'jenis' => 'umum', 'is_active' => true]);
        $patient = Pasien::create(['no_rm' => 'RM-TEST', 'nama' => 'Test']);
        $visit = Kunjungan::create(['no_kunjungan' => 'KNJ-TEST', 'pasien_id' => $patient->id, 'poliklinik_id' => $clinic->id, 'tanggal' => today(), 'status' => 'farmasi', 'cost_center' => 'CC-TEST']);

        return [$actor, $medicine, $depot, $visit];
    }

    private function receipt(DepoObat $depot, string $batch, int $quantity, int $cost, string $expiry): array
    {
        return ['depo_id' => $depot->id, 'nomor_batch' => $batch, 'jumlah' => $quantity, 'harga_beli' => $cost, 'expired_at' => $expiry, 'sumber' => 'pengadaan', 'referensi' => 'Sumber persediaan uji'];
    }
}
