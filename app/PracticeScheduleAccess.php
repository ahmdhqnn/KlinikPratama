<?php

namespace App;

use App\Models\User;

final class PracticeScheduleAccess
{
    public function __construct(private PerawatClinicResolver $nurses) {}

    public function canManage(User $user): bool
    {
        return $user->isAdmin();
    }

    public function authorizeClinic(User $user, int $clinicId): void
    {
        abort_unless($this->canManage($user), 403);

    }
}
