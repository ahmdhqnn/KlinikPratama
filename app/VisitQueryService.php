<?php

namespace App;

use App\Models\Kunjungan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class VisitQueryService
{
    /**
     * @return array{search?: string|null, tanggal?: string|null, status?: string|null, poliklinik_id?: int|string|null}
     */
    public function filters(Request $request): array
    {
        $scope = $request->validate(['scope' => ['nullable', 'in:all,today']]);
        if (($scope['scope'] ?? 'all') === 'today') {
            return ['tanggal' => today()->toDateString()];
        }

        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['nullable', 'in:menunggu,screening,pemeriksaan,farmasi,kasir,selesai,batal'],
            'poliklinik_id' => ['nullable', 'integer', 'exists:poliklinik,id'],
        ]);
    }

    /**
     * @param array{search?: string|null, tanggal?: string|null, status?: string|null, poliklinik_id?: int|string|null} $filters
     * @return Builder<Kunjungan>
     */
    public function filtered(array $filters, ?int $assignedClinicId = null, ?DateRangeFilter $period = null): Builder
    {
        $query = Kunjungan::query()
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->whereHas('pasien', fn (Builder $patients) => $patients
                ->where(fn (Builder $names) => $names->where('nama', 'like', "%{$search}%")->orWhere('no_rm', 'like', "%{$search}%"))))
            ->when($filters['tanggal'] ?? null, fn (Builder $query, string $date) => $query->whereDate('tanggal', $date))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($assignedClinicId ?? ($filters['poliklinik_id'] ?? null), fn (Builder $query, int|string $clinicId) => $query->where('poliklinik_id', $clinicId));

        return ($period ?? new DateRangeFilter)->apply($query);
    }
}
