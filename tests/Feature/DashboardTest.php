<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\CalendarDayStatus;
use App\Enums\DutyType;
use App\Enums\Role;
use App\Enums\SchoolDay;
use App\Filament\Pages\Dashboard;
use App\Models\AdditionalDuty;
use App\Models\Journal;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\Substitution;
use App\Models\TeacherAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrators_and_the_principal_and_koordinator_ekskul_get_the_leadership_view(): void
    {
        foreach ([Role::KepalaSekolah, Role::WakaKurikulum, Role::AdminKurikulum, Role::KoordinatorEkskul] as $role) {
            $this->actingAs(User::factory()->withRoles($role)->create());

            $this->assertTrue(Livewire::test(Dashboard::class)->instance()->isPimpinanView(), "Expected {$role->value} to get the leadership view");
        }
    }

    public function test_a_plain_teacher_gets_the_teacher_view(): void
    {
        $this->actingAs(User::factory()->create());

        $this->assertFalse(Livewire::test(Dashboard::class)->instance()->isPimpinanView());
    }

    public function test_a_weekend_shows_as_libur_even_with_no_calendar_exception(): void
    {
        $this->travelTo(Carbon::parse('2026-09-19 08:00:00'));
        $this->assertTrue(Carbon::today()->isSaturday());

        $this->actingAs(User::factory()->create());

        $status = Livewire::test(Dashboard::class)->instance()->getTodayStatus();

        $this->assertSame(CalendarDayStatus::Libur, $status);
    }

    public function test_attendance_summary_counts_todays_statuses(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 08:00:00'));

        TeacherAttendance::factory()->create(['status' => AttendanceStatus::Hadir]);
        TeacherAttendance::factory()->create(['status' => AttendanceStatus::Hadir]);
        TeacherAttendance::factory()->create(['status' => AttendanceStatus::Sakit]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        $summary = Livewire::test(Dashboard::class)->instance()->getAttendanceSummary();

        $this->assertSame(2, $summary['hadir']);
        $this->assertSame(1, $summary['sakit']);
        $this->assertSame(0, $summary['izin']);
    }

    public function test_journal_summary_reports_filled_against_expected_slots(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 08:00:00'));

        $semester = Semester::factory()->active()->create();
        $scheduleWithJournal = Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
        ]);
        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'day_of_week' => SchoolDay::Senin,
        ]);
        Journal::factory()->create([
            'schedule_id' => $scheduleWithJournal->id,
            'date' => '2026-09-14',
        ]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        $summary = Livewire::test(Dashboard::class)->instance()->getJournalSummary();

        $this->assertSame(1, $summary['filled']);
        $this->assertSame(2, $summary['expected']);
    }

    public function test_workload_analysis_sums_teaching_and_duty_jp_per_teacher(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create(['name' => 'Guru Uji']);

        Schedule::factory()->create([
            'semester_id' => $semester->id,
            'teacher_id' => $teacher->id,
            'subject_id' => Subject::factory()->create(['counts_toward_teaching_load' => true]),
        ]);
        AdditionalDuty::factory()->create(['teacher_id' => $teacher->id, 'jp' => 2, 'type' => DutyType::Tambahan]);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        $analysis = Livewire::test(Dashboard::class)->instance()->getWorkloadAnalysis();
        $row = collect($analysis)->firstWhere('teacher', 'Guru Uji');

        $this->assertNotNull($row);
        $this->assertSame(3, $row['jp']);
    }

    public function test_todays_substitutions_are_listed_for_the_leadership_view(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 08:00:00'));

        $schedule = Schedule::factory()->create();
        Substitution::factory()->create(['schedule_id' => $schedule->id, 'date' => '2026-09-14']);
        Substitution::factory()->create(['date' => '2026-09-13']);

        $this->actingAs(User::factory()->withRoles(Role::WakaKurikulum)->create());

        $substitutions = Livewire::test(Dashboard::class)->instance()->getTodaysSubstitutions();

        $this->assertCount(1, $substitutions);
    }

    public function test_the_daily_reminder_only_applies_after_3pm(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 10:00:00'));
        $this->actingAs(User::factory()->create());
        $this->assertFalse(Livewire::test(Dashboard::class)->instance()->isPastDailyReminderTime());

        $this->travelTo(Carbon::parse('2026-09-14 15:00:00'));
        $this->assertTrue(Livewire::test(Dashboard::class)->instance()->isPastDailyReminderTime());
    }

    public function test_a_teacher_who_has_filled_todays_attendance_is_detected(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 08:00:00'));

        $teacher = User::factory()->create();
        $this->actingAs($teacher);

        $this->assertFalse(Livewire::test(Dashboard::class)->instance()->hasFilledAttendanceToday());

        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-09-14']);

        $this->assertTrue(Livewire::test(Dashboard::class)->instance()->hasFilledAttendanceToday());
    }

    public function test_my_substitutions_today_includes_both_directions(): void
    {
        $this->travelTo(Carbon::parse('2026-09-14 08:00:00'));

        $me = User::factory()->create();
        $mySchedule = Schedule::factory()->create(['teacher_id' => $me->id]);
        Substitution::factory()->create(['schedule_id' => $mySchedule->id, 'date' => '2026-09-14']);

        $othersSchedule = Schedule::factory()->create();
        Substitution::factory()->create(['schedule_id' => $othersSchedule->id, 'date' => '2026-09-14', 'substitute_teacher_id' => $me->id]);

        $this->actingAs($me);

        $substitutions = Livewire::test(Dashboard::class)->instance()->getMySubstitutionsToday();

        $this->assertCount(2, $substitutions);
    }
}
