<?php

namespace Database\Factories;

use App\Enums\SchoolDay;
use App\Models\Extracurricular;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Extracurricular>
 */
class ExtracurricularFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Pramuka', 'Futsal', 'Tahfiz', 'Robotik', 'Melukis']),
            'day' => SchoolDay::Selasa,
            'start_time' => '15:00:00',
            'end_time' => '16:30:00',
            'location' => 'Lapangan Sekolah',
            'external_coach' => null,
            'created_by' => User::factory(),
        ];
    }
}
