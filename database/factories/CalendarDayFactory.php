<?php

namespace Database\Factories;

use App\Enums\CalendarDayStatus;
use App\Models\CalendarDay;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarDay>
 */
class CalendarDayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'semester_id' => Semester::factory(),
            'date' => fake()->unique()->dateTimeBetween('-1 month', '+2 months')->format('Y-m-d'),
            'status' => fake()->randomElement(CalendarDayStatus::exceptions()),
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the date is a holiday.
     */
    public function libur(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CalendarDayStatus::Libur,
        ]);
    }
}
