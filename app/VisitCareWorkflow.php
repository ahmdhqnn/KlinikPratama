<?php

namespace App;

use App\Models\Farmasi;
use App\Models\Kunjungan;
use App\Models\TindakanKunjungan;
use App\Models\User;

class VisitCareWorkflow
{
    /** Caller must hold the visit lock inside a transaction. */
    public function advance(Kunjungan $visit): void
    {
        if ($visit->tindakanKunjungan()->where('execution_role', 'perawat')->where('execution_status', 'pending')->exists()) {
            $visit->update(['status' => 'tindakan']);

            return;
        }
        $prescription = $visit->resep()->first();
        if ($prescription && $prescription->resepObat()->where('is_resep_luar', false)->exists()) {
            Farmasi::firstOrCreate(['kunjungan_id' => $visit->id], ['resep_id' => $prescription->id, 'status' => 'menunggu']);
            $visit->update(['status' => 'farmasi']);
        } else {
            $prescription?->update(['status' => 'selesai']);
            $visit->update(['status' => 'selesai']);
        }
    }

    /** Caller must hold the visit and procedure locks inside a transaction. */
    public function consumeSupplies(TindakanKunjungan $procedure, Kunjungan $visit, User $actor): void
    {
        if ($procedure->bhp_consumed_at || ! $procedure->tindakan) {
            return;
        }
        foreach ($procedure->tindakan->bhp->sortBy('obat_id') as $supply) {
            abort_unless($supply->obat?->jenis === 'bhp', 422, 'Komposisi BHP tindakan harus memakai master BHP.');
            app(PersediaanRecorder::class)->issue($supply->obat_id, (float) $supply->jumlah * (float) $procedure->jumlah, $actor, $visit, 'TindakanBhp', $procedure->id);
        }
        $procedure->update(['bhp_consumed_at' => now()]);
    }
}
