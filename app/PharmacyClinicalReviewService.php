<?php

namespace App;

use App\Models\Kunjungan;
use App\Models\PharmacyClinicalReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PharmacyClinicalReviewService
{
    public function source(Kunjungan $visit): array
    {
        $visit->load(['pasien', 'resep.resepObat.farmasiResepLuar', 'farmasi.items', 'screening', 'pemeriksaan', 'dokter']);
        $maternal = app(ClinicalSafetyService::class)->maternalSummary($visit->screening, $visit->pasien);

        return [
            'visit_id' => $visit->id, 'note_hash' => $visit->pemeriksaan?->latest_note_hash,
            'doctor' => [$visit->dokter_id, $visit->dokter?->no_sip],
            'allergy' => app(PatientAllergyHistory::class)->forVisit($visit),
            'maternal' => [$maternal['pregnancyStatus'], $maternal['gestationalWeeks'], $maternal['lactationStatus']],
            'weight' => $visit->screening?->berat_badan,
            'weight_at' => $visit->screening?->submitted_at?->toIso8601String(),
            'prescription' => $visit->resep?->resepObat->sortBy('id')->map(fn ($item) => [
                $item->id, $item->obat_id, $item->nama_obat, (string) $item->jumlah, $item->satuan,
                $item->aturan_pakai, $item->catatan, $item->allergy_override_reason, $item->is_resep_luar, $item->farmasiResepLuar?->id,
            ])->values()->all() ?? [],
            'dispensing' => $visit->farmasi?->items->sortBy('id')->map(fn ($item) => [
                $item->resep_obat_id, $item->obat_id, (string) $item->jumlah_diberikan, $item->aturan_pakai, $item->catatan,
            ])->values()->all() ?? [],
        ];
    }

    public function hash(Kunjungan $visit): string
    {
        return hash('sha256', json_encode($this->source($visit), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public function latest(Kunjungan $visit): ?PharmacyClinicalReview
    {
        return PharmacyClinicalReview::where('kunjungan_id', $visit->id)->latest('id')->first();
    }

    public function recordHandover(Request $request, Kunjungan $visit): void
    {
        $staff = app(PharmacyStaffAccess::class)->current($request->user());
        if (! $staff) {
            throw ValidationException::withMessages(['pharmacy_review' => 'Kualifikasi petugas farmasi belum diverifikasi atau tidak berlaku. Hubungi pengelola profil tenaga kerja.']);
        }
        $review = $this->latest($visit);
        $reviewer = $review ? User::find($review->actor_id) : null;
        $credential = $reviewer ? app(PharmacyStaffAccess::class)->current($reviewer) : null;
        if (! $review || $review->decision !== 'approved' || ! hash_equals($review->source_hash, $this->hash($visit))
            || $credential?->qualification !== 'apoteker' || $credential->id !== $review->credential_id) {
            throw ValidationException::withMessages(['pharmacy_review' => 'Review apoteker belum tersedia, ditahan atau tidak sesuai data terbaru. Simpan rencana penyerahan dan lakukan review kembali.']);
        }
        $data = $request->validate([
            'recipient_name' => ['required', 'string', 'max:200'], 'recipient_relationship' => ['required', 'string', 'max:200'],
            'recipient_verified' => ['required', 'accepted'], 'counseling_completed' => ['required', 'accepted'],
            'counseling_notes' => ['required', 'string', 'min:10', 'max:2000'],
        ]);
        PharmacyClinicalReview::create(['kunjungan_id' => $visit->id, 'credential_id' => $staff->id, 'actor_id' => $request->user()->id,
            'source_hash' => $review->source_hash, 'decision' => 'delivered', 'recorded_at' => now(),
            'data' => ['review_id' => $review->id, 'recipient' => $data, 'qualification' => $staff->qualification]]);
    }
}
