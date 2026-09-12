<?php

namespace Tests\Feature;

use App\Enums\CalendarDayStatus;
use App\Enums\SchoolDay;
use App\Models\CalendarDay;
use App\Models\Journal;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Substitution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_weekday_with_no_calendar_exception_is_fillable(): void
    {
        $monday = Carbon::parse('2026-09-14');

        $this->assertTrue(Journal::isFillableDate($monday));
    }

    public function test_a_day_marked_kegiatan_is_not_fillable_unlike_attendance(): void
    {
        $semester = Semester::factory()->active()->create();
        $monday = Carbon::parse('2026-09-14');

        CalendarDay::factory()->create([
            'semester_id' => $semester->id,
            'date' => $monday->toDateString(),
            'status' => CalendarDayStatus::Kegiatan,
        ]);

        $this->assertFalse(Journal::isFillableDate($monday));
    }

    public function test_a_teachers_own_schedule_is_effective_on_its_day(): void
    {
        $teacher = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $teacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);

        $slots = Schedule::effectiveForTeacherOnDate($teacher->id, Carbon::parse('2026-09-14'));

        $this->assertTrue($slots->contains('id', $schedule->id));
    }

    public function test_a_schedule_substituted_away_is_not_effective_for_the_original_teacher(): void
    {
        $teacher = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $teacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);
        Substitution::factory()->create([
            'schedule_id' => $schedule->id,
            'date' => '2026-09-14',
        ]);

        $slots = Schedule::effectiveForTeacherOnDate($teacher->id, Carbon::parse('2026-09-14'));

        $this->assertFalse($slots->contains('id', $schedule->id));
    }

    public function test_a_substitute_teacher_inherits_the_schedule_for_that_date(): void
    {
        $originalTeacher = User::factory()->create();
        $substitute = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $originalTeacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);
        Substitution::factory()->create([
            'schedule_id' => $schedule->id,
            'date' => '2026-09-14',
            'substitute_teacher_id' => $substitute->id,
        ]);

        $slots = Schedule::effectiveForTeacherOnDate($substitute->id, Carbon::parse('2026-09-14'));

        $this->assertTrue($slots->contains('id', $schedule->id));
    }

    public function test_the_substitution_does_not_carry_over_to_a_different_date(): void
    {
        $originalTeacher = User::factory()->create();
        $substitute = User::factory()->create();
        $schedule = Schedule::factory()->create([
            'teacher_id' => $originalTeacher->id,
            'day_of_week' => SchoolDay::Senin,
        ]);
        Substitution::factory()->create([
            'schedule_id' => $schedule->id,
            'date' => '2026-09-14',
            'substitute_teacher_id' => $substitute->id,
        ]);

        $slots = Schedule::effectiveForTeacherOnDate($substitute->id, Carbon::parse('2026-09-21'));

        $this->assertFalse($slots->contains('id', $schedule->id));
    }
}
