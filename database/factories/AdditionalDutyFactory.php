<?php

namespace Database\Factories;

use App\Enums\DutyType;
use App\Models\AdditionalDuty;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdditionalDuty>
 */
class AdditionalDutyFactory extends Factory
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
            'title' => fake()->jobTitle(),
            'type' => DutyType::Tambahan,
            'jp' => 2,
        ];
    }
}
