<?php

namespace Database\Factories;

use App\Enums\SchoolDay;
use App\Models\DailyRoutine;
use App\Models\TimeSlot;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DailyRoutine>
 */
class DailyRoutineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'day_of_week' => fake()->randomElement(SchoolDay::cases()),
            'time_slot_id' => TimeSlot::factory(),
            'label' => Str::ucfirst(fake()->words(2, true)),
        ];
    }
}
