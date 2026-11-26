<?php

namespace App\Http\Middleware;

use App\ClinicalAuditRecorder;
use App\Models\Kunjungan;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class ProtectClinicalInformation
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $controller = class_basename($request->route()?->getControllerClass() ?? '');
            $method = $request->route()?->getActionMethod();
            $service = match ($controller) {
                'ScreeningController' => 'screening',
                'PemeriksaanController', 'DokterDashboardController', 'DokterTindakanController', 'DiagnosisCodingCorrectionController' => 'examination',
                'TindakanPerawatController', 'PerawatDashboardController' => 'nursing',
                'FarmasiController', 'FarmasiMandiriController', 'FarmasiDashboardController', 'PharmacyClinicalReviewController' => 'pharmacy',
                'VisitAttachmentController' => 'attachment',
                'PatientDocumentController' => 'patient_document',
                'GeneralConsentController' => $method === 'store' ? 'consent_write' : 'consent_read',
                'ProcedureConsentController' => $method === 'store' ? 'procedure_consent_write' : 'procedure_consent_read',
                'MedicalDocumentVerificationController' => $method === 'void' ? 'correspondence' : null,
                'MedicalInformationReleaseController' => 'information_release',
                'PasienController' => $method === 'rekamMedis' ? 'history' : null,
                'PersuratanController' => in_array($method, ['index', 'storeTemplate', 'updateTemplate', 'destroyTemplate'], true) ? null : 'correspondence',
                default => null,
            };
            // Pharmacists may print their prescription projection, never a letter or full RME.
            if ($controller === 'PersuratanController' && $method === 'printPrescription' && $request->user()->role === 'farmasi') {
                $service = 'pharmacy';
            }
            if ($service !== null) {
                try {
                    Gate::authorize('accessService', [Kunjungan::class, $service]);
                    $visit = $request->route('kunjungan');
                    if ($visit instanceof Kunjungan && in_array($service, ['attachment', 'history'], true)) {
                        Gate::authorize('viewClinical', $visit);
                    }
                } catch (AuthorizationException $exception) {
                    app(ClinicalAuditRecorder::class)->record($request, 'access.denied', metadata: ['outcome' => 'denied', 'service' => $service]);
                    throw $exception;
                }
            }
        }

        $response = $next($request);
        if ($request->user() || $request->is('login', 'profile*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('X-Content-Type-Options', 'nosniff');
            $response->headers->set('X-Frame-Options', 'DENY');
            $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
            $response->headers->set('Referrer-Policy', 'same-origin');
            if ($request->isSecure()) {
                $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            }
        }

        return $response;
    }
}
