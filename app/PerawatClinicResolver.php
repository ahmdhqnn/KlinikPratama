<?php

namespace App;

use App\Models\Nakes;
use App\Models\User;

class PerawatClinicResolver
{
    public function nakes(User $user): Nakes
    {
        $nakes = $this->assignedNakes($user);

        abort_unless($nakes !== null, 403, 'Akun perawat belum terhubung dengan poli aktif. Minta admin melengkapi penugasan poli di profil tenaga kesehatan.');

        return $nakes;
    }

    public function assignedNakes(User $user): ?Nakes
    {
        return $user->nakes()
            ->with('poliklinik')
            ->whereIn('jabatan', ['perawat', 'bidan', 'terapis_gigi'])
            ->where('is_active', true)
            ->whereHas('poliklinik', fn ($query) => $query->where('is_active', true)->whereIn('jenis', ['umum', 'gigi']))
            ->first();
    }

    public function clinicId(User $user): int
    {
        return (int) $this->nakes($user)->poliklinik_id;
    }
}
