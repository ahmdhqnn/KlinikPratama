<?php

namespace App;

use App\Models\TindakanKunjungan;
use Illuminate\Database\Eloquent\Builder;

class NursingProcedureReportQuery
{
    /**
     * @param  array{dari?: ?string, sampai?: ?string, search?: ?string, status?: ?string}  $filters
     */
    public function query(array $filters, int $clinicId): Builder
    {
        return TindakanKunjungan::query()
            ->with(['kunjungan.pasien', 'kunjungan.poliklinik', 'kunjungan.dokter', 'performedBy', 'tindakan'])
            ->where('execution_role', 'perawat')
            ->whereHas('kunjungan', fn (Builder $visit) => $visit
                ->where('poliklinik_id', $clinicId)
                ->when($filters['dari'] ?? null, fn (Builder $query, string $date) => $query->whereDate('tanggal', '>=', $date))
                ->when($filters['sampai'] ?? null, fn (Builder $query, string $date) => $query->whereDate('tanggal', '<=', $date)))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $matches) => $matches
                ->where('nama_tindakan', 'like', "%{$search}%")
                ->orWhere('nama_tindakan_manual', 'like', "%{$search}%")
                ->orWhereHas('tindakan', fn (Builder $treatments) => $treatments->where('nama', 'like', "%{$search}%"))
                ->orWhereHas('kunjungan', fn (Builder $visits) => $visits
                    ->where('no_kunjungan', 'like', "%{$search}%")
                    ->orWhereHas('pasien', fn (Builder $patients) => $patients->where(fn (Builder $identity) => $identity
                        ->where('nama', 'like', "%{$search}%")
                        ->orWhere('no_rm', 'like', "%{$search}%"))))))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('execution_status', $status))
            ->latest('tindakan_kunjungan.created_at')->latest('tindakan_kunjungan.id');
    }
}
