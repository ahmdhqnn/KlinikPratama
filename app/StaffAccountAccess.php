<?php

namespace App;

use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StaffAccountAccess
{
    public function save(string $role, bool $needsSlot, ?int $except, Closure $save, string $field = 'role'): void
    {
        if (! $needsSlot || ! in_array($role, User::SINGLE_ACTIVE_ROLES, true)) {
            DB::transaction($save, attempts: 3);

            return;
        }

        try {
            DB::transaction(function () use ($role, $except, $field, $save): void {
                $this->assertAvailable($role, $except, $field);
                $save();
            }, attempts: 3);
        } catch (UniqueConstraintViolationException $exception) {
            $this->assertAvailable($role, $except, $field);

            throw $exception;
        }
    }

    private function assertAvailable(string $role, ?int $except, string $field): void
    {
        if (in_array($role, User::occupiedSingleRoles($except), true)) {
            $label = $role === 'admin' ? 'Administrator' : 'Pendaftaran';

            throw ValidationException::withMessages([$field => "Sudah ada akun {$label} aktif. Gunakan akun yang tersedia."]);
        }
    }
}
