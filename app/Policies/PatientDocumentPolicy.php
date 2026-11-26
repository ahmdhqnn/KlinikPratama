<?php

namespace App\Policies;

use App\DoctorAccessService;
use App\Models\Pasien;
use App\Models\PatientDocument;
use App\Models\User;
use App\PerawatClinicResolver;
use Illuminate\Auth\Access\Response;

class PatientDocumentPolicy
{
    public function viewAny(User $user, Pasien $pasien): Response
    {
        if (! $user->is_active || ! in_array($user->role, ['admin', 'pendaftaran', 'perawat', 'dokter'], true)) {
            return Response::deny();
        }

        return $this->hasPatientScope($user, $pasien) ? Response::allow() : Response::denyAsNotFound();
    }

    public function upload(User $user, Pasien $pasien, string $category): Response
    {
        if (! $user->is_active || ! in_array($user->role, ['admin', 'pendaftaran', 'perawat', 'dokter'], true)) {
            return Response::deny();
        }

        if (! $this->hasPatientScope($user, $pasien)) {
            return Response::denyAsNotFound();
        }

        if ($user->role === 'pendaftaran' && $category !== PatientDocument::CATEGORY_DOKUMEN_IDENTITAS) {
            return Response::deny('Petugas pendaftaran hanya dapat mengunggah dokumen identitas atau kepesertaan.');
        }

        if ($user->role === 'admin' && ! in_array($category, [
            PatientDocument::CATEGORY_DOKUMEN_IDENTITAS,
            PatientDocument::CATEGORY_RME_LAMA,
            PatientDocument::CATEGORY_SURAT_RUJUKAN,
        ], true)) {
            return Response::deny('Administrator hanya dapat mengunggah dokumen identitas, rekam medis lama, atau surat rujukan untuk keperluan pendaftaran.');
        }

        return Response::allow();
    }

    public function preview(User $user, PatientDocument $document): Response
    {
        return $this->canReadFile($user, $document);
    }

    public function download(User $user, PatientDocument $document): Response
    {
        return $this->canReadFile($user, $document);
    }

    public function archive(User $user, PatientDocument $document): Response
    {
        if (! $user->is_active || ! in_array($user->role, ['admin', 'perawat', 'dokter'], true)) {
            return Response::deny();
        }

        $patient = $document->pasien;
        if (! $patient || ! $this->hasPatientScope($user, $patient)) {
            return Response::denyAsNotFound();
        }

        if ($user->role === 'dokter') {
            return Response::allow();
        }

        if ($user->role === 'perawat') {
            return ($document->uploaded_by === $user->id || $document->sensitivity === PatientDocument::SENSITIVITY_ADMINISTRATIVE)
                ? Response::allow()
                : Response::deny('Perawat hanya dapat mengarsipkan dokumen yang diunggah sendiri atau dokumen administratif.');
        }

        // Admin may archive administrative documents or onboarding documents they uploaded themselves.
        return ($document->sensitivity === PatientDocument::SENSITIVITY_ADMINISTRATIVE || $document->uploaded_by === $user->id)
            ? Response::allow()
            : Response::deny('Dokumen klinis hanya dapat diarsipkan oleh dokter atau pengunggah berwenang.');
    }

    private function canReadFile(User $user, PatientDocument $document): Response
    {
        if (! $user->is_active || ! in_array($user->role, ['admin', 'pendaftaran', 'perawat', 'dokter'], true)) {
            return Response::deny();
        }

        $patient = $document->pasien;
        if (! $patient || ! $this->hasPatientScope($user, $patient)) {
            return Response::denyAsNotFound();
        }

        if (in_array($user->role, ['admin', 'pendaftaran'], true)
            && $document->sensitivity !== PatientDocument::SENSITIVITY_ADMINISTRATIVE) {
            return Response::deny('Berkas klinis pasien hanya dapat dilihat oleh dokter atau perawat yang menangani pasien.');
        }

        return Response::allow();
    }

    private function hasPatientScope(User $user, Pasien $pasien): bool
    {
        if (in_array($user->role, ['admin', 'pendaftaran'], true)) {
            return true;
        }

        if ($user->role === 'perawat') {
            $clinicId = app(PerawatClinicResolver::class)->clinicId($user);

            return $pasien->kunjungan()->where('poliklinik_id', $clinicId)->exists();
        }

        if ($user->role === 'dokter') {
            return app(DoctorAccessService::class)->visibleVisits($user, today()->toDateString())
                ->where('pasien_id', $pasien->id)
                ->exists();
        }

        return false;
    }
}
