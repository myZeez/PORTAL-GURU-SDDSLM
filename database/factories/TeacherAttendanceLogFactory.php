<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\TeacherAttendanceLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAttendanceLog>
 */
class TeacherAttendanceLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'teacher_attendance_id' => null,
            'teacher_id' => User::factory(),
            'date' => now()->toDateString(),
            'actor_id' => User::factory(),
            'action' => TeacherAttendanceLog::ACTION_CREATED,
            'before' => null,
            'after' => ['status' => AttendanceStatus::Hadir->value],
            'reason' => fake()->sentence(),
        ];
    }
}
