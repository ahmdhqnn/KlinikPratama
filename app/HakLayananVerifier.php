<?php

namespace App;

use App\Models\Kepesertaan;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HakLayananVerifier
{
    public function reason(?Kepesertaan $member): ?string
    {
        if (! $member || ! $member->verified_at || ! $member->verified_by) {
            return 'Kepesertaan belum diverifikasi. Hubungkan pasien dengan direktori kepesertaan.';
        }
        if (! $member->hak_layanan || ! in_array($member->status_kepegawaian, ['aktif', 'pensiun'], true)) {
            return 'Peserta tidak memiliki hak layanan aktif.';
        }
        if (($member->jenis_lainnya === 'pensiunan') !== ($member->status_kepegawaian === 'pensiun')) {
            return 'Status kepegawaian tidak sesuai kategori peserta.';
        }
        if ($member->kategori === 'lainnya' && $member->jenis_lainnya === 'keluarga') {
            $sponsor = $member->penanggung;
            if (! $member->hubungan_keluarga || ! $sponsor || $sponsor->kategori !== 'asn'
                || $this->reason($sponsor) !== null) {
                return 'Hak layanan ASN terkait keluarga belum aktif atau hubungan keluarga belum terverifikasi.';
            }
        }

        return null;
    }

    public function member(int $id, string $field = 'kepesertaan_id'): Kepesertaan
    {
        $member = Kepesertaan::whereKey($id)->lockForUpdate()->first();
        if ($member?->pegawai_penanggung_id) {
            $member->setRelation('penanggung', Kepesertaan::whereKey($member->pegawai_penanggung_id)->lockForUpdate()->first());
        }
        if ($reason = $this->reason($member)) {
            throw ValidationException::withMessages([$field => $reason]);
        }

        return $member;
    }

    public function patientReason(Pasien $patient): ?string
    {
        if (! $patient->kepesertaan_id) {
            return null;
        }

        if ($reason = $this->reason($patient->kepesertaan)) {
            return $reason;
        }

        return $this->identityReason($patient, $patient->kepesertaan);
    }

    private function identityReason(Pasien $patient, Kepesertaan $member): ?string
    {
        if ($member->nik && $member->nik !== $patient->nik) {
            return 'NIK pasien tidak sesuai direktori kepesertaan. Perbaiki tautan identitas terlebih dahulu.';
        }

        if ($member->nip && $patient->nip && $member->nip !== $patient->nip) {
            return 'NIP pasien tidak sesuai direktori kepesertaan. Perbaiki tautan identitas terlebih dahulu.';
        }

        return null;
    }

    public function patient(Pasien $patient, Request $request): array
    {
        if (! $patient->kepesertaan_id) {
            return [
                'unit_kerja' => null,
                'kategori_peserta' => null,
                'verified_by' => null,
                'verified_at' => null,
                'hak_layanan_snapshot' => [
                    'kepesertaan_id' => null,
                    'nik' => $patient->nik,
                    'nip' => $patient->nip,
                    'status' => 'pending_verification',
                ],
            ];
        }

        $member = $this->member((int) $patient->kepesertaan_id, 'pasien_id');
        if ($reason = $this->identityReason($patient, $member)) {
            throw ValidationException::withMessages(['pasien_id' => $reason]);
        }

        return [
            'unit_kerja' => $member->unit_kerja,
            'kategori_peserta' => $member->kategori,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'hak_layanan_snapshot' => [
                'kepesertaan_id' => $member->id, 'nik' => $member->nik, 'nip' => $member->nip,
                'kategori' => $member->kategori, 'jenis_lainnya' => $member->jenis_lainnya, 'status_kepegawaian' => $member->status_kepegawaian,
                'unit_kerja' => $member->unit_kerja,
                'pegawai_penanggung_id' => $member->pegawai_penanggung_id, 'hubungan_keluarga' => $member->hubungan_keluarga,
                'referensi_bukti' => $member->referensi_bukti,
                'sponsor' => $member->jenis_lainnya === 'keluarga' ? $member->penanggung?->only(['id', 'nip', 'nik', 'status_kepegawaian', 'hak_layanan', 'referensi_bukti', 'verified_by', 'verified_at']) : null,
                'directory_verified_by' => $member->verified_by, 'directory_verified_at' => $member->verified_at->toIso8601String(),
            ],
        ];
    }
}
