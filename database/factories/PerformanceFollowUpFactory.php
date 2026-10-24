<?php

namespace Database\Factories;

use App\Enums\PerformanceFollowUpStatus;
use App\Enums\PerformanceIndicator;
use App\Models\PerformanceFollowUp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceFollowUp>
 */
class PerformanceFollowUpFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory(),
            'indicator' => PerformanceIndicator::Journal,
            'finding' => fake()->sentence(),
            'recommendation' => fake()->sentence(),
            'responsible_id' => User::factory(),
            'target_date' => now()->addWeek()->toDateString(),
            'status' => PerformanceFollowUpStatus::Open,
            'completed_at' => null,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the follow-up is finished.
     */
    public function done(): static
    {
        return $this->state(fn (): array => [
            'status' => PerformanceFollowUpStatus::Done,
            'completed_at' => now(),
        ]);
    }
}
