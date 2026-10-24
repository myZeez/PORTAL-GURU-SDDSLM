<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\SimpatiCheck;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SimpatiCheck>
 */
class SimpatiCheckFactory extends Factory
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
            'is_filled' => true,
            'checked_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the class has not filled SIMPATI.
     */
    public function unfilled(): static
    {
        return $this->state(fn (): array => ['is_filled' => false]);
    }
}
