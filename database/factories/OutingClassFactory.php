<?php

namespace Database\Factories;

use App\Enums\OutingStatus;
use App\Models\Classroom;
use App\Models\OutingClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutingClass>
 */
class OutingClassFactory extends Factory
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
            'date' => now()->addWeek()->toDateString(),
            'destination' => fake()->city(),
            'status' => OutingStatus::Menunggu,
            'requested_by' => User::factory(),
        ];
    }
}
