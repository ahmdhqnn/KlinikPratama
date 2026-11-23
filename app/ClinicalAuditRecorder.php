<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClinicalAuditRecorder
{
    public function record(Request $request, string $action, ?int $patientId = null, ?int $kunjunganId = null, array $metadata = []): void
    {
        DB::table('clinical_audit_events')->insert([
            'actor_id' => $request->user()?->id,
            'actor_role' => $request->user()?->role,
            'user_agent' => mb_substr($request->userAgent() ?? '', 0, 512),
            'request_id' => $request->attributes->get('audit_request_id') ?? tap((string) Str::uuid(), fn ($id) => $request->attributes->set('audit_request_id', $id)),
            'outcome' => $metadata['outcome'] ?? 'success',
            'patient_id' => $patientId,
            'kunjungan_id' => $kunjunganId,
            'action' => $action,
            'route_name' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'occurred_at' => now(),
            'metadata' => $metadata === [] ? null : json_encode($metadata, JSON_THROW_ON_ERROR),
        ]);
    }
}
