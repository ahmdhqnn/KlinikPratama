<?php

namespace App;

use App\Models\User;

final class PracticeScheduleAccess
{
    public function __construct(private PerawatClinicResolver $nurses) {}

    public function canManage(User $user): bool
    {
        if ($user->hasRole('admin', 'pendaftaran')) {
            return true;
        }

        return $user->isPerawat() && $this->nurses->nakes($user)->poliklinik->jenis === 'gigi';
    }

    public function authorizeClinic(User $user, int $clinicId): void
    {
        abort_unless($this->canManage($user), 403);

        if ($user->isPerawat()) {
            abort_unless($this->nurses->clinicId($user) === $clinicId, 404);
        }
    }
}
