<?php

namespace Database\Factories;

use App\Models\ExtracurricularAnnouncement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtracurricularAnnouncement>
 */
class ExtracurricularAnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'body' => fake()->paragraph(),
            'image_drive_url' => null,
            'created_by' => User::factory(),
        ];
    }
}
