<?php

namespace Database\Factories;

use App\Models\CorrespondenceTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CorrespondenceTemplate>
 */
class CorrespondenceTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->bothify('TPL-???-####'),
            'nama' => fake()->words(3, true),
            'jenis' => 'sakit',
            'prefix' => 'SKS',
            'format_nomor' => '[prefix]/[urut]/[bulan]/[tahun]',
            'nomor_berikutnya' => 1,
            'isi' => 'Surat keterangan untuk [pasien] pada [tanggal].',
            'is_active' => true,
        ];
    }
}
