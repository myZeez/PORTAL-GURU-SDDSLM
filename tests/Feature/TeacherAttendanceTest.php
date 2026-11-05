<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
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

    public function test_each_attendance_status_has_the_expected_credit_weight(): void
    {
        $this->assertSame(100, AttendanceStatus::Hadir->attendanceWeight());
        $this->assertSame(75, AttendanceStatus::PulangCepat->attendanceWeight());
        $this->assertSame(50, AttendanceStatus::Izin->attendanceWeight());
        $this->assertSame(50, AttendanceStatus::Sakit->attendanceWeight());
        $this->assertSame(0, AttendanceStatus::Cuti->attendanceWeight());
        $this->assertSame(0, AttendanceStatus::Alpa->attendanceWeight());
    }
}
