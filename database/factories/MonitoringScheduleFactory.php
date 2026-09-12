<?php

namespace Database\Factories;

use App\Models\Classroom;
use App\Models\MonitoringSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoringSchedule>
 */
class MonitoringScheduleFactory extends Factory
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
            'classroom_id' => Classroom::factory(),
            'created_by' => User::factory(),
        ];
    }
}
