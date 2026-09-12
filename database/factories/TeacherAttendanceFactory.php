<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAttendance>
 */
class TeacherAttendanceFactory extends Factory
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
            'date' => now()->toDateString(),
            'status' => AttendanceStatus::Hadir,
            'filled_at' => now(),
        ];
    }

    /**
     * Indicate the teacher is on leave for a date range.
     */
    public function cuti(string $startsOn, string $endsOn): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AttendanceStatus::Cuti,
            'leave_starts_on' => $startsOn,
            'leave_ends_on' => $endsOn,
        ]);
    }
}
