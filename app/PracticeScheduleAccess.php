<?php

namespace App;

use App\Models\User;

final class PracticeScheduleAccess
{
    public function __construct(private PerawatClinicResolver $nurses) {}

    public function canManage(User $user): bool
    {
        if ($user->hasRole('admin', 'pendaftaran', 'dokter')) {
            return true;
        }

        return $user->isPerawat() && $this->nurses->assignedNakes($user) !== null;
    }

    public function authorizeClinic(User $user, int $clinicId): void
    {
        abort_unless($this->canManage($user), 403);

        if ($user->isPerawat()) {
            abort_unless($this->nurses->clinicId($user) === $clinicId, 404);
        }
    }
}
