<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\Classroom;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assessment>
 */
class AssessmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classroom_id' => Classroom::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory(),
            'date' => now()->toDateString(),
            'chapter' => 'Bab '.fake()->numberBetween(1, 10),
            'is_completed' => false,
        ];
    }
}
