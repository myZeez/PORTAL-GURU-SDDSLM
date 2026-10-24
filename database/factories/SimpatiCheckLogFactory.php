<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\SimpatiCheckLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SimpatiCheckLog>
 */
class SimpatiCheckLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'date' => now()->toDateString(),
            'actor_id' => User::factory(),
            'was_filled' => false,
            'is_filled' => true,
        ];
    }
}
