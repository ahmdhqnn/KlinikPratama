<?php

namespace App;

use App\Models\Kunjungan;
use Illuminate\Http\Request;

final class AdminPharmacyAssistance
{
    /** @return array{assistance_reason?: string} */
    public function context(Request $request): array
    {
        abort_unless($request->user()->role === 'farmasi', 403);

        return [];
    }

    public function authorizeVisit(Request $request, Kunjungan $visit): void
    {
        abort_unless($request->user()->role === 'farmasi', 403);
        app(FarmasiClinicResolver::class)->authorizeVisit($request->user(), $visit);
        abort_unless($visit->poliklinik?->is_active && in_array($visit->poliklinik->jenis, ['umum', 'gigi'], true), 422);
        abort_unless($visit->pemeriksaan?->signed_at && app(ClinicalNoteRecorder::class)->verify($visit->pemeriksaan), 422, 'Catatan dokter final harus terverifikasi sebelum penyerahan obat.');
        abort_if($visit->tindakanKunjungan()->where('execution_role', 'perawat')->where('execution_status', 'pending')->exists(), 422, 'Selesaikan tindakan perawat sebelum pelayanan farmasi.');
    }
}
