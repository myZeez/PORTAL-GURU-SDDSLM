<?php

namespace Database\Factories;

use App\Enums\ExtracurricularAttendanceStatus;
use App\Models\Extracurricular;
use App\Models\ExtracurricularAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExtracurricularAttendance>
 */
class ExtracurricularAttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'extracurricular_id' => Extracurricular::factory(),
            'teacher_id' => User::factory(),
            'date' => now()->toDateString(),
            'status' => ExtracurricularAttendanceStatus::Hadir,
        ];
    }
}
