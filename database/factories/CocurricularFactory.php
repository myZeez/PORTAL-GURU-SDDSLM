<?php

namespace Database\Factories;

use App\Enums\CocurricularActivityType;
use App\Enums\GraduateDimension;
use App\Models\Classroom;
use App\Models\Cocurricular;
use App\Models\CocurricularSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cocurricular>
 */
class CocurricularFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cocurricular_schedule_id' => CocurricularSchedule::factory(),
            'classroom_id' => Classroom::factory(),
            'activity_type' => CocurricularActivityType::Proyek,
            'description' => fake()->sentence(),
            'dimensions' => [GraduateDimension::Kolaborasi->value, GraduateDimension::Kreativitas->value],
            'created_by' => User::factory(),
        ];
    }
}
