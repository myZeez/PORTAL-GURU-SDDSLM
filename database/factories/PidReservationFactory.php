<?php

namespace Database\Factories;

use App\Enums\OutingStatus;
use App\Models\PidReservation;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PidReservation>
 */
class PidReservationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => now()->addWeek()->toDateString(),
            'starts_at' => '08:00:00',
            'ends_at' => '09:00:00',
            'location' => 'Ruang PID 1',
            'subject_id' => Subject::factory(),
            'purpose' => fake()->sentence(),
            'status' => OutingStatus::Menunggu,
            'requested_by' => User::factory(),
        ];
    }
}
