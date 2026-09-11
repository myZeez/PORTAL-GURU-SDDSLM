<?php

namespace Database\Factories;

use App\Enums\Term;
use App\Models\Semester;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Semester>
 */
class SemesterFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startYear = fake()->unique()->numberBetween(2000, 2099);

        return [
            'academic_year' => $startYear.'/'.($startYear + 1),
            'term' => fake()->randomElement(Term::cases()),
            'starts_on' => null,
            'ends_on' => null,
            'is_active' => false,
        ];
    }

    /**
     * Indicate that the semester is the one currently running.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
