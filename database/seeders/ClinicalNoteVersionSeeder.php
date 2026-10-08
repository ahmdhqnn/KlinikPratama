<?php

namespace Database\Seeders;

use App\ClinicalNoteRecorder;
use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClinicalNoteVersionSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Seeder addendum sintetis hanya untuk lingkungan local/testing.');
        }

        DB::transaction(function (): void {
            $doctorUser = User::firstOrCreate(
                ['email' => 'dokter-uat@klinik.test'],
                ['name' => 'Dokter UAT', 'password' => 'password', 'role' => 'dokter', 'is_active' => true]
            );
            $doctor = Nakes::where('kode', 'DKT001')->firstOrFail();
            $doctor->update(['user_id' => $doctorUser->id]);
            $clinic = Poliklinik::where('jenis', 'umum')->where('is_active', true)->firstOrFail();
            $patient = Pasien::firstOrCreate(
                ['no_rm' => 'RM-UAT-ADDENDUM'],
                ['nama' => 'Pasien UAT Addendum', 'jenis_kelamin' => 'P']
            );
            $visit = Kunjungan::firstOrCreate(
                ['no_kunjungan' => 'KNJ-UAT-ADDENDUM'],
                [
                    'pasien_id' => $patient->id,
                    'poliklinik_id' => $clinic->id,
                    'dokter_id' => $doctor->id,
                    'tanggal' => today(),
                    'status' => 'pemeriksaan',
                    'jenis_pasien' => 'baru',
                    'jenis_bayar' => 'umum',
                ]
            );
            $examination = Pemeriksaan::firstOrCreate(
                ['kunjungan_id' => $visit->id],
                [
                    'dokter_id' => $doctor->id,
                    'anamnesis' => 'Keluhan nyeri kepala sejak dua hari.',
                    'pemeriksaan_fisik' => 'Keadaan umum baik, tanda vital stabil.',
                    'status' => 'draft',
                ]
            );
            Diagnosa::firstOrCreate(
                ['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'R51'],
                ['nama_diagnosa' => 'Sakit kepala', 'jenis' => 'utama']
            );

            if (! $examination->clinicalNoteVersions()->exists()) {
                $recorder = new ClinicalNoteRecorder;
                $recorder->recordFinal($examination, $recorder->snapshot($visit, $examination), $doctorUser->id);
                $examination->update([
                    'status' => 'selesai',
                    'signed_by_user_id' => $doctorUser->id,
                    'signed_at' => now(),
                ]);
                $visit->update(['status' => 'kasir']);
            }
        });
    }
}
