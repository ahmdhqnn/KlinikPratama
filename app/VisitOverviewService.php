<?php

namespace App;

use App\Models\Kunjungan;
use Illuminate\Database\Eloquent\Builder;

final class VisitOverviewService
{
    /**
     * @param Builder<Kunjungan> $visits
     * @return array{upcoming: int, overdue: int}
     */
    public function summarize(Builder $visits): array
    {
        $active = (clone $visits)->whereNotIn('status', ['selesai', 'batal']);

        return [
            'upcoming' => (clone $active)->whereDate('tanggal', '>', today())->count(),
            'overdue' => (clone $active)->whereDate('tanggal', '<', today())->count(),
        ];
    }
}
