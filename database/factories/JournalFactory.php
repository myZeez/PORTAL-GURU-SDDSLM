<?php

namespace Database\Factories;

use App\Models\Journal;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Journal>
 */
class JournalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $schedule = Schedule::factory()->create();

        return [
            'date' => now()->toDateString(),
            'schedule_id' => $schedule->id,
            'teacher_id' => $schedule->teacher_id,
            'topic' => fake()->sentence(4),
            'students_present' => 28,
            'students_permitted' => 0,
            'students_sick' => 0,
            'students_absent' => 0,
        ];
    }
}
