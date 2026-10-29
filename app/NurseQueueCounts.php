<?php

namespace App;

use App\Models\Kunjungan;

class NurseQueueCounts
{
    /** @return array{assessment: int, treatments: int} */
    public function forClinic(int $clinicId): array
    {
        $visits = Kunjungan::query()->where('poliklinik_id', $clinicId);

        return [
            'assessment' => (clone $visits)
                ->whereDate('tanggal', today())
                ->whereIn('status', ['menunggu', 'screening'])
                ->whereDoesntHave('screening', fn ($query) => $query->whereNotNull('submitted_at'))
                ->count(),
            'treatments' => (clone $visits)
                ->where('status', 'tindakan')
                ->whereHas('pemeriksaan', fn ($query) => $query->whereNotNull('signed_at'))
                ->whereHas('tindakanKunjungan', fn ($query) => $query
                    ->where('execution_role', 'perawat')
                    ->where('execution_status', 'pending'))
                ->count(),
        ];
    }
}
