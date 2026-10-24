<?php

namespace Database\Factories;

use App\Models\PerformanceSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceSetting>
 */
class PerformanceSettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'is_scoring_enabled' => false,
            'weights' => null,
            'target_percentage' => PerformanceSetting::DEFAULT_TARGET,
            'thresholds' => null,
        ];
    }

    /**
     * Scoring on, with weights that total 100%.
     *
     * @param  array<string, int>  $weights
     */
    public function scoring(array $weights = []): static
    {
        return $this->state(fn (): array => [
            'is_scoring_enabled' => true,
            'weights' => $weights ?: [
                'attendance' => 25, 'journal' => 25, 'tasks' => 15, 'simpati' => 10, 'class_administration' => 15, 'follow_up' => 10,
            ],
        ]);
    }
}
