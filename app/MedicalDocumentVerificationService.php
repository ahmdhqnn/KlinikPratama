<?php

namespace App;

use App\Models\MedicalDocumentVerification;
use App\Models\SuratMedis;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MedicalDocumentVerificationService
{
    public function digest(SuratMedis $letter): string
    {
        return hash('sha256', json_encode([
            $letter->id, $letter->kunjungan_id, $letter->dokter_id, $letter->surat_template_id,
            $letter->jenis, $letter->nomor_surat, $letter->tanggal?->toDateString(), $letter->konten,
            $this->canonicalize($letter->document_data ?? []),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function issue(SuratMedis $letter, User $actor): MedicalDocumentVerification
    {
        $existing = MedicalDocumentVerification::where('surat_medis_id', $letter->id)->first();
        if ($existing) {
            return $existing;
        }
        $token = bin2hex(random_bytes(32));

        return MedicalDocumentVerification::create([
            'surat_medis_id' => $letter->id, 'token' => $token, 'token_hash' => hash('sha256', $token),
            'content_hash' => $this->digest($letter), 'issued_by' => $actor->id, 'issued_at' => now(),
        ]);
    }

    public function status(MedicalDocumentVerification $verification): string
    {
        if (DB::table('medical_document_verification_events')->where('verification_id', $verification->id)->whereIn('action', ['void', 'replaced'])->exists()) {
            return 'void';
        }

        return $verification->letter && hash_equals($verification->content_hash, $this->digest($verification->letter)) ? 'valid' : 'invalid';
    }

    public function printData(SuratMedis $letter): ?array
    {
        $verification = MedicalDocumentVerification::where('surat_medis_id', $letter->id)->first();
        if (! $verification) {
            return null;
        }
        $url = route('medical-document.verify', ['token' => $verification->token]);
        return ['status' => $this->status($verification), 'url' => $url,
            'qrDataUrl' => app(DocumentVerificationQr::class)->dataUrl($url),
            'voidUrl' => route('persuratan.surat.void', $letter),
        ];
    }

    private function canonicalize(array $data): array
    {
        if (! array_is_list($data)) {
            ksort($data);
        }
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->canonicalize($value);
            }
        }

        return $data;
    }
}
