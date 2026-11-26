<?php

namespace App;

use App\Models\Screening;
use Illuminate\Database\Eloquent\Builder;

class ScreeningReportQuery
{
    public function query(array $filters, ?int $staffId, ?int $clinicId, ?int $userId = null): Builder
    {
        return Screening::query()->with(['kunjungan.pasien', 'kunjungan.poliklinik', 'kunjungan.pemeriksaan', 'petugas', 'submittedBy'])
            ->whereHas('kunjungan', fn ($visits) => $visits
                ->whereHas('poliklinik', fn ($clinics) => $clinics->whereIn('jenis', ['umum', 'gigi']))
                ->when($clinicId, fn ($query) => $query->where('poliklinik_id', $clinicId))
                ->when($filters['tanggal'] ?? null, fn ($query, $date) => $query->getConnection()->getDriverName() === 'sqlite'
                    ? $query->whereDate('tanggal', $date)
                    : $query->where('tanggal', $date))
                ->when($filters['dari'] ?? null, fn ($query, $date) => $query->whereDate('tanggal', '>=', $date))
                ->when($filters['sampai'] ?? null, fn ($query, $date) => $query->whereDate('tanggal', '<=', $date)))
            ->when(($filters['status'] ?? null) === 'submitted', fn (Builder $query) => $query->whereNotNull('submitted_at'))
            ->when(($filters['status'] ?? null) === 'draft', fn (Builder $query) => $query->whereNull('submitted_at'))
            ->when($userId, fn (Builder $query) => $query->where(fn (Builder $owner) => $owner
                ->where('submitted_by_user_id', $userId)
                ->orWhere(fn (Builder $legacy) => $legacy->whereNull('submitted_by_user_id')->where('petugas_id', $staffId))))
            ->when($staffId && ! $userId, fn (Builder $query) => $query->where('petugas_id', $staffId))
            ->when($filters['prioritas'] ?? null, fn (Builder $query, string $priority) => $query->where('prioritas_layanan', $priority))
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('kunjungan', fn (Builder $visits) => $visits
                ->where('no_kunjungan', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn (Builder $patients) => $patients->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))
            ->latest('created_at')->latest('id');
    }
}
