<?php

namespace Database\Factories;

use App\Models\Kunjungan;
use App\Models\OdontogramFinding;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OdontogramFinding>
 */
class OdontogramFindingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tooth_fdi' => '46',
            'surface' => 'O',
            'finding_code' => 'caries',
            'notes' => $this->faker->sentence(),
            'recorded_by_user_id' => User::factory(),
        ];
    }

    public function forVisit(Kunjungan $visit): static
    {
        return $this->state(fn (): array => ['kunjungan_id' => $visit->id]);
    }
}
