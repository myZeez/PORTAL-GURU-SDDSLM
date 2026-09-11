<?php

namespace Database\Factories;

use App\Models\TimeSlot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeSlot>
 */
class TimeSlotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now()->setTime(6, 0)->addMinutes(fake()->unique()->numberBetween(0, 40) * 15);

        return [
            'starts_at' => $startsAt->format('H:i'),
            'ends_at' => $startsAt->copy()->addMinutes(30)->format('H:i'),
        ];
    }
}
