<?php

namespace Database\Factories;

use App\Enums\CalendarDayStatus;
use App\Models\CalendarDayLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarDayLog>
 */
class CalendarDayLogFactory extends Factory
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
            'actor_id' => User::factory(),
            'before_status' => CalendarDayStatus::Efektif->value,
            'after_status' => CalendarDayStatus::Libur->value,
            'notes' => fake()->sentence(),
        ];
    }
}
