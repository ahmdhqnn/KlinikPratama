<?php

namespace App;

use App\Models\Kepesertaan;
use App\Models\Pasien;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PatientRegistrationService
{
    /** @return array<string, mixed> */
    public function validateManual(Request $request, bool $includeClinicalHistory = true): array
    {
        if ($request->boolean('grant_special_access')) {
            throw ValidationException::withMessages(['grant_special_access' => 'Verifikasi kepesertaan dilakukan oleh admin setelah pasien didaftarkan.']);
        }

        $rules = [
            'nama' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'digits:16'],
            'nip' => ['nullable', 'string', 'regex:/^[0-9]{8,30}$/'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],
            'tanggal_lahir' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'jenis_kelamin' => ['nullable', 'in:L,P'],
            'golongan_darah' => ['nullable', 'in:A,B,AB,O'],
            'agama' => ['nullable', 'string', 'max:50'],
            'unit_kerja' => ['nullable', 'string', 'max:200'],
            'alamat' => ['nullable', 'string', 'max:2000'],
            'rt' => ['nullable', 'string', 'max:5'],
            'rw' => ['nullable', 'string', 'max:5'],
            'kelurahan' => ['nullable', 'string', 'max:100'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'telepon' => ['nullable', 'string', 'max:20'],
            'riwayat_alergi' => $includeClinicalHistory ? ['nullable', 'string', 'max:2000'] : ['exclude'],
        ];

        $data = Validator::make($request->all(), $rules)->validate();
        if ($includeClinicalHistory) {
            $data['riwayat_alergi'] = filled($data['riwayat_alergi'] ?? null) ? trim($data['riwayat_alergi']) : null;
        }

        return $data;
    }

    public function updateIdentity(Request $request, Pasien $patient): void
    {
        $data = $this->validateManual($request, includeClinicalHistory: false);
        DB::transaction(function () use ($request, $patient, $data): void {
            $patient = Pasien::whereKey($patient->id)->lockForUpdate()->firstOrFail();
            $member = $patient->kepesertaan_id ? Kepesertaan::whereKey($patient->kepesertaan_id)->lockForUpdate()->firstOrFail() : null;
            foreach (['nik', 'nip'] as $field) {
                if ($member && array_key_exists($field, $data) && ($data[$field] ?? null) !== $member->getAttribute($field)) {
                    throw ValidationException::withMessages([$field => strtoupper($field).' pasien terverifikasi harus sesuai dengan kepesertaan.']);
                }
                if (filled($data[$field] ?? null) && Pasien::withTrashed()->where($field, $data[$field])->whereKeyNot($patient->id)->exists()) {
                    throw ValidationException::withMessages([$field => strtoupper($field).' sudah terhubung dengan pasien lain.']);
                }
            }
            if ($member) {
                $requiredFields = ['tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'alamat', 'unit_kerja'];
                Validator::make($data, array_fill_keys($requiredFields, ['sometimes', 'required']))->validate();
                foreach (['rt', 'rw', 'kelurahan', 'kecamatan'] as $field) {
                    if (blank($data[$field] ?? null) && $member->{$field}) {
                        $data[$field] = $member->{$field};
                    }
                }
                $identity = array_intersect_key($data, array_flip(['nama', 'tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'golongan_darah', 'agama', 'alamat', 'unit_kerja', 'rt', 'rw', 'kelurahan', 'kecamatan']));
                $member->update($identity);
            }
            $patient->fill($data);
            $changedFields = array_keys($patient->getDirty());
            $patient->save();
            app(ClinicalAuditRecorder::class)->record($request, 'patient.identity_updated', $patient->id,
                metadata: ['fields' => $changedFields, 'membership_id' => $patient->kepesertaan_id]);
        });
    }

    /** @param array<string, mixed> $data */
    public function createManual(array $data): Pasien
    {
        return DB::transaction(function () use ($data): Pasien {
            $medicalRecordNumber = app(ClinicDocumentNumber::class)->next('pasien', 'RM-', 6, 'pasien', 'no_rm');
            if (! empty($data['nik']) && Pasien::withTrashed()->where('nik', $data['nik'])->exists()) {
                throw ValidationException::withMessages(['nik' => 'NIK sudah terdaftar. Cari data pasien sebelum membuat rekam medis baru.']);
            }

            if (! empty($data['nip']) && Pasien::withTrashed()->where('nip', $data['nip'])->exists()) {
                throw ValidationException::withMessages(['nip' => 'NIP sudah terdaftar. Pilih pasien pada tab Pasien Terdaftar.']);
            }

            return Pasien::create([
                'no_rm' => $medicalRecordNumber,
                'nama' => $data['nama'], 'nik' => $data['nik'] ?? null, 'nip' => $data['nip'] ?? null, 'kepesertaan_id' => null,
                'tempat_lahir' => $data['tempat_lahir'] ?? null, 'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
                'jenis_kelamin' => $data['jenis_kelamin'] ?? null, 'golongan_darah' => $data['golongan_darah'] ?? null,
                'agama' => $data['agama'] ?? null, 'unit_kerja' => $data['unit_kerja'] ?? null, 'alamat' => $data['alamat'] ?? null, 'rt' => $data['rt'] ?? null,
                'rw' => $data['rw'] ?? null, 'kelurahan' => $data['kelurahan'] ?? null, 'kecamatan' => $data['kecamatan'] ?? null,
                'telepon' => $data['telepon'] ?? null,
                'riwayat_alergi' => $data['riwayat_alergi'] ?? null,
            ]);
        });
    }
}
