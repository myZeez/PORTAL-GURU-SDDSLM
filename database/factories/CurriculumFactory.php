<?php

namespace Database\Factories;

use App\Models\Curriculum;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curriculum>
 */
class CurriculumFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'drive_url' => 'https://drive.google.com/file/d/'.fake()->regexify('[A-Za-z0-9_-]{28}').'/view?usp=sharing',
        ];
    }
}
