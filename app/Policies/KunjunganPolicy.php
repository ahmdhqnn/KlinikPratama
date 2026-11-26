<?php

namespace App\Policies;

use App\DoctorAccessService;
use App\Models\Kunjungan;
use App\Models\User;
use App\PerawatClinicResolver;
use Illuminate\Auth\Access\Response;

class KunjunganPolicy
{
    public function accessService(User $user, string $service): bool
    {
        return $user->is_active && match ($service) {
            'screening', 'nursing' => $user->role === 'perawat',
            'examination', 'correspondence' => $user->role === 'dokter',
            'pharmacy' => $user->role === 'farmasi',
            'history', 'attachment' => in_array($user->role, ['dokter', 'perawat'], true),
            'patient_document' => in_array($user->role, ['admin', 'pendaftaran', 'dokter', 'perawat'], true),
            'consent_read' => in_array($user->role, ['admin', 'pendaftaran', 'dokter', 'perawat'], true),
            'consent_write' => in_array($user->role, ['admin', 'pendaftaran'], true),
            'procedure_consent_read' => in_array($user->role, ['dokter', 'perawat'], true),
            'procedure_consent_write' => $user->role === 'dokter',
            'information_release' => in_array($user->role, ['dokter', 'pendaftaran'], true),
            default => false,
        };
    }

    public function viewClinical(User $user, Kunjungan $visit): Response
    {
        if (! $this->accessService($user, 'history')) {
            return Response::deny();
        }

        $allowed = $user->role === 'dokter'
            ? app(DoctorAccessService::class)->visibleVisits($user, today()->toDateString())
                ->where(fn ($query) => $query->whereKey($visit->id)->orWhere('pasien_id', $visit->pasien_id))
                ->exists()
            : (int) $visit->poliklinik_id === app(PerawatClinicResolver::class)->clinicId($user);

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }
}
