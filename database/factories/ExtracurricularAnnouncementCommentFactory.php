<?php

namespace Database\Factories;

use App\Models\ExtracurricularAnnouncement;
use App\Models\ExtracurricularAnnouncementComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtracurricularAnnouncementComment>
 */
class ExtracurricularAnnouncementCommentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'extracurricular_announcement_id' => ExtracurricularAnnouncement::factory(),
            'user_id' => User::factory(),
            'body' => fake()->sentence(),
        ];
    }
}
