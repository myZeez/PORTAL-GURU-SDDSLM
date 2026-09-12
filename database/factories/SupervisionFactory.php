<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\Supervision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supervision>
 */
class SupervisionFactory extends Factory
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
            'subject_id' => Subject::factory(),
            'supervisor_id' => User::factory(),
            'date' => now()->addWeek()->toDateString(),
            'location' => 'Ruang Kelas',
        ];
    }
}
