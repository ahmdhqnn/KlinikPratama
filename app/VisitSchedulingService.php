<?php

namespace App;

use App\Models\Kunjungan;
use App\Models\Pasien;
use App\Models\Poliklinik;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

final class VisitSchedulingService
{
    public function __construct(private JadwalPraktikResolver $schedule) {}

    public function resolveDoctor(?int $doctorId, int $clinicId, string $date): int
    {
        Poliklinik::registrable()->findOrFail($clinicId);
        $visitDate = Carbon::parse($date)->startOfDay();

        if ($visitDate->lt(today())) {
            throw ValidationException::withMessages(['tanggal' => 'Tanggal kunjungan tidak boleh sudah terlewat.']);
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

    public function resolveDoctorForUpdate(Kunjungan $visit, ?int $doctorId, int $clinicId, string $date): int
    {
        if ($doctorId === null && $visit->isPendingReservation()
            && $clinicId === $visit->poliklinik_id && $date === $visit->tanggal->toDateString()) {
            $doctorId = $visit->dokter_id;
        }

        return $this->resolveDoctor($doctorId, $clinicId, $date);
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

    /**
     * Call inside the examination transaction while holding the source visit lock.
     */
    public function syncFollowUp(Kunjungan $source, ?string $date, ?string $notes, ?string $previousDate): void
    {
        Pasien::whereKey($source->pasien_id)->lockForUpdate()->firstOrFail();
        $reservation = Kunjungan::where('kontrol_asal_id', $source->id)->lockForUpdate()->first();

        if ($reservation && ! in_array($reservation->status, ['menunggu', 'batal'], true)) {
            return;
        }

        if ($date === null) {
            if ($reservation?->status === 'menunggu') {
                $reservation->update(['status' => 'batal']);
            }

            return;
        }

        if ($date === $previousDate && $reservation) {
            if ($reservation->status === 'menunggu') {
                $reservation->update(['kontrol_catatan' => $notes]);
            }

            return;
        }

        if ($date <= today()->toDateString() || $date <= $source->tanggal->toDateString()) {
            if ($date !== $previousDate) {
                throw ValidationException::withMessages(['kontrol_berikutnya' => 'Pilih tanggal kontrol setelah hari ini dan tanggal kunjungan.']);
            }

            return;
        }

        try {
            $this->ensureNoDuplicate($source->pasien_id, $source->poliklinik_id, $date, $reservation?->id);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['kontrol_berikutnya' => 'Pasien sudah memiliki kunjungan aktif pada poli dan tanggal kontrol ini. Periksa reservasi yang sudah ada.']);
        }

        $reservation ??= new Kunjungan([
            'general_consent_required' => true,
            'no_kunjungan' => Kunjungan::generateNomor(),
            'kontrol_asal_id' => $source->id,
            'pasien_id' => $source->pasien_id,
            'jenis_pasien' => 'lama',
        ]);
        $reservation->fill([
            'poliklinik_id' => $source->poliklinik_id,
            'dokter_id' => $source->dokter_id,
            'tanggal' => $date,
            'status' => 'menunggu',
            'kontrol_pending' => true,
            'kontrol_catatan' => $notes,
            'verified_by' => null,
            'verified_at' => null,
            'hak_layanan_snapshot' => null,
            'kategori_peserta' => null,
            'unit_kerja' => null,
        ])->save();
    }

    public function ensureServiceDate(Kunjungan $visit): void
    {
        abort_if($visit->isPendingReservation(), 422, 'Reservasi kontrol masih menunggu konfirmasi pendaftaran.');
        abort_if($visit->tanggal->isFuture(), 422, 'Reservasi belum dapat dilayani sebelum tanggal kunjungan.');
    }
}
