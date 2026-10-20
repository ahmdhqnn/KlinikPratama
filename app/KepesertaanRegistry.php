<?php

namespace App;

use App\Models\Kepesertaan;
use App\Models\Pasien;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KepesertaanRegistry
{
    public function save(array $input, User $actor, ?Kepesertaan $member = null): Kepesertaan
    {
        $data = Validator::make($input, [
            'nama' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'required_without:nip', 'digits:16', Rule::unique('kepesertaan', 'nik')->ignore($member)],
            'nip' => ['nullable', 'required_without:nik', 'string', 'regex:/^[0-9]{8,30}$/', Rule::unique('kepesertaan', 'nip')->ignore($member)],
            'kategori' => ['required', Rule::in(array_keys(Kepesertaan::CATEGORIES))],
            'jenis_lainnya' => ['nullable', 'required_if:kategori,lainnya', Rule::in(array_keys(Kepesertaan::OTHER_TYPES))],
            'status_kepegawaian' => ['required', Rule::in(['aktif', 'pensiun', 'nonaktif'])],
            'unit_kerja' => ['required', 'string', 'max:200'],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'agama' => ['required', 'string', 'max:50'],
            'golongan_darah' => ['nullable', Rule::in(['A', 'B', 'AB', 'O'])],
            'alamat' => ['required', 'string', 'max:2000'],
            'rt' => ['required', 'string', 'max:5'],
            'rw' => ['required', 'string', 'max:5'],
            'kelurahan' => ['required', 'string', 'max:100'],
            'kecamatan' => ['required', 'string', 'max:100'],
            'pegawai_penanggung_id' => ['nullable', 'integer', 'exists:kepesertaan,id'],
            'hubungan_keluarga' => ['nullable', Rule::in(['suami', 'istri', 'anak'])],
            'hak_layanan' => ['required', 'boolean'],
            'referensi_bukti' => ['required', 'string', 'max:255'],
        ], [
            'nik.string' => 'NIK harus disimpan sebagai teks di Excel agar seluruh digit tetap utuh.',
            'nip.string' => 'NIP harus disimpan sebagai teks di Excel agar seluruh digit tetap utuh.',
        ])->validate();

        $isFamily = $data['kategori'] === 'lainnya' && $data['jenis_lainnya'] === 'keluarga';
        if ($data['kategori'] === 'asn' && empty($data['nip'])) {
            throw ValidationException::withMessages(['nip' => 'Peserta ASN wajib memiliki NIP.']);
        }
        if ($data['kategori'] === 'asn') {
            $data['jenis_lainnya'] = null;
        }
        if ($isFamily && (empty($data['pegawai_penanggung_id']) || empty($data['hubungan_keluarga']))) {
            throw ValidationException::withMessages(['pegawai_penanggung_id' => 'Pilih ASN terkait dan hubungan keluarga untuk kepesertaan keluarga.']);
        }
        if ($data['status_kepegawaian'] !== 'nonaktif'
            && ((($data['jenis_lainnya'] ?? null) === 'pensiunan') !== ($data['status_kepegawaian'] === 'pensiun'))) {
            throw ValidationException::withMessages(['status_kepegawaian' => 'Gunakan status pensiun untuk pensiunan dan aktif untuk kategori lain.']);
        }
        if ($data['hak_layanan'] && $data['status_kepegawaian'] === 'nonaktif') {
            throw ValidationException::withMessages(['hak_layanan' => 'Peserta nonaktif tidak dapat diberikan hak layanan.']);
        }
        if (! $isFamily) {
            $data['pegawai_penanggung_id'] = null;
            $data['hubungan_keluarga'] = null;
        } else {
            $sponsor = Kepesertaan::whereKey($data['pegawai_penanggung_id'])->lockForUpdate()->firstOrFail();
            if ($sponsor->id === $member?->id || $sponsor->kategori !== 'asn') {
                throw ValidationException::withMessages(['pegawai_penanggung_id' => 'Pilih ASN terkait keluarga yang tercatat pada direktori.']);
            }
        }
        if ($member && $data['kategori'] !== 'asn' && Kepesertaan::where('pegawai_penanggung_id', $member->id)->exists()) {
            throw ValidationException::withMessages(['kategori' => 'ASN yang terkait dengan anggota keluarga harus tetap berkategori ASN.']);
        }
        if ($member && $member->nik !== ($data['nik'] ?? null) && Pasien::withTrashed()->where('kepesertaan_id', $member->id)->exists()) {
            throw ValidationException::withMessages(['nik' => 'NIK peserta yang sudah terhubung dengan pasien tidak dapat diganti.']);
        }
        if ($member && $member->nip !== ($data['nip'] ?? null) && Pasien::withTrashed()->where('kepesertaan_id', $member->id)->whereNotNull('nip')->exists()) {
            throw ValidationException::withMessages(['nip' => 'NIP peserta yang sudah terhubung dengan pasien tidak dapat diganti.']);
        }
        $before = $member?->only(array_keys($data));
        $member ??= new Kepesertaan;
        $member->fill([...$data, 'verified_by' => $actor->id, 'verified_at' => now()]);
        $member->save();
        Pasien::where('kepesertaan_id', $member->id)->whereNull('nip')->update(['nip' => $member->nip]);
        DB::table('clinical_audit_events')->insert([
            'actor_id' => $actor->id, 'action' => $before ? 'membership.changed' : 'membership.created',
            'occurred_at' => now(), 'membership_id' => $member->id,
            'metadata' => json_encode(['before' => $before, 'after' => $member->only(array_keys($data))], JSON_THROW_ON_ERROR),
        ]);

        return $member;
    }
}
