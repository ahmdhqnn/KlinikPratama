<?php

namespace App;

use App\Models\Kunjungan;
use Illuminate\Database\Eloquent\Builder;

class DoctorExaminationReport
{
    public function query(?int $doctorId, DateRangeFilter $period, string $search = '', ?string $status = null, ?int $clinicId = null): Builder
    {
        return $period->apply(Kunjungan::query()
            ->when($doctorId !== null, fn (Builder $visits) => $visits->where('dokter_id', $doctorId))
            ->when($clinicId !== null, fn (Builder $visits) => $visits->where('poliklinik_id', $clinicId))
            ->whereHas('pemeriksaan', fn (Builder $exams) => $exams
                ->when($doctorId !== null, fn (Builder $query) => $query->where('dokter_id', $doctorId))
                ->when($status === 'final', fn (Builder $query) => $query->whereNotNull('signed_at'))
                ->when($status === 'draf', fn (Builder $query) => $query->whereNull('signed_at'))))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('no_kunjungan', 'like', "%{$search}%")
                ->orWhereHas('pasien', fn (Builder $patients) => $patients
                    ->where(fn (Builder $names) => $names->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%")))))
            ->with(['pasien', 'poliklinik', 'pemeriksaan.diagnosa'])->latest('tanggal')->latest('id');
    }
}
