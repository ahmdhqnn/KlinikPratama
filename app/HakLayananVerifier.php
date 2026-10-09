<?php

namespace App;

use App\Models\Kepesertaan;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class HakLayananVerifier
{
    public function reason(?Kepesertaan $member, string $date): ?string
    {
        if (! $member || ! $member->verified_at || ! $member->verified_by) {
            return 'Kepesertaan belum diverifikasi. Hubungkan pasien dengan direktori kepesertaan.';
        }
        if (! $member->hak_layanan || ! in_array($member->status_kepegawaian, ['aktif', 'pensiun'], true)) {
            return 'Peserta tidak memiliki hak layanan aktif.';
        }
        if (($member->kategori === 'pensiunan') !== ($member->status_kepegawaian === 'pensiun')) {
            return 'Status kepegawaian tidak sesuai kategori peserta.';
        }
        $day = Carbon::parse($date)->startOfDay();
        if ($day->lt($member->berlaku_mulai) || ($member->berlaku_sampai && $day->gt($member->berlaku_sampai))) {
            return 'Hak layanan tidak berlaku pada tanggal kunjungan.';
        }
        if ($member->kategori === 'keluarga') {
            $sponsor = $member->penanggung;
            if (! $member->hubungan_keluarga || ! $sponsor || ! in_array($sponsor->kategori, ['pegawai_pusat', 'kontrak', 'pensiunan'], true)
                || $this->reason($sponsor, $date) !== null) {
                return 'Hak layanan pegawai penanggung keluarga tidak aktif atau relasi belum diverifikasi.';
            }
        }

        return null;
    }

    public function member(int $id, string $date, string $field = 'kepesertaan_id'): Kepesertaan
    {
        $member = Kepesertaan::whereKey($id)->lockForUpdate()->first();
        if ($member?->pegawai_penanggung_id) {
            $member->setRelation('penanggung', Kepesertaan::whereKey($member->pegawai_penanggung_id)->lockForUpdate()->first());
        }
        if ($reason = $this->reason($member, $date)) {
            throw ValidationException::withMessages([$field => $reason]);
        }

        return $member;
    }

    public function patient(Pasien $patient, string $date, Request $request): array
    {
        $member = $this->member((int) $patient->kepesertaan_id, $date, 'pasien_id');
        if ($member->nik && $member->nik !== $patient->nik) {
            throw ValidationException::withMessages(['pasien_id' => 'NIK pasien tidak sesuai direktori kepesertaan. Perbaiki tautan identitas terlebih dahulu.']);
        }

        return [
            'jenis_bayar' => 'internal',
            'asuransi_id' => null,
            'cost_center' => $member->cost_center,
            'unit_kerja' => $member->unit_kerja,
            'kategori_peserta' => $member->kategori,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'hak_layanan_snapshot' => [
                'kepesertaan_id' => $member->id, 'nik' => $member->nik, 'nip' => $member->nip,
                'kategori' => $member->kategori, 'status_kepegawaian' => $member->status_kepegawaian,
                'unit_kerja' => $member->unit_kerja, 'cost_center' => $member->cost_center,
                'pegawai_penanggung_id' => $member->pegawai_penanggung_id, 'hubungan_keluarga' => $member->hubungan_keluarga,
                'referensi_bukti' => $member->referensi_bukti, 'berlaku_mulai' => $member->berlaku_mulai->toDateString(),
                'berlaku_sampai' => $member->berlaku_sampai?->toDateString(),
                'sponsor' => $member->kategori === 'keluarga' ? $member->penanggung?->only(['id', 'nip', 'nik', 'status_kepegawaian', 'hak_layanan', 'berlaku_mulai', 'berlaku_sampai', 'referensi_bukti', 'verified_by', 'verified_at']) : null,
                'directory_verified_by' => $member->verified_by, 'directory_verified_at' => $member->verified_at->toIso8601String(),
                'patient_payable' => 0, 'funding_source' => 'Anggaran instansi',
            ],
        ];
    }
}
