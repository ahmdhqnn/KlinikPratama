<?php

namespace App;

use App\Models\Kunjungan;
use Illuminate\Database\Eloquent\Builder;

class NurseQueueCounts
{
    /** @return array{assessment: int, treatments: int} */
    public function forClinic(int $clinicId): array
    {
        return $this->counts(Kunjungan::query()->where('poliklinik_id', $clinicId));
    }

    /** @return array{assessment: int, treatments: int} */
    public function forAllClinics(): array
    {
        return $this->counts(Kunjungan::query());
    }

    /** @return array{assessment: int, treatments: int} */
    private function counts(Builder $visits): array
    {
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
