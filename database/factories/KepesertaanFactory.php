<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class KepesertaanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => fake()->name(), 'nik' => fake()->unique()->numerify('################'),
            'nip' => fake()->unique()->numerify('##################'), 'kategori' => 'asn', 'status_kepegawaian' => 'aktif',
            'unit_kerja' => 'Biro Umum',
            'tempat_lahir' => fake()->city(), 'tanggal_lahir' => fake()->dateTimeBetween('-65 years', '-18 years')->format('Y-m-d'),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']), 'agama' => 'Islam',
            'golongan_darah' => fake()->randomElement(['A', 'B', 'AB', 'O']),
            'alamat' => fake()->streetAddress(), 'rt' => '001', 'rw' => '002',
            'kelurahan' => fake()->city(), 'kecamatan' => fake()->city(),
            'hak_layanan' => true,
            'referensi_bukti' => 'Fixture daftar kepegawaian terverifikasi',
            'verified_by' => User::factory(), 'verified_at' => now(),
        ];
    }
}
