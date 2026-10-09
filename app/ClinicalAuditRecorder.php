<?php

namespace App;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClinicalAuditRecorder
{
    public function record(Request $request, string $action, ?int $patientId = null, ?int $kunjunganId = null, array $metadata = []): void
    {
        DB::table('clinical_audit_events')->insert([
            'actor_id' => $request->user()->id,
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
