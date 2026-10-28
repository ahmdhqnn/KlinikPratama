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
            'jenis_lainnya' => ['nullable', 'required_if:kategori,lainnya', 'string', 'max:30'],
            'status_kepegawaian' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'unit_kerja' => ['required', 'string', 'max:200'],
            'tempat_lahir' => ['required', 'string', 'max:100'],
            'tanggal_lahir' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'jenis_kelamin' => ['required', Rule::in(['L', 'P'])],
            'agama' => ['required', 'string', 'max:50'],
            'golongan_darah' => ['nullable', Rule::in(['A', 'B', 'AB', 'O'])],
            'alamat' => ['required', 'string', 'max:2000'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'hak_layanan' => ['required', 'boolean'],
            'referensi_bukti' => ['required', 'string', 'max:255'],
        ], [
            'nik.string' => 'NIK harus disimpan sebagai teks di Excel agar seluruh digit tetap utuh.',
            'nip.string' => 'NIP harus disimpan sebagai teks di Excel agar seluruh digit tetap utuh.',
        ])->validate();

        foreach (['rt', 'rw', 'kelurahan', 'kecamatan'] as $field) {
            if (blank($data[$field] ?? null) && $member?->{$field}) {
                $data[$field] = $member->{$field};
            }
        }

        if ($data['kategori'] === 'asn' && empty($data['nip'])) {
            throw ValidationException::withMessages(['nip' => 'Peserta ASN wajib memiliki NIP.']);
        }
        if ($data['kategori'] === 'asn') {
            $data['jenis_lainnya'] = null;
        } else {
            $data['jenis_lainnya'] = trim($data['jenis_lainnya']);
            if (! Kepesertaan::isInternalOtherType($data['jenis_lainnya'])) {
                throw ValidationException::withMessages(['jenis_lainnya' => 'Isi jenis peserta internal yang jelas. Keluarga, pensiunan, dan tamu tidak termasuk kepesertaan klinik internal.']);
            }
            if (in_array(mb_strtolower($data['jenis_lainnya']), ['staff', 'staf'], true)) {
                $data['jenis_lainnya'] = 'staf';
            } elseif (mb_strtolower($data['jenis_lainnya']) === 'magang') {
                $data['jenis_lainnya'] = 'magang';
            }
        }
        if ($data['hak_layanan'] && $data['status_kepegawaian'] === 'nonaktif') {
            throw ValidationException::withMessages(['hak_layanan' => 'Peserta nonaktif tidak dapat diberikan hak layanan.']);
        }
        $data['pegawai_penanggung_id'] = null;
        $data['hubungan_keluarga'] = null;
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
