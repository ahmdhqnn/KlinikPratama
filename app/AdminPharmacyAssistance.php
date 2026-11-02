<?php

namespace App;

use App\Models\Kunjungan;
use Illuminate\Http\Request;

final class AdminPharmacyAssistance
{
    /** @return array{assistance_reason?: string} */
    public function context(Request $request): array
    {
        if (! $request->user()->isAdmin()) {
            return [];
        }
        if (is_string($request->input('assistance_reason'))) {
            $request->merge(['assistance_reason' => trim($request->input('assistance_reason'))]);
        }

        return $request->validate(['assistance_reason' => ['required', 'string', 'min:10', 'max:1000']]);
    }

    public function authorizeVisit(Request $request, Kunjungan $visit): void
    {
        if (! $request->user()->isAdmin()) {
            return;
        }
        abort_unless($visit->poliklinik?->is_active && in_array($visit->poliklinik->jenis, ['umum', 'gigi'], true), 422);
        abort_unless($visit->pemeriksaan?->signed_at && app(ClinicalNoteRecorder::class)->verify($visit->pemeriksaan), 422, 'Catatan dokter final harus terverifikasi sebelum bantuan farmasi.');
        abort_if($visit->tindakanKunjungan()->where('execution_role', 'perawat')->where('execution_status', 'pending')->exists(), 422, 'Selesaikan tindakan perawat sebelum pelayanan farmasi.');
    }
}
