<?php

namespace Database\Seeders;

use App\ClinicalNoteRecorder;
use App\Models\Diagnosa;
use App\Models\Kunjungan;
use App\Models\Nakes;
use App\Models\OdontogramFinding;
use App\Models\Pasien;
use App\Models\Pemeriksaan;
use App\Models\Poliklinik;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OdontogramFindingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Seeder odontogram sintetis hanya untuk lingkungan local/testing.');
        }

        DB::transaction(function (): void {
            $doctorUser = User::firstOrCreate(
                ['email' => 'dokter-gigi-uat@klinik.test'],
                ['name' => 'Dokter Gigi UAT', 'password' => 'password', 'role' => 'dokter', 'is_active' => true]
            );
            $doctor = Nakes::firstOrCreate(
                ['kode' => 'DKT-GIGI-UAT'],
                ['nama' => 'drg. UAT', 'kategori' => 'medis', 'jabatan' => 'dokter', 'user_id' => $doctorUser->id, 'is_active' => true]
            );
            $clinic = Poliklinik::where('jenis', 'gigi')->where('is_active', true)->firstOrFail();
            $patient = Pasien::firstOrCreate(
                ['no_rm' => 'RM-UAT-ODONTO'],
                ['nama' => 'Pasien UAT Gigi', 'jenis_kelamin' => 'P']
            );
            $visit = Kunjungan::firstOrCreate(
                ['no_kunjungan' => 'KNJ-UAT-ODONTO'],
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
                    'anamnesis' => 'Nyeri geraham kanan bawah saat mengunyah.',
                    'pemeriksaan_fisik' => 'Karies oklusal gigi 46.',
                    'status' => 'draft',
                ]
            );
            Diagnosa::firstOrCreate(
                ['pemeriksaan_id' => $examination->id, 'kode_icd10' => 'K02.9'],
                ['nama_diagnosa' => 'Karies gigi', 'jenis' => 'utama']
            );
            OdontogramFinding::firstOrCreate(
                ['kunjungan_id' => $visit->id, 'tooth_fdi' => '46', 'surface' => 'O'],
                ['finding_code' => 'caries', 'notes' => 'Kavitas oklusal', 'recorded_by_user_id' => $doctorUser->id]
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
