<?php

namespace Database\Factories;

use App\Enums\SchoolDay;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'semester_id' => Semester::factory(),
            'day_of_week' => fake()->randomElement(SchoolDay::cases()),
            'time_slot_id' => TimeSlot::factory(),
            'classroom_id' => Classroom::factory(),
            'subject_id' => Subject::factory(),
            'teacher_id' => User::factory(),
        ];
    }
}
