<?php

namespace App;

use App\Models\ResepObat;
use App\Models\TindakanKunjungan;
use Illuminate\Database\Eloquent\Builder;

class DoctorActivityReportQuery
{
    public function treatments(int $doctorId, DateRangeFilter $period, string $search = ''): Builder
    {
        return TindakanKunjungan::query()
            ->with(['kunjungan.pasien', 'kunjungan.poliklinik', 'kunjungan.dokter', 'kunjungan.pemeriksaan.clinicalNoteVersions', 'tindakan', 'performedBy'])
            ->where('dokter_id', $doctorId)
            ->whereHas('kunjungan', fn (Builder $visit) => $period->apply($visit->where('dokter_id', $doctorId)))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('nama_tindakan', 'like', "%{$search}%")
                ->orWhere('nama_tindakan_manual', 'like', "%{$search}%")
                ->orWhereHas('tindakan', fn (Builder $treatments) => $treatments->where('nama', 'like', "%{$search}%"))
                ->orWhereHas('kunjungan', fn (Builder $visit) => $visit
                    ->where('no_kunjungan', 'like', "%{$search}%")
                    ->orWhereHas('pasien', fn (Builder $patients) => $patients->where(fn (Builder $identity) => $identity
                        ->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))))
            ->latest('tindakan_kunjungan.created_at')->latest('tindakan_kunjungan.id');
    }

    public function medicines(int $doctorId, DateRangeFilter $period, string $search = ''): Builder
    {
        return ResepObat::query()
            ->with(['resep.kunjungan.pasien', 'resep.kunjungan.poliklinik', 'resep.kunjungan.pemeriksaan.clinicalNoteVersions', 'resep', 'obat'])
            ->whereHas('resep', fn (Builder $prescription) => $prescription->where('dokter_id', $doctorId)
                ->whereHas('kunjungan', fn (Builder $visit) => $period->apply($visit->where('dokter_id', $doctorId))))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $matches) => $matches
                ->where('nama_obat', 'like', "%{$search}%")
                ->orWhereHas('obat', fn (Builder $medicines) => $medicines->where('nama', 'like', "%{$search}%"))
                ->orWhereHas('resep.kunjungan', fn (Builder $visit) => $visit
                    ->where('no_kunjungan', 'like', "%{$search}%")
                    ->orWhereHas('pasien', fn (Builder $patients) => $patients->where(fn (Builder $identity) => $identity
                        ->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))))
            ->latest('resep_obat.created_at')->latest('resep_obat.id');
    }
}
