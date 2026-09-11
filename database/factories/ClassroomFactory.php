<?php

namespace Database\Factories;

use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Classroom>
 */
class ClassroomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grade = fake()->numberBetween(1, 6);

        return [
            'code' => $grade.'-'.Str::upper(fake()->unique()->lexify('???')),
            'name' => fake()->name(),
            'grade' => $grade,
            'homeroom_teacher_id' => null,
            'assistant_teacher_id' => null,
        ];
    }
}
