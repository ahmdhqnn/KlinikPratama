<?php

namespace App;

use App\Models\Kunjungan;
use App\Models\Obat;
use App\Models\ObatBatch;
use App\Models\StokMutasi;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PersediaanRecorder
{
    public function receive(int $medicineId, array $data, User $actor, string $referenceType = 'Penerimaan', ?int $referenceId = null): ObatBatch
    {
        return DB::transaction(function () use ($medicineId, $data, $actor, $referenceType, $referenceId): ObatBatch {
            $medicine = Obat::whereKey($medicineId)->lockForUpdate()->firstOrFail();
            $this->assertBalance($medicine);
            $batch = $medicine->batches()->create([
                'depo_id' => $data['depo_id'], 'nomor_batch' => $data['nomor_batch'],
                'expired_at' => $data['expired_at'], 'harga_beli' => $data['harga_beli'],
                'stok' => 0, 'status' => 'tersedia', 'sumber' => $data['sumber'], 'referensi' => $data['referensi'],
            ]);
            $this->change($medicine, $batch, (float) $data['jumlah'], 'masuk', $actor, $data['referensi'], $referenceType, $referenceId);

            return $batch->refresh();
        });
    }

    public function issue(int $medicineId, float $quantity, User $actor, Kunjungan $visit, string $referenceType, int $referenceId, ?int $pharmacyItemId = null): void
    {
        DB::transaction(function () use ($medicineId, $quantity, $actor, $visit, $referenceType, $referenceId, $pharmacyItemId): void {
            $medicine = Obat::whereKey($medicineId)->lockForUpdate()->firstOrFail();
            $this->assertBalance($medicine);
            if (! $medicine->is_active || $quantity <= 0) {
                throw ValidationException::withMessages(['items' => 'Obat/BHP tidak aktif atau jumlah tidak valid.']);
            }
            $batches = $medicine->batches()->usable()->orderBy('expired_at')->orderBy('id')->lockForUpdate()->get();
            if (round((float) $batches->sum('stok'), 2) < round($quantity, 2)) {
                throw ValidationException::withMessages(['items' => "Stok layak pakai {$medicine->nama} tidak mencukupi. Batch kedaluwarsa atau karantina tidak dapat digunakan."]);
            }
            foreach ($batches as $batch) {
                $taken = min($quantity, (float) $batch->stok);
                $this->change($medicine, $batch, -$taken, 'keluar', $actor, "Pemakaian {$visit->no_kunjungan}", $referenceType, $referenceId, $visit, $pharmacyItemId);
                $quantity = round($quantity - $taken, 2);
                if ($quantity <= 0) {
                    break;
                }
            }
        });
    }

    public function adjust(ObatBatch $batch, array $data, User $actor): void
    {
        DB::transaction(function () use ($batch, $data, $actor): void {
            $medicine = Obat::whereKey($batch->obat_id)->lockForUpdate()->firstOrFail();
            $this->assertBalance($medicine);
            $batch = ObatBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $quantity = (float) $data['jumlah'];
            $delta = $data['jenis'] === 'opname' ? round($quantity - (float) $batch->stok, 2) : -$quantity;
            if ((float) $batch->stok + $delta < 0 || ($data['jenis'] !== 'opname' && $quantity <= 0)) {
                throw ValidationException::withMessages(['jumlah' => 'Jumlah melebihi saldo batch atau tidak valid.']);
            }
            $this->change($medicine, $batch, $delta, $data['jenis'], $actor, $data['alasan'], 'Penyesuaian', $batch->id);
        });
    }

    public function reconcile(ObatBatch $batch, array $data, User $actor): void
    {
        DB::transaction(function () use ($batch, $data, $actor): void {
            $medicine = Obat::whereKey($batch->obat_id)->lockForUpdate()->firstOrFail();
            $this->assertBalance($medicine);
            $batch = ObatBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->status !== 'karantina' || (float) $data['jumlah'] > (float) $batch->stok) {
                throw ValidationException::withMessages(['jumlah' => 'Verifikasi hanya dapat menggunakan saldo karantina yang tersedia.']);
            }
            $verified = $medicine->batches()->create([
                'depo_id' => $data['depo_id'], 'nomor_batch' => $data['nomor_batch'], 'expired_at' => $data['expired_at'],
                'harga_beli' => $batch->harga_beli, 'stok' => 0, 'status' => 'tersedia',
                'sumber' => 'verifikasi', 'referensi' => $data['referensi'],
            ]);
            $this->change($medicine, $batch, -(float) $data['jumlah'], 'verifikasi_keluar', $actor, $data['referensi'], 'VerifikasiBatch', $batch->id);
            $this->change($medicine, $verified, (float) $data['jumlah'], 'verifikasi_masuk', $actor, $data['referensi'], 'VerifikasiBatch', $batch->id);
        });
    }

    public function transfer(ObatBatch $batch, array $data, User $actor): void
    {
        DB::transaction(function () use ($batch, $data, $actor): void {
            $medicine = Obat::whereKey($batch->obat_id)->lockForUpdate()->firstOrFail();
            $this->assertBalance($medicine);
            $batch = ObatBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ((int) $batch->depo_id === (int) $data['depo_id'] || (float) $data['jumlah'] > (float) $batch->stok) {
                throw ValidationException::withMessages(['jumlah' => 'Pilih depo tujuan berbeda dan jumlah sesuai saldo batch.']);
            }
            $target = $medicine->batches()->create([
                'depo_id' => $data['depo_id'], 'nomor_batch' => $batch->nomor_batch, 'expired_at' => $batch->expired_at,
                'harga_beli' => $batch->harga_beli, 'stok' => 0, 'status' => $batch->status,
                'sumber' => 'mutasi', 'referensi' => $data['alasan'],
            ]);
            $this->change($medicine, $batch, -(float) $data['jumlah'], 'mutasi_keluar', $actor, $data['alasan'], 'MutasiDepo', $batch->id);
            $this->change($medicine, $target, (float) $data['jumlah'], 'mutasi_masuk', $actor, $data['alasan'], 'MutasiDepo', $batch->id);
        });
    }

    public function returnDispensing(StokMutasi $original, float $quantity, string $reason, User $actor): void
    {
        DB::transaction(function () use ($original, $quantity, $reason, $actor): void {
            $medicine = Obat::whereKey($original->obat_id)->lockForUpdate()->firstOrFail();
            $this->assertBalance($medicine);
            $original = StokMutasi::with('batch')->whereKey($original->id)->lockForUpdate()->firstOrFail();
            $returned = (float) StokMutasi::where('mutasi_asal_id', $original->id)->where('jenis', 'retur')->sum('jumlah');
            if ($original->jenis !== 'keluar' || $original->referensi_type !== 'Farmasi' || ! $original->batch || $quantity <= 0 || $quantity > (float) $original->jumlah - $returned) {
                throw ValidationException::withMessages(['jumlah' => 'Retur harus mengacu pengeluaran resep dan tidak melebihi jumlah yang belum diretur.']);
            }
            $batch = $medicine->batches()->create([
                'depo_id' => $original->depo_id, 'nomor_batch' => $original->batch->nomor_batch,
                'expired_at' => $original->batch->expired_at, 'harga_beli' => $original->harga,
                'stok' => 0, 'status' => 'karantina', 'sumber' => 'retur', 'referensi' => $reason,
            ]);
            $visit = Kunjungan::findOrFail($original->kunjungan_id);
            $movement = $this->change($medicine, $batch, $quantity, 'retur', $actor, $reason, 'FarmasiRetur', $original->referensi_id, $visit, $original->farmasi_item_id);
            $movement->update(['mutasi_asal_id' => $original->id, 'cost_center' => $original->cost_center]);
        });
    }

    private function assertBalance(Obat $medicine): void
    {
        $total = (float) $medicine->batches()->sum('stok');
        if (round($total, 2) !== round((float) $medicine->stok, 2)) {
            throw ValidationException::withMessages(['items' => "Saldo total {$medicine->nama} tidak sesuai saldo batch. Lakukan rekonsiliasi persediaan terlebih dahulu."]);
        }
    }

    private function change(Obat $medicine, ObatBatch $batch, float $delta, string $type, User $actor, string $reason, string $referenceType, ?int $referenceId, ?Kunjungan $visit = null, ?int $pharmacyItemId = null): StokMutasi
    {
        $before = (float) $medicine->stok;
        $batchBefore = (float) $batch->stok;
        if (round($before + $delta, 2) < 0 || round($before + $delta, 2) > 99999999.99 || round($batchBefore + $delta, 2) < 0) {
            throw ValidationException::withMessages(['jumlah' => 'Saldo persediaan berada di luar batas yang dapat dicatat.']);
        }
        $medicine->update(['stok' => round($before + $delta, 2)]);
        $batch->update(['stok' => round($batchBefore + $delta, 2)]);

        return StokMutasi::create([
            'obat_id' => $medicine->id, 'batch_id' => $batch->id, 'depo_id' => $batch->depo_id,
            'jenis' => $type, 'jumlah' => abs($delta), 'harga' => $batch->harga_beli,
            'stok_sebelum' => $before, 'stok_sesudah' => $medicine->stok,
            'batch_stok_sebelum' => $batchBefore, 'batch_stok_sesudah' => $batch->stok,
            'actor_id' => $actor->id, 'kunjungan_id' => $visit?->id, 'cost_center' => $visit?->cost_center,
            'farmasi_item_id' => $pharmacyItemId, 'referensi_type' => $referenceType, 'referensi_id' => $referenceId,
            'keterangan' => $reason,
        ]);
    }
}
