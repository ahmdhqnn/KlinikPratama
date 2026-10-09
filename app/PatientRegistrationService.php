<?php

namespace App;

use App\Models\Pasien;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PatientRegistrationService
{
    /** @return array<string, mixed> */
    public function validateManual(Request $request, bool $allowSpecialAccess = false): array
    {
        if (! $allowSpecialAccess && $request->boolean('grant_special_access')) {
            throw ValidationException::withMessages(['grant_special_access' => 'Verifikasi hak layanan khusus hanya dapat dilakukan oleh admin.']);
        }

        $rules = [
            'nama' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:16'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'golongan_darah' => ['nullable', 'in:A,B,AB,O'],
            'agama' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string', 'max:2000'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'nama_ibu' => ['nullable', 'string', 'max:255'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'riwayat_alergi' => ['nullable', 'string', 'max:2000'],
            'grant_special_access' => ['sometimes', 'boolean'],
            'special_unit_kerja' => ['nullable', 'string', 'max:200'],
            'special_cost_center' => ['nullable', 'string', 'max:100'],
            'special_valid_from' => ['nullable', 'date_format:Y-m-d'],
            'special_valid_until' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:special_valid_from'],
            'special_reference' => ['nullable', 'string', 'max:255'],
        ];

        if ($allowSpecialAccess && $request->boolean('grant_special_access')) {
            $rules['special_unit_kerja'] = ['required', 'string', 'max:200'];
            $rules['special_cost_center'] = ['required', 'string', 'max:100'];
            $rules['special_valid_from'] = ['required', 'date_format:Y-m-d'];
            $rules['special_valid_until'] = ['required', 'date_format:Y-m-d', 'after_or_equal:special_valid_from'];
            $rules['special_reference'] = ['required', 'string', 'max:255'];
        }

        $data = Validator::make($request->all(), $rules)->validate();
        if ($allowSpecialAccess && $request->boolean('grant_special_access')) {
            Validator::make($data, [
                'nik' => ['required', 'digits:16'],
                'tempat_lahir' => ['required', 'string', 'max:100'],
                'tanggal_lahir' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
                'jenis_kelamin' => ['required', 'in:L,P'],
                'agama' => ['required', 'string', 'max:50'],
                'alamat' => ['required', 'string', 'max:2000'],
                'rt' => ['required', 'string', 'max:5'],
                'rw' => ['required', 'string', 'max:5'],
                'kelurahan' => ['required', 'string', 'max:100'],
                'kecamatan' => ['required', 'string', 'max:100'],
            ])->validate();
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    public function createManual(array $data, User $actor, bool $allowSpecialAccess = false): Pasien
    {
        return DB::transaction(function () use ($data, $actor, $allowSpecialAccess): Pasien {
            if (! empty($data['nik']) && Pasien::withTrashed()->where('nik', $data['nik'])->exists()) {
                throw ValidationException::withMessages(['nik' => 'NIK sudah terdaftar. Cari data pasien sebelum membuat rekam medis baru.']);
            }

            $membership = null;
            if (($data['grant_special_access'] ?? false) === true) {
                if (! $allowSpecialAccess || $actor->role !== 'admin') {
                    throw ValidationException::withMessages(['grant_special_access' => 'Verifikasi hak layanan khusus hanya dapat dilakukan oleh admin.']);
                }

                $membership = app(KepesertaanRegistry::class)->save([
                    'nama' => $data['nama'], 'nik' => $data['nik'], 'nip' => null,
                    'kategori' => 'khusus', 'status_kepegawaian' => 'aktif',
                    'unit_kerja' => $data['special_unit_kerja'], 'cost_center' => $data['special_cost_center'],
                    'tempat_lahir' => $data['tempat_lahir'], 'tanggal_lahir' => $data['tanggal_lahir'],
                    'jenis_kelamin' => $data['jenis_kelamin'], 'agama' => $data['agama'],
                    'golongan_darah' => $data['golongan_darah'] ?? null, 'alamat' => $data['alamat'],
                    'rt' => $data['rt'], 'rw' => $data['rw'], 'kelurahan' => $data['kelurahan'],
                    'kecamatan' => $data['kecamatan'], 'hak_layanan' => true,
                    'berlaku_mulai' => $data['special_valid_from'], 'berlaku_sampai' => $data['special_valid_until'],
                    'referensi_bukti' => $data['special_reference'],
                ], $actor);
            }

            return Pasien::create([
                'no_rm' => app(ClinicDocumentNumber::class)->next('pasien', 'RM-', 6, 'pasien', 'no_rm'),
                'nama' => $data['nama'], 'nik' => $data['nik'] ?? null, 'kepesertaan_id' => $membership?->id,
                'tempat_lahir' => $data['tempat_lahir'] ?? null, 'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
                'jenis_kelamin' => $data['jenis_kelamin'] ?? null, 'golongan_darah' => $data['golongan_darah'] ?? null,
                'agama' => $data['agama'] ?? null, 'alamat' => $data['alamat'] ?? null, 'rt' => $data['rt'] ?? null,
                'rw' => $data['rw'] ?? null, 'kelurahan' => $data['kelurahan'] ?? null, 'kecamatan' => $data['kecamatan'] ?? null,
                'nama_ibu' => $data['nama_ibu'] ?? null, 'telepon' => $data['telepon'] ?? null,
                'riwayat_alergi' => $data['riwayat_alergi'] ?? null, 'asuransi_id' => null, 'no_asuransi' => null,
            ]);
        });
    }
}
