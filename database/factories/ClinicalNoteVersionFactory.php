<?php

namespace Database\Factories;

use App\Models\ClinicalNoteVersion;
use App\Models\Pemeriksaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClinicalNoteVersion>
 */
class ClinicalNoteVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payload = [
            'anamnesis' => fake()->sentence(),
            'pemeriksaan_fisik' => fake()->sentence(),
            'catatan' => null,
            'edukasi' => null,
            'kontrol_berikutnya' => null,
            'diagnoses' => [],
            'treatments' => [],
            'prescriptions' => [],
            'referrals' => [],
        ];

        return [
            'version' => 1,
            'kind' => 'finalized',
            'actor_id' => User::factory(),
            'reason' => null,
            'payload' => $payload,
            'previous_hash' => null,
            'content_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
            'recorded_at' => now(),
        ];
    }

    public function forExamination(Pemeriksaan $examination): static
    {
        return $this->state(fn (): array => ['pemeriksaan_id' => $examination->id]);
    }
}
