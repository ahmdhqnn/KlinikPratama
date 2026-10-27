<?php

namespace App;

use App\Models\Kunjungan;
use Illuminate\Database\Eloquent\Builder;

class DoctorExaminationReport
{
    public function query(int $doctorId, DateRangeFilter $period, string $search = ''): Builder
    {
        return $period->apply(Kunjungan::query()->where('dokter_id', $doctorId)
            ->whereHas('pemeriksaan', fn ($query) => $query->where('dokter_id', $doctorId)))
            ->when($search !== '', fn ($query) => $query->whereHas('pasien', fn ($patients) => $patients
                ->where(fn ($names) => $names->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))
            ->with(['pasien', 'poliklinik', 'pemeriksaan.diagnosa'])->latest('tanggal')->latest('id');
    }
}
