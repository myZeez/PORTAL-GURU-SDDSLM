<?php

namespace Database\Factories;

use App\Enums\ClassMonitoringGroup;
use App\Enums\ClassMonitoringItemStatus;
use App\Models\MonitoringItem;
use App\Models\MonitoringResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoringItem>
 */
class MonitoringItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'monitoring_result_id' => MonitoringResult::factory(),
            'group' => ClassMonitoringGroup::AdministrasiPembelajaran,
            'label' => fake()->sentence(4),
            'status' => ClassMonitoringItemStatus::Lengkap,
        ];
    }
}
