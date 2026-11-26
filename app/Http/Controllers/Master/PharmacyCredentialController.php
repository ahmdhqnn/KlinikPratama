<?php

namespace App\Http\Controllers\Master;

use App\ClinicalAuditRecorder;
use App\Http\Controllers\Controller;
use App\Models\Nakes;
use App\Models\PharmacyStaffCredential;
use App\Rules\ClinicalSigningCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PharmacyCredentialController extends Controller
{
    public function show(Request $request, Nakes $nakes)
    {
        abort_unless($request->user()->role === 'admin', 403);
        abort_unless(in_array($nakes->jabatan, ['farmasi', 'apotek'], true), 404);
        app(ClinicalAuditRecorder::class)->record($request, 'pharmacy.credential_view', metadata: ['nakes_id' => $nakes->id]);

        return Inertia::render('master/nakes/kualifikasi-farmasi', [
            'staff' => ['id' => $nakes->id, 'name' => $nakes->nama, 'sip' => $nakes->no_sip, 'str' => $nakes->no_str],
            'currentId' => PharmacyStaffCredential::where('nakes_id', $nakes->id)->max('id'),
            'history' => PharmacyStaffCredential::where('nakes_id', $nakes->id)->latest('id')->paginate(10)
                ->through(fn ($item) => ['id' => $item->id, 'qualification' => $item->qualification, 'at' => $item->recorded_at->format('d/m/Y H:i'), 'data' => $item->data]),
        ]);
    }

    public function store(Request $request, Nakes $nakes)
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate([
            'current_id' => ['nullable', 'integer'], 'qualification' => ['required', Rule::in(['apoteker', 'ttk', 'revoked'])],
            'reference' => ['required', 'string', 'min:10', 'max:2000'],
            'valid_until' => ['required_unless:qualification,revoked', 'nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'documents_verified' => ['required', 'accepted'], 'signature_password' => ['required', new ClinicalSigningCredential],
        ]);
        DB::transaction(function () use ($request, $nakes, $data): void {
            $staff = Nakes::whereKey($nakes->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($staff->jabatan, ['farmasi', 'apotek'], true), 404);
            if ((int) PharmacyStaffCredential::where('nakes_id', $staff->id)->max('id') !== (int) ($data['current_id'] ?? 0)) {
                throw ValidationException::withMessages(['current_id' => 'Kualifikasi telah diperbarui. Muat ulang halaman.']);
            }
            if ($data['qualification'] !== 'revoked' && (! $staff->is_active || ! $staff->user?->is_active
                || $staff->user->role !== 'farmasi' || blank($staff->no_sip) || blank($staff->no_str))) {
                throw ValidationException::withMessages(['qualification' => 'Profil aktif, akun farmasi aktif, SIP dan STR diperlukan sebelum verifikasi.']);
            }
            if (mb_strlen(trim($data['reference'])) < 10) {
                throw ValidationException::withMessages(['reference' => 'Catat referensi bukti dan penugasan yang diperiksa.']);
            }
            $record = PharmacyStaffCredential::create(['nakes_id' => $staff->id, 'actor_id' => $request->user()->id,
                'qualification' => $data['qualification'], 'recorded_at' => now(), 'data' => [
                    'user_id' => $staff->user_id, 'sip' => $staff->no_sip, 'str' => $staff->no_str,
                    'reference' => trim($data['reference']), 'valid_until' => $data['valid_until'] ?? null,
                ]]);
            app(ClinicalAuditRecorder::class)->record($request, 'pharmacy.credential_recorded', metadata: ['nakes_id' => $staff->id, 'credential_id' => $record->id]);
        });

        return back()->with('success', 'Verifikasi kualifikasi farmasi tersimpan.');
    }
}
