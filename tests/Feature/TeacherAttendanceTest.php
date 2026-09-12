<?php

namespace Tests\Feature;

use App\Enums\CalendarDayStatus;
use App\Models\CalendarDay;
use App\Models\Semester;
use App\Models\TeacherAttendance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TeacherAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_weekday_with_no_calendar_exception_is_fillable(): void
    {
        $monday = Carbon::parse('2026-09-14');
        $this->assertTrue($monday->isMonday());

        $this->assertTrue(TeacherAttendance::isFillableDate($monday));
    }

    public function test_a_weekend_is_not_fillable(): void
    {
        $saturday = Carbon::parse('2026-09-19');
        $this->assertTrue($saturday->isSaturday());

        $this->assertFalse(TeacherAttendance::isFillableDate($saturday));
    }

    public function test_a_day_marked_libur_is_not_fillable(): void
    {
        $semester = Semester::factory()->active()->create();
        $monday = Carbon::parse('2026-09-14');

        CalendarDay::factory()->create([
            'semester_id' => $semester->id,
            'date' => $monday->toDateString(),
            'status' => CalendarDayStatus::Libur,
        ]);

        $this->assertFalse(TeacherAttendance::isFillableDate($monday));
    }

    public function test_a_day_marked_kegiatan_is_still_fillable(): void
    {
        $semester = Semester::factory()->active()->create();
        $monday = Carbon::parse('2026-09-14');

        CalendarDay::factory()->create([
            'semester_id' => $semester->id,
            'date' => $monday->toDateString(),
            'status' => CalendarDayStatus::Kegiatan,
        ]);

        $this->assertTrue(TeacherAttendance::isFillableDate($monday));
    }

    public function test_it_is_late_when_filled_after_the_date_it_is_for(): void
    {
        $attendance = TeacherAttendance::factory()->create([
            'date' => '2026-09-10',
            'filled_at' => '2026-09-11 08:00:00',
        ]);

        $this->assertTrue($attendance->is_late);
    }

    public function test_it_is_not_late_when_filled_on_the_same_date(): void
    {
        $attendance = TeacherAttendance::factory()->create([
            'date' => '2026-09-10',
            'filled_at' => '2026-09-10 06:15:00',
        ]);

        $this->assertFalse($attendance->is_late);
    }
}
