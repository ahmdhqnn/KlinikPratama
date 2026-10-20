<?php

namespace App;

use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

final class DoctorAccessService
{
    public function __construct(private JadwalPraktikResolver $schedule) {}

    public function doctorId(User $user): int
    {
        abort_unless($user->role === 'dokter', 403);
        $doctorId = Nakes::query()->where('user_id', $user->id)->where('jabatan', 'dokter')->where('is_active', true)->value('id');
        abort_unless($doctorId, 403, 'Akun dokter belum terhubung dengan profil dokter aktif. Hubungi admin untuk memperbarui penugasan akun.');

        return (int) $doctorId;
    }

    /**
     * @return array<int, int>
     */
    public function scheduledClinicIds(int $doctorId, string $date): array
    {
        return $this->schedule->doctorSchedule($doctorId, Carbon::parse($date))->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /**
     * @return Builder<Kunjungan>
     */
    public function visibleVisits(User $user, ?string $claimDate = null): Builder
    {
        $doctorId = $this->doctorId($user);
        $clinicIds = $claimDate ? $this->scheduledClinicIds($doctorId, $claimDate) : [];

        return Kunjungan::query()->where(fn (Builder $query) => $query->where('dokter_id', $doctorId)
            ->when($claimDate, fn (Builder $query) => $query->orWhere(fn (Builder $unassigned) => $unassigned
                ->whereNull('dokter_id')->where('status', 'pemeriksaan')
                ->whereDate('tanggal', $claimDate)->whereIn('poliklinik_id', $clinicIds))));
    }
}
