<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\Substitution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Substitution>
 */
class SubstitutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->toDateString(),
            'schedule_id' => Schedule::factory(),
            'substitute_teacher_id' => User::factory(),
            'reason' => fake()->sentence(),
        ];
    }
}
