<?php

namespace Database\Factories;

use App\Models\RecordAudit;
use App\Models\Substitution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecordAudit>
 */
class RecordAuditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subject_type' => Substitution::class,
            'subject_id' => fake()->numberBetween(1, 500),
            'action' => RecordAudit::ACTION_UPDATED,
            'label' => 'I-A — Matematika',
            'actor_id' => User::factory(),
            'before' => ['Alasan' => 'Sakit'],
            'after' => ['Alasan' => 'Dinas luar'],
            'reason' => null,
        ];
    }
}
