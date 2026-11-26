<?php

namespace App\Http\Middleware;

use App\ClinicalAuditRecorder;
use App\Models\Kunjungan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditAdminAssistanceFailures
{
    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->user()?->isAdmin() || ! in_array($request->method(), self::WRITE_METHODS, true)) {
            return $response;
        }

        $status = $response->getStatusCode();
        $hasValidationErrors = $request->hasSession() && $request->session()->has('errors');
        if (! $response->isClientError() && ! $response->isServerError() && ! $hasValidationErrors) {
            return $response;
        }

        $routeName = $request->route()?->getName();
        $visit = $request->route('kunjungan');
        $visitId = $visit instanceof Kunjungan ? $visit->getKey() : (is_numeric($visit) ? (int) $visit : null);
        $failureKind = $status >= 500
            ? 'server_error'
            : (in_array($status, [401, 403], true) ? 'access_denied' : 'validation_or_workflow');

        $patientId = $visit instanceof Kunjungan ? $visit->pasien_id : null;
        app(ClinicalAuditRecorder::class)->record(
            $request,
            'admin_assistance.attempt_rejected',
            $patientId,
            $visitId,
            [
                'failure_kind' => $failureKind,
                'status' => $status,
            ],
            'denied',
        );

        return $response;
    }
}
