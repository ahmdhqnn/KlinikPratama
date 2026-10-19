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
        foreach ([
            ['nama' => 'Peserta Contoh ASN Satu', 'nik' => '0000000000000001', 'nip' => '000000000000000001', 'kategori' => 'asn', 'jenis_lainnya' => null, 'tanggal_lahir' => '1990-05-15', 'jenis_kelamin' => 'L'],
            ['nama' => 'Peserta Contoh ASN Dua', 'nik' => '0000000000000002', 'nip' => '000000000000000002', 'kategori' => 'asn', 'jenis_lainnya' => null, 'tanggal_lahir' => '1985-11-20', 'jenis_kelamin' => 'P'],
            ['nama' => 'Peserta Contoh Magang', 'nik' => '0000000000000003', 'nip' => null, 'kategori' => 'lainnya', 'jenis_lainnya' => 'magang', 'tanggal_lahir' => '2000-01-10', 'jenis_kelamin' => 'L'],
        ] as $person) {
            Kepesertaan::firstOrCreate(['nik' => $person['nik']], [
                ...$person, 'status_kepegawaian' => 'aktif', 'unit_kerja' => 'Unit Contoh',
                'tempat_lahir' => 'Jakarta', 'agama' => 'Islam', 'alamat' => 'Alamat sintetis',
                'rt' => '001', 'rw' => '002', 'kelurahan' => 'Kelurahan Contoh', 'kecamatan' => 'Kecamatan Contoh',
                'hak_layanan' => true,
                'referensi_bukti' => 'DATA SINTETIS UNTUK DEMO', 'verified_by' => $actor->id, 'verified_at' => now(),
            ]);
        }
    }
}
