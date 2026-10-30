<?php

namespace App;

use App\Models\Screening;
use Illuminate\Database\Eloquent\Builder;

class ScreeningReportQuery
{
    public function query(array $filters, ?int $staffId, ?int $clinicId): Builder
    {
        return Screening::query()->with(['kunjungan.pasien', 'kunjungan.poliklinik', 'kunjungan.pemeriksaan', 'petugas'])
            ->whereHas('kunjungan', fn ($visits) => $visits
                ->whereHas('poliklinik', fn ($clinics) => $clinics->whereIn('jenis', ['umum', 'gigi']))
                ->when($clinicId, fn ($query) => $query->where('poliklinik_id', $clinicId))
                ->when($filters['tanggal'] ?? null, fn ($query, $date) => $query->whereDate('tanggal', $date)))
            ->when($staffId, fn ($query) => $query->where('petugas_id', $staffId))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas('kunjungan.pasien', fn ($patients) => $patients
                ->where(fn ($patient) => $patient->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))
            ->latest('created_at')->latest('id');
    }
}
