<?php

namespace App;

use App\Models\Kunjungan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VisitCancellation
{
    public function cancel(Request $request, Kunjungan $visit): void
    {
        $reason = $request->input('reason');
        $request->merge(['reason' => is_string($reason) ? trim($reason) : $reason]);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        DB::transaction(function () use ($request, $visit, $data): void {
            $locked = Kunjungan::query()->lockForUpdate()->findOrFail($visit->id);
            if ($locked->status === 'batal') {
                return;
            }
            abort_unless($locked->status === 'menunggu', 422, 'Kunjungan yang sudah dilayani tidak dapat dibatalkan.');
            $locked->update(['status' => 'batal', 'cancelled_at' => now(), 'cancelled_by' => $request->user()->id, 'cancellation_reason' => trim($data['reason'])]);
            app(ClinicalAuditRecorder::class)->record($request, 'visit.cancelled', $locked->pasien_id, $locked->id, ['reason' => trim($data['reason']), 'old_status' => 'menunggu', 'new_status' => 'batal']);
        });
    }
}
