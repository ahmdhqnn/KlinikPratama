<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ObatBatchFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nomor_batch' => 'BATCH-'.fake()->unique()->bothify('????####'),
            'expired_at' => today()->addYear(), 'stok' => 10, 'harga_beli' => 1000,
            'status' => 'tersedia', 'sumber' => 'pengadaan', 'referensi' => 'Fixture penerimaan',
        ];
    }
}
