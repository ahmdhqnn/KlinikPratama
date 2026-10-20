<?php

namespace App;

use App\Models\Kunjungan;
use App\Models\Poliklinik;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

final class VisitSchedulingService
{
    public function __construct(private JadwalPraktikResolver $schedule) {}

    public function resolveDoctor(?int $doctorId, int $clinicId, string $date, ?Kunjungan $existing = null): int
    {
        $clinic = Poliklinik::registrable()->findOrFail($clinicId);
        $visitDate = Carbon::parse($date)->startOfDay();
        $sameAppointment = $existing !== null && (int) $existing->poliklinik_id === $clinicId && $existing->tanggal->isSameDay($visitDate);

        if ($visitDate->lt(today())) {
            throw ValidationException::withMessages(['tanggal' => 'Tanggal kunjungan tidak boleh sudah terlewat.']);
        }

        if ($clinic->jenis === 'gigi' && ! $sameAppointment && ! $visitDate->gt(today())) {
            throw ValidationException::withMessages(['tanggal' => 'Poli Gigi memerlukan reservasi terlebih dahulu. Pilih tanggal setelah hari ini sesuai jadwal dokter.']);
        }

        $doctors = $this->schedule->doctors($clinicId, $visitDate)->pluck('id');
        if ($doctorId !== null && ! $doctors->contains($doctorId)) {
            throw ValidationException::withMessages(['dokter_id' => 'Dokter tidak memiliki jadwal aktif pada poli dan tanggal kunjungan yang dipilih.']);
        }

        if ($doctorId !== null) {
            return $doctorId;
        }

        if ($doctors->count() === 1) {
            return (int) $doctors->first();
        }

        if ($doctors->isEmpty()) {
            throw ValidationException::withMessages(['tanggal' => 'Tidak ada dokter praktik pada tanggal tersebut. Periksa jadwal dan kalender libur.']);
        }

        throw ValidationException::withMessages(['dokter_id' => 'Pilih salah satu dokter yang tersedia pada poliklinik dan tanggal tersebut.']);
    }

    /**
     * Call while holding the patient's row lock in the registration transaction.
     */
    public function ensureNoDuplicate(int $patientId, int $clinicId, string $date, ?int $exceptVisitId = null): void
    {
        $exists = Kunjungan::query()->where('pasien_id', $patientId)->where('poliklinik_id', $clinicId)
            ->whereDate('tanggal', $date)->whereNotIn('status', ['selesai', 'batal'])
            ->when($exceptVisitId, fn ($query) => $query->where('id', '!=', $exceptVisitId))->exists();

        if ($exists) {
            throw ValidationException::withMessages(['tanggal' => 'Pasien sudah memiliki kunjungan aktif pada poli dan tanggal tersebut.']);
        }
    }

    public function ensureServiceDate(Kunjungan $visit): void
    {
        abort_if($visit->tanggal->isFuture(), 422, 'Reservasi belum dapat dilayani sebelum tanggal kunjungan.');
    }
}
