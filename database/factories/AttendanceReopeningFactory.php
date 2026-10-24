<?php

namespace Database\Factories;

use App\Models\AttendanceReopening;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceReopening>
 */
class AttendanceReopeningFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->subDay()->toDateString(),
            'reason' => fake()->sentence(),
            'opened_by' => User::factory(),
            'closed_at' => null,
            'closed_by' => null,
        ];
    }

    /**
     * Indicate that the date has been closed again.
     */
    public function closed(): static
    {
        return $this->state(fn (): array => [
            'closed_at' => now(),
            'closed_by' => User::factory(),
        ]);
    }
}
