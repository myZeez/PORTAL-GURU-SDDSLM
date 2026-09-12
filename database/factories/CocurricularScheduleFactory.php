<?php

namespace Database\Factories;

use App\Models\CocurricularSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CocurricularSchedule>
 */
class CocurricularScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->addWeek()->toDateString(),
            'theme' => fake()->sentence(3),
            'created_by' => User::factory(),
        ];
    }
}
