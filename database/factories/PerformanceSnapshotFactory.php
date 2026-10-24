<?php

namespace Database\Factories;

use App\Enums\PerformancePeriod;
use App\Models\PerformanceSnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceSnapshot>
 */
class PerformanceSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'teacher_id' => User::factory(),
            'period_type' => PerformancePeriod::Month,
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'indicators' => ['attendance' => ['value' => 90.0, 'counted' => 20, 'full' => 18]],
            'jp' => ['teaching' => 24, 'duties' => 0, 'total' => 24],
            'score' => null,
            'notes' => null,
            'saved_by' => User::factory(),
        ];
    }
}
