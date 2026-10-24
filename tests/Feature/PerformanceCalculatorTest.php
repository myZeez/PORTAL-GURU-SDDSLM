<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\CalendarDayStatus;
use App\Enums\ClassMonitoringResultStatus;
use App\Enums\PerformanceIndicator;
use App\Enums\PerformancePeriod;
use App\Enums\SchoolDay;
use App\Filament\Performance\PerformanceCalculator;
use App\Models\AdditionalDuty;
use App\Models\CalendarDay;
use App\Models\Classroom;
use App\Models\Journal;
use App\Models\MonitoringResult;
use App\Models\MonitoringSchedule;
use App\Models\PerformanceFollowUp;
use App\Models\PerformanceSetting;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\SimpatiCheck;
use App\Models\Subject;
use App\Models\Substitution;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\TeacherAttendance;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PerformanceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    /** Wednesday 28 Oct 2026; the evaluated week runs Mon 26 Oct – Sun 1 Nov. */
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-28 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function run_for(User $teacher, ?PerformanceSetting $settings = null, string $from = '2026-10-26', string $to = '2026-11-01'): array
    {
        $calculator = new PerformanceCalculator(Carbon::parse($from), Carbon::parse($to), $settings ?? PerformanceSetting::current());

        return $calculator->calculate(collect([$teacher]))[$teacher->id];
    }

    public function test_attendance_is_the_weighted_credit_over_required_days_and_todays_open_entry_is_not_counted(): void
    {
        $teacher = User::factory()->create();
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-26', 'status' => AttendanceStatus::Hadir]);
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-27', 'status' => AttendanceStatus::Izin]);

        $attendance = $this->run_for($teacher)['indicators']['attendance'];

        $this->assertSame(75.0, $attendance['value']);
        $this->assertSame(2, $attendance['counted']);
        $this->assertSame(1, $attendance['full']);
        $this->assertSame('pending', collect($attendance['items'])->last()['state']);
    }

    public function test_a_past_day_with_no_record_counts_as_missing_but_a_holiday_is_left_out(): void
    {
        $semester = Semester::factory()->active()->create();
        CalendarDay::factory()->create(['semester_id' => $semester->id, 'date' => '2026-10-27', 'status' => CalendarDayStatus::Libur]);
        $teacher = User::factory()->create();
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-28', 'status' => AttendanceStatus::Sakit]);

        $attendance = $this->run_for($teacher)['indicators']['attendance'];

        $this->assertSame(2, $attendance['counted']);
        $this->assertSame(25.0, $attendance['value']);
        $this->assertNotContains('2026-10-27', array_column($attendance['items'], 'date'));
    }

    public function test_a_period_that_has_not_started_has_no_attendance_value_rather_than_zero(): void
    {
        $teacher = User::factory()->create();

        $result = $this->run_for($teacher, from: '2026-11-02', to: '2026-11-08');

        $this->assertNull($result['indicators']['attendance']['value']);
        $this->assertNull($result['indicators']['journal']['value']);
    }

    public function test_journals_are_counted_against_the_teaching_schedule_and_substitutions_move_the_duty(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create();
        $replacement = User::factory()->create();
        $subject = Subject::factory()->create(['name' => 'Matematika']);
        $slot = TimeSlot::factory()->create();
        $class = Classroom::factory()->create(['code' => '4-AAA']);
        $make = fn (SchoolDay $day): Schedule => Schedule::factory()->create([
            'semester_id' => $semester->id, 'day_of_week' => $day, 'time_slot_id' => $slot->id,
            'classroom_id' => $class->id, 'subject_id' => $subject->id, 'teacher_id' => $teacher->id,
        ]);
        $monday = $make(SchoolDay::Senin);
        $tuesday = $make(SchoolDay::Selasa);
        $make(SchoolDay::Rabu);

        Journal::factory()->create(['schedule_id' => $monday->id, 'teacher_id' => $teacher->id, 'date' => '2026-10-26']);

        $journal = $this->run_for($teacher)['indicators']['journal'];
        $this->assertSame(2, $journal['counted']);
        $this->assertSame(50.0, $journal['value']);
        $this->assertSame('pending', collect($journal['items'])->last()['state']);

        Substitution::factory()->create(['schedule_id' => $tuesday->id, 'substitute_teacher_id' => $replacement->id, 'date' => '2026-10-27']);

        $this->assertSame(100.0, $this->run_for($teacher)['indicators']['journal']['value']);

        $covering = $this->run_for($replacement)['indicators']['journal'];
        $this->assertSame(1, $covering['counted']);
        $this->assertSame(0.0, $covering['value']);
    }

    public function test_a_teacher_with_nothing_scheduled_has_no_journal_value(): void
    {
        Semester::factory()->active()->create();

        $this->assertNull($this->run_for(User::factory()->create())['indicators']['journal']['value']);
    }

    public function test_tasks_count_only_when_due_or_done(): void
    {
        $teacher = User::factory()->create();
        $assign = fn (string $due, bool $done) => TaskAssignee::factory()->create([
            'user_id' => $teacher->id,
            'task_id' => Task::factory()->create(['due_on' => $due, 'starts_on' => '2026-10-20']),
            'is_completed' => $done,
            'completed_at' => $done ? now() : null,
        ]);
        $assign('2026-10-27', true);
        $assign('2026-10-27', false);
        $assign('2026-10-31', false);
        $assign('2026-10-31', true);

        $tasks = $this->run_for($teacher)['indicators']['tasks'];

        $this->assertSame(3, $tasks['counted']);
        $this->assertSame(66.7, $tasks['value']);
        $this->assertCount(4, $tasks['items']);
    }

    public function test_simpati_applies_only_to_a_pendamping_and_ignores_holidays(): void
    {
        $semester = Semester::factory()->active()->create();
        CalendarDay::factory()->create(['semester_id' => $semester->id, 'date' => '2026-10-27', 'status' => CalendarDayStatus::Libur]);
        $pendamping = User::factory()->create();
        $classroom = Classroom::factory()->create(['assistant_teacher_id' => $pendamping->id]);
        SimpatiCheck::factory()->create(['classroom_id' => $classroom->id, 'date' => '2026-10-26']);

        $simpati = $this->run_for($pendamping)['indicators']['simpati'];

        $this->assertSame(1, $simpati['counted']);
        $this->assertSame(100.0, $simpati['value']);
        $this->assertNull($this->run_for(User::factory()->create())['indicators']['simpati']['value']);
    }

    public function test_class_administration_averages_monitoring_scores_and_no_visit_means_no_value_not_zero(): void
    {
        $homeroom = User::factory()->create();
        $classroom = Classroom::factory()->create(['homeroom_teacher_id' => $homeroom->id]);

        $this->assertNull($this->run_for($homeroom)['indicators']['class_administration']['value']);

        foreach ([['2026-10-27', 90], ['2026-10-29', 70]] as [$date, $score]) {
            $visit = MonitoringSchedule::factory()->create(['classroom_id' => $classroom->id, 'date' => $date]);
            MonitoringResult::factory()->create([
                'monitoring_schedule_id' => $visit->id,
                'score' => $score,
                'status' => $score >= 90 ? ClassMonitoringResultStatus::SangatLengkap : ClassMonitoringResultStatus::PerluTindakLanjut,
            ]);
        }

        $admin = $this->run_for($homeroom)['indicators']['class_administration'];

        $this->assertSame(80.0, $admin['value']);
        $this->assertSame(2, $admin['counted']);
    }

    public function test_a_scheduled_visit_without_results_is_pending_not_zero(): void
    {
        $homeroom = User::factory()->create();
        $classroom = Classroom::factory()->create(['homeroom_teacher_id' => $homeroom->id]);
        MonitoringSchedule::factory()->create(['classroom_id' => $classroom->id, 'date' => '2026-10-29']);

        $admin = $this->run_for($homeroom)['indicators']['class_administration'];

        $this->assertNull($admin['value']);
        $this->assertSame('pending', $admin['items'][0]['state']);
    }

    public function test_follow_ups_assigned_to_the_teacher_count_when_due_or_done(): void
    {
        $teacher = User::factory()->create();
        PerformanceFollowUp::factory()->done()->create(['responsible_id' => $teacher->id, 'target_date' => '2026-10-27']);
        PerformanceFollowUp::factory()->create(['responsible_id' => $teacher->id, 'target_date' => '2026-10-27']);
        PerformanceFollowUp::factory()->create(['responsible_id' => $teacher->id, 'target_date' => '2026-10-31']);
        PerformanceFollowUp::factory()->create(['target_date' => '2026-10-27']);

        $followUp = $this->run_for($teacher)['indicators']['follow_up'];

        $this->assertSame(2, $followUp['counted']);
        $this->assertSame(50.0, $followUp['value']);
    }

    public function test_the_combined_score_is_off_until_scoring_is_enabled_and_weights_total_100(): void
    {
        $teacher = User::factory()->create();
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-26']);

        $this->assertNull($this->run_for($teacher)['score']);

        $broken = PerformanceSetting::factory()->create(['is_scoring_enabled' => true, 'weights' => ['attendance' => 60, 'journal' => 30]]);
        $this->assertNull($this->run_for($teacher, $broken)['score']);
    }

    public function test_the_score_uses_only_applicable_indicators_and_rescales_their_weights(): void
    {
        $teacher = User::factory()->create();
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-26', 'status' => AttendanceStatus::Hadir]);
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-27', 'status' => AttendanceStatus::Alpa]);
        $settings = PerformanceSetting::factory()->scoring(['attendance' => 50, 'journal' => 30, 'tasks' => 20, 'simpati' => 0, 'class_administration' => 0, 'follow_up' => 0])->create();

        $result = $this->run_for($teacher, $settings);

        $this->assertSame(50, $result['score']['value']);
        $this->assertSame(['attendance'], array_keys($result['score']['breakdown']));
        $this->assertSame(100.0, $result['score']['breakdown']['attendance']['weight']);
        $this->assertNull($result['indicators']['journal']['value']);
    }

    public function test_the_score_blends_weights_across_the_indicators_that_apply(): void
    {
        $teacher = User::factory()->create();
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-26', 'status' => AttendanceStatus::Hadir]);
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-27', 'status' => AttendanceStatus::Hadir]);
        TaskAssignee::factory()->create([
            'user_id' => $teacher->id,
            'task_id' => Task::factory()->create(['due_on' => '2026-10-27', 'starts_on' => '2026-10-20']),
            'is_completed' => false,
        ]);
        $settings = PerformanceSetting::factory()->scoring(['attendance' => 75, 'journal' => 0, 'tasks' => 25, 'simpati' => 0, 'class_administration' => 0, 'follow_up' => 0])->create();

        $this->assertSame(75, $this->run_for($teacher, $settings)['score']['value']);
    }

    public function test_the_label_appears_only_when_every_cutoff_is_agreed(): void
    {
        $teacher = User::factory()->create();
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-26']);
        TeacherAttendance::factory()->create(['teacher_id' => $teacher->id, 'date' => '2026-10-27']);
        $weights = ['attendance' => 100, 'journal' => 0, 'tasks' => 0, 'simpati' => 0, 'class_administration' => 0, 'follow_up' => 0];

        $noCutoffs = PerformanceSetting::factory()->scoring($weights)->create();
        $this->assertNull($this->run_for($teacher, $noCutoffs)['label']);

        $partial = PerformanceSetting::factory()->scoring($weights)->create(['thresholds' => ['excellent' => 90, 'good' => 75]]);
        $this->assertNull($this->run_for($teacher, $partial)['label']);

        $complete = PerformanceSetting::factory()->scoring($weights)->create(['thresholds' => ['excellent' => 90, 'good' => 75, 'fair' => 60]]);
        $this->assertSame('Sangat Baik', $this->run_for($teacher, $complete)['label']);
    }

    public function test_jp_adds_teaching_hours_and_additional_duties(): void
    {
        $semester = Semester::factory()->active()->create();
        $teacher = User::factory()->create();
        Schedule::factory()->count(3)->create([
            'semester_id' => $semester->id, 'teacher_id' => $teacher->id,
            'subject_id' => Subject::factory()->create(['counts_toward_teaching_load' => true]),
        ]);
        AdditionalDuty::factory()->create(['teacher_id' => $teacher->id, 'jp' => 4]);

        $this->assertSame(['teaching' => 3, 'duties' => 4, 'total' => 7], $this->run_for($teacher)['jp']);
    }

    public function test_responsibilities_say_which_classes_the_teacher_is_tied_to(): void
    {
        $teacher = User::factory()->create();
        $wali = Classroom::factory()->create(['homeroom_teacher_id' => $teacher->id]);
        $pendamping = Classroom::factory()->create(['assistant_teacher_id' => $teacher->id]);

        $responsibilities = $this->run_for($teacher)['responsibilities'];

        $this->assertSame($wali->id, $responsibilities['homeroom']->id);
        $this->assertSame($pendamping->id, $responsibilities['assistant']->id);
        $this->assertEqualsCanonicalizing([$wali->id, $pendamping->id], $responsibilities['classroom_ids']);
    }

    public function test_period_resolution_handles_each_type_and_falls_back_on_bad_input(): void
    {
        [$from, $to] = PerformanceCalculator::periodFor(PerformancePeriod::Week, '2026-10-28', null, null, null, null);
        $this->assertSame(['2026-10-26', '2026-11-01'], [$from->toDateString(), $to->toDateString()]);

        [$from, $to] = PerformanceCalculator::periodFor(PerformancePeriod::Month, null, '2026-02', null, null, null);
        $this->assertSame(['2026-02-01', '2026-02-28'], [$from->toDateString(), $to->toDateString()]);

        [$from, $to] = PerformanceCalculator::periodFor(PerformancePeriod::Month, null, 'sembarang', null, null, null);
        $this->assertSame(['2026-10-01', '2026-10-31'], [$from->toDateString(), $to->toDateString()]);

        $semester = Semester::factory()->active()->create(['starts_on' => '2026-07-13', 'ends_on' => '2026-12-19']);
        [$from, $to] = PerformanceCalculator::periodFor(PerformancePeriod::Semester, null, null, (string) $semester->id, null, null);
        $this->assertSame(['2026-07-13', '2026-12-19'], [$from->toDateString(), $to->toDateString()]);

        [$from, $to] = PerformanceCalculator::periodFor(PerformancePeriod::Range, null, null, null, '2026-10-20', '2026-10-10');
        $this->assertSame(['2026-10-10', '2026-10-20'], [$from->toDateString(), $to->toDateString()]);

        [$from, $to] = PerformanceCalculator::periodFor(PerformancePeriod::Range, null, null, null, 'x', '2026-02-31');
        $this->assertTrue($from->lte($to));
    }

    public function test_every_indicator_is_always_present_in_the_result(): void
    {
        $result = $this->run_for(User::factory()->create());

        $this->assertSame(
            array_map(fn (PerformanceIndicator $i): string => $i->value, PerformanceIndicator::cases()),
            array_keys($result['indicators']),
        );
    }
}
