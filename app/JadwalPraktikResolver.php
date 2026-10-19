<?php

namespace App;

use App\Models\JadwalDokter;
use App\Models\Nakes;
use App\Models\Poliklinik;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JadwalPraktikResolver
{
    public function applyAsnTemplate(int $doctorId, int $clinicId, string $validFrom): int
    {
        $added = 0;
        foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat'] as $day) {
            if (JadwalDokter::where('dokter_id', $doctorId)->where('poliklinik_id', $clinicId)->where('hari', $day)->exists()) {
                continue;
            }
            JadwalDokter::create([
                'dokter_id' => $doctorId, 'poliklinik_id' => $clinicId, 'hari' => $day,
                'berlaku_mulai' => $validFrom, 'jam_mulai' => '08:00', 'jam_selesai' => '16:00', 'is_active' => true,
            ]);
            $added++;
        }

        return $added;
    }

    /**
     * @return Collection<int, Nakes>
     */
    public function doctors(int $clinicId, Carbon $date): Collection
    {
        $day = $date->toDateString();
        $doctorIds = $this->isHoliday($day)
            ? collect()
            : JadwalDokter::query()
                ->where('poliklinik_id', $clinicId)
                ->where('hari', strtolower($date->locale('id')->dayName))
                ->where('is_active', true)
                ->where(fn ($query) => $query->whereNull('berlaku_mulai')->orWhereDate('berlaku_mulai', '<=', $day))
                ->where(fn ($query) => $query->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $day))
                ->pluck('dokter_id');

        $exceptions = DB::table('jadwal_khusus_dokter')->where('poliklinik_id', $clinicId)->whereDate('tanggal', $day)->get();
        foreach ($exceptions as $exception) {
            $doctorIds = $doctorIds->reject(fn ($id): bool => (int) $id === (int) $exception->dokter_id);
            if ($exception->is_active) {
                $doctorIds->push((int) $exception->dokter_id);
            }
        }

        return Nakes::query()->whereIn('id', $doctorIds->unique()->all())
            ->where('is_active', true)->where('jabatan', 'dokter')->orderBy('nama')->get(['id', 'nama']);
    }

    public function isHoliday(string $date): bool
    {
        return DB::table('hari_libur_praktik')->whereDate('tanggal', $date)->exists();
    }

    /**
     * @return Collection<int, array{id: int, clinic: string, start: string, end: string}>
     */
    public function doctorSchedule(int $doctorId, Carbon $date): Collection
    {
        $day = $date->toDateString();
        $entries = $this->isHoliday($day) ? collect() : JadwalDokter::query()
            ->with('poliklinik:id,nama')
            ->where('dokter_id', $doctorId)
            ->where('hari', strtolower($date->locale('id')->dayName))
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('berlaku_mulai')->orWhereDate('berlaku_mulai', '<=', $day))
            ->where(fn ($query) => $query->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $day))
            ->get()
            ->mapWithKeys(fn (JadwalDokter $schedule): array => [$schedule->poliklinik_id => [
                'id' => $schedule->poliklinik_id, 'clinic' => $schedule->poliklinik?->nama ?? 'Poliklinik',
                'start' => substr($schedule->jam_mulai, 0, 5), 'end' => substr($schedule->jam_selesai, 0, 5),
            ]]);

        $exceptions = DB::table('jadwal_khusus_dokter')->where('dokter_id', $doctorId)->whereDate('tanggal', $day)->get();
        $clinics = Poliklinik::whereIn('id', $exceptions->pluck('poliklinik_id'))->pluck('nama', 'id');
        foreach ($exceptions as $exception) {
            $entries->forget($exception->poliklinik_id);
            if ($exception->is_active) {
                $entries->put($exception->poliklinik_id, [
                    'id' => (int) $exception->poliklinik_id, 'clinic' => $clinics[$exception->poliklinik_id] ?? 'Poliklinik',
                    'start' => substr($exception->jam_mulai, 0, 5), 'end' => substr($exception->jam_selesai, 0, 5),
                ]);
            }
        }

        return $entries->values();
    }
}
