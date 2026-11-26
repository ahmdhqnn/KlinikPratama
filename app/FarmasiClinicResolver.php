<?php

namespace App;

use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\ObatBatch;
use App\Models\Poliklinik;
use App\Models\User;

class FarmasiClinicResolver
{
    public function nakes(User $user): Nakes
    {
        $nakes = $this->assignedNakes($user);

        abort_unless(
            $nakes !== null,
            403,
            'Akun farmasi belum terhubung dengan poliklinik aktif. Minta admin melengkapi penugasan poliklinik pada profil tenaga kesehatan.'
        );

        return $nakes;
    }

    public function assignedNakes(User $user): ?Nakes
    {
        return $user->nakes()
            ->with('poliklinik.depoObat')
            ->whereIn('jabatan', ['farmasi', 'apotek'])
            ->where('is_active', true)
            ->whereHas('poliklinik', fn ($query) => $query->where('is_active', true)->whereIn('jenis', ['umum', 'gigi']))
            ->first();
    }

    /**
     * Resolve the pharmacist's assigned clinic ID, returning null only when the user is
     * not a poli-scoped pharmacy account (e.g. admin supervision or legacy unassigned test fixture
     * without a Nakes record).
     */
    public function optionalClinicId(User $user): ?int
    {
        if ($user->role !== 'farmasi') {
            return null;
        }

        $nakes = $this->assignedNakes($user);

        return $nakes?->poliklinik_id ? (int) $nakes->poliklinik_id : null;
    }

    public function clinicId(User $user): int
    {
        return (int) $this->nakes($user)->poliklinik_id;
    }

    public function clinic(User $user): ?Poliklinik
    {
        return $this->assignedNakes($user)?->poliklinik;
    }

    public function clinicType(User $user): ?string
    {
        return $this->clinic($user)?->jenis;
    }

    public function clinicName(User $user): ?string
    {
        return $this->clinic($user)?->nama;
    }

    public function depoId(User $user): ?int
    {
        $depoId = $this->clinic($user)?->depo_obat_id;

        return $depoId !== null ? (int) $depoId : null;
    }

    /**
     * Determine whether a pharmacy user is explicitly bound to an active clinic.
     * Note: If a pharmacy user HAS a Nakes row, it MUST have an active poliklinik_id;
     * otherwise access is denied (403).
     */
    public function scopedClinicIdOrFail(User $user): ?int
    {
        if ($user->role !== 'farmasi') {
            return null;
        }

        $assigned = $this->assignedNakes($user);
        if ($assigned !== null) {
            return (int) $assigned->poliklinik_id;
        }

        // If the pharmacist has a Nakes profile without an active poliklinik assignment, block access.
        $hasNakesProfile = $user->nakes()
            ->whereIn('jabatan', ['farmasi', 'apotek'])
            ->where('is_active', true)
            ->exists();

        if ($hasNakesProfile) {
            abort(403, 'Akun farmasi belum memiliki penugasan poliklinik aktif. Hubungi admin untuk melengkapi penugasan poliklinik.');
        }

        return null;
    }

    public function authorizeVisit(User $user, Kunjungan $visit): void
    {
        $clinicId = $this->scopedClinicIdOrFail($user);
        if ($clinicId !== null && (int) $visit->poliklinik_id !== $clinicId) {
            abort(404);
        }
    }

    public function authorizeBatch(User $user, ObatBatch $batch): void
    {
        if ($user->role !== 'farmasi') {
            return;
        }

        $this->scopedClinicIdOrFail($user);
        $depoId = $this->depoId($user);
        if ($depoId !== null && $batch->depo_id !== null && (int) $batch->depo_id !== $depoId) {
            abort(404);
        }
    }
}
