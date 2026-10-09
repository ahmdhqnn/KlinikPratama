<?php

namespace Database\Seeders;

use App\Models\Kepesertaan;
use App\Models\User;
use Illuminate\Database\Seeder;

class KepesertaanSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Data kepesertaan contoh hanya untuk local/testing.');
        }
        $actor = User::where('role', 'admin')->firstOrFail();
        Kepesertaan::firstOrCreate(['nik' => '0000000000000001'], [
            'nama' => 'Peserta Contoh Internal', 'nip' => '000000000000000001',
            'kategori' => 'pegawai_pusat', 'status_kepegawaian' => 'aktif', 'unit_kerja' => 'Unit Contoh',
            'cost_center' => 'CC-CONTOH', 'hak_layanan' => true, 'berlaku_mulai' => '2026-01-01',
            'referensi_bukti' => 'DATA SINTETIS UNTUK DEMO', 'verified_by' => $actor->id, 'verified_at' => now(),
        ]);
    }
}
