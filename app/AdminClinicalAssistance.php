<?php

namespace App;

use App\Models\Kunjungan;
use App\Models\Pemeriksaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

final class AdminClinicalAssistance
{
    public function draftHash(Kunjungan $visit, Pemeriksaan $examination): string
    {
        return hash('sha256', json_encode(app(ClinicalNoteRecorder::class)->snapshot($visit, $examination), JSON_THROW_ON_ERROR));
    }

    public function latestWrite(Kunjungan $visit): ?stdClass
    {
        return DB::table('clinical_audit_events')
            ->leftJoin('users', 'users.id', '=', 'clinical_audit_events.actor_id')
            ->where('clinical_audit_events.kunjungan_id', $visit->id)
            ->where('clinical_audit_events.action', 'admin_assistance.draft_write')
            ->orderByDesc('clinical_audit_events.id')
            ->first(['clinical_audit_events.id', 'clinical_audit_events.actor_id', 'clinical_audit_events.occurred_at', 'users.name as operator']);
    }

    public function active(Request $request, Kunjungan $visit): bool
    {
        return $request->user()?->role === 'admin'
            && DB::table('admin_clinical_assistances')->where('actor_id', $request->user()->id)
                ->where('kunjungan_id', $visit->id)->where('dokter_id', $visit->dokter_id)
                ->where('poliklinik_id', $visit->poliklinik_id)->where('service_date', $visit->tanggal->toDateString())
                ->where('expires_at', '>', now())->exists();
    }

    public function authorizeDraft(Request $request, Kunjungan $visit): void
    {
        if ($request->user()->role !== 'admin') {
            return;
        }
        abort_unless($this->active($request, $visit), 403, 'Mulai bantuan pemeriksaan dengan alasan terlebih dahulu.');
        abort_unless($visit->dokter?->is_active && $visit->dokter->jabatan === 'dokter', 422, 'Dokter penanggung jawab harus aktif.');
        abort_if($request->filled('dokter_id') && (int) $request->input('dokter_id') !== $visit->dokter_id, 422, 'Bantuan admin tidak mengubah dokter penanggung jawab.');
        $key = 'admin_assistance_audited_'.$visit->id;
        if (! $request->attributes->get($key)) {
            $context = DB::table('admin_clinical_assistances')->where('actor_id', $request->user()->id)->where('kunjungan_id', $visit->id)->first();
            app(ClinicalAuditRecorder::class)->record($request, 'admin_assistance.draft_write', $visit->pasien_id, $visit->id, ['assistance_id' => $context->id, 'doctor_id' => $context->dokter_id]);
            $request->attributes->set($key, true);
        }
    }
}
