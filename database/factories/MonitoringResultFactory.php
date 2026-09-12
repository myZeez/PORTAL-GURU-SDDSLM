<?php

namespace Database\Factories;

use App\Models\MonitoringResult;
use App\Models\MonitoringSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoringResult>
 */
class MonitoringResultFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'monitoring_schedule_id' => MonitoringSchedule::factory(),
            'created_by' => User::factory(),
        ];
    }
}
