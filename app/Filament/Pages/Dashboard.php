<?php

namespace App\Filament\Pages;

use App\Enums\CalendarDayStatus;
use App\Enums\Role;
use App\Enums\SchoolDay;
use App\Models\AdditionalDuty;
use App\Models\CalendarDay;
use App\Models\Classroom;
use App\Models\Journal;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\SimpatiCheck;
use App\Models\Substitution;
use App\Models\TaskAssignee;
use App\Models\TeacherAttendance;
use App\Models\TimeSlot;
use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Replaces Filament's default dashboard with a role-aware daily briefing: a leadership
 * view (Kepala Sekolah and designated coordinators) summarising
 * today's attendance/journal/substitution activity and workload, or a teacher view
 * reminding them what's still outstanding today. The full interactive weekly matrix
 * already lives on its own "Matriks Jadwal" page, so this links to it rather than
 * duplicating it.
 */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    protected Width|string|null $maxContentWidth = Width::SevenExtraLarge;

    public function getHeading(): null
    {
        return null;
    }

    public function isPimpinanView(): bool
    {
        $user = auth()->user();

        return $user->isSchoolLeader();
    }

    public function getGreeting(): string
    {
        $hour = Carbon::now()->hour;

        return match (true) {
            $hour < 11 => 'Selamat pagi',
            $hour < 15 => 'Selamat siang',
            $hour < 19 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }

    /**
     * `CalendarDay::statusFor()` only tracks exceptions to the school week, defaulting
     * to Efektif for any date with no override — including weekends, which aren't
     * school days at all. Treat a weekend as Libur here so the dashboard doesn't
     * misleadingly call a Saturday "Efektif".
     */
    public function getTodayStatus(): CalendarDayStatus
    {
        $today = Carbon::today();

        $status = CalendarDay::statusFor($today);

        if (SchoolDay::tryFrom($today->dayOfWeekIso) === null) {
            return $status === CalendarDayStatus::Efektif ? CalendarDayStatus::Libur : $status;
        }

        return $status;
    }

    /**
     * Whether the signed-in user sees the school-wide SIMPATI progress: Waka Kurikulum and
     * the other curriculum managers, the principal, and the extracurricular coordinator.
     */
    public function canSeeSimpati(): bool
    {
        return auth()->user()->canMonitorSimpati();
    }

    /**
     * Today's SIMPATI completion across every class, read from the same checklist the
     * pendamping tick.
     *
     * @return array{required: bool, total: int, filled: int, missing: int, percentage: int, unfilled: Collection<int, Classroom>}
     */
    public function getSimpatiSummary(): array
    {
        return SimpatiCheck::summaryFor(Carbon::today());
    }

    /**
     * @return array{filled: int, possible: int, percentage: int}
     */
    public function getSimpatiMonthSummary(): array
    {
        return SimpatiCheck::monthlySummaryFor(Carbon::today());
    }

    /**
     * Count of today's attendance rows per status, for every non-principal teacher.
     *
     * @return array<string, int>
     */
    public function getAttendanceSummary(): array
    {
        $counts = TeacherAttendance::query()
            ->whereDate('date', Carbon::today())
            ->get()
            ->countBy(fn (TeacherAttendance $attendance): string => $attendance->status->value);

        $expected = User::query()
            ->where('is_active', true)
            ->where(fn (Builder $query): Builder => $query->whereNull('role')->orWhere('role', '!=', Role::KepalaSekolah->value))
            ->count();

        return [
            'hadir' => $counts->get('hadir', 0),
            'pulang_cepat' => $counts->get('pulang_cepat', 0),
            'izin' => $counts->get('izin', 0),
            'sakit' => $counts->get('sakit', 0),
            'cuti' => $counts->get('cuti', 0),
            'alpa' => $counts->get('alpa', 0),
            'belum_mengisi' => max(0, $expected - $counts->sum()),
        ];
    }

    /**
     * How many of today's effective teaching slots already have a journal entry.
     *
     * @return array{filled: int, expected: int}
     */
    public function getJournalSummary(): array
    {
        $semester = Semester::current();
        $today = Carbon::today();

        if (! $semester) {
            return ['filled' => 0, 'expected' => 0];
        }

        // A lesson taught by a team has no teacher to write its journal, so it is not expected.
        $expected = Schedule::query()
            ->where('semester_id', $semester->id)
            ->where('day_of_week', $today->dayOfWeekIso)
            ->whereNotNull('teacher_id')
            ->count();

        $filled = Journal::query()
            ->whereDate('date', $today)
            ->count();

        return ['filled' => $filled, 'expected' => $expected];
    }

    /**
     * Today's substitutions, for the leadership view.
     *
     * @return Collection<int, Substitution>
     */
    public function getTodaysSubstitutions(): Collection
    {
        return Substitution::query()
            ->whereDate('date', Carbon::today())
            ->with(['schedule.classroom', 'schedule.subject', 'schedule.teacher', 'substituteTeacher'])
            ->get();
    }

    /**
     * Every active teacher's total workload (teaching JP + additional-duty JP), highest
     * first.
     *
     * @return list<array{teacher: string, jp: int, teaching_jp: int, duty_jp: int, duties: list<array{title: string, jp: int}>}>
     */
    public function getWorkloadAnalysis(): array
    {
        $semester = Semester::current();

        $teachingJpByTeacher = $semester
            ? Schedule::query()
                ->where('semester_id', $semester->id)
                ->whereNotNull('teacher_id')
                ->with('subject')
                ->get()
                ->groupBy('teacher_id')
                ->map(fn (Collection $schedules): int => $schedules->sum('jp'))
            : collect();

        $dutiesByTeacher = AdditionalDuty::query()->get()->groupBy('teacher_id');
        $dutyJpByTeacher = $dutiesByTeacher->map->sum('jp');

        $teacherIds = $teachingJpByTeacher->keys()->merge($dutyJpByTeacher->keys())->unique();

        $teachers = User::query()->whereKey($teacherIds)->get()->keyBy('id');

        return $teacherIds
            ->map(fn (int $teacherId): array => [
                'teacher' => $teachers->get($teacherId)?->name ?? '—',
                'jp' => $teachingJpByTeacher->get($teacherId, 0) + $dutyJpByTeacher->get($teacherId, 0),
                'teaching_jp' => $teachingJpByTeacher->get($teacherId, 0),
                'duty_jp' => $dutyJpByTeacher->get($teacherId, 0),
                'duties' => $dutiesByTeacher->get($teacherId, collect())
                    ->map(fn (AdditionalDuty $duty): array => ['title' => $duty->title, 'jp' => $duty->jp])
                    ->values()
                    ->all(),
            ])
            ->sortByDesc('jp')
            ->values()
            ->all();
    }

    /**
     * Whether it is late enough in the day (15.00 WIB) that a teacher should be nudged
     * about anything they haven't filled in yet.
     */
    public function isPastDailyReminderTime(): bool
    {
        return Carbon::now()->hour >= 15;
    }

    public function hasFilledAttendanceToday(): bool
    {
        return TeacherAttendance::query()
            ->where('teacher_id', auth()->id())
            ->whereDate('date', Carbon::today())
            ->exists();
    }

    public function hasUnfilledJournalToday(): bool
    {
        $semester = Semester::current();
        $today = Carbon::today();

        if (! $semester) {
            return false;
        }

        $mySlots = Schedule::effectiveForTeacherOnDate(auth()->id(), $today);

        if ($mySlots->isEmpty()) {
            return false;
        }

        $filledCount = Journal::query()
            ->where('teacher_id', auth()->id())
            ->whereDate('date', $today)
            ->whereIn('schedule_id', $mySlots->pluck('id'))
            ->count();

        return $filledCount < $mySlots->count();
    }

    /**
     * Today's substitutions involving the signed-in teacher, either as the one covering
     * or the one being covered.
     *
     * @return Collection<int, Substitution>
     */
    public function getMySubstitutionsToday(): Collection
    {
        return Substitution::query()
            ->whereDate('date', Carbon::today())
            ->where(fn ($query) => $query
                ->where('substitute_teacher_id', auth()->id())
                ->orWhereHas('schedule', fn ($query) => $query->where('teacher_id', auth()->id())))
            ->with(['schedule.classroom', 'schedule.subject', 'schedule.teacher', 'substituteTeacher'])
            ->get();
    }

    /**
     * The signed-in teacher's own scheduled slots today, including a slot that was
     * assigned to a substitute so the teacher can still see the daily context.
     *
     * @return Collection<int, Schedule>
     */
    public function getMyScheduleToday(): Collection
    {
        return Schedule::query()
            ->where('teacher_id', auth()->id())
            ->where('day_of_week', Carbon::today()->dayOfWeekIso)
            ->with(['classroom', 'subject', 'timeSlot'])
            ->orderBy(
                TimeSlot::query()
                    ->select('starts_at')
                    ->whereColumn('time_slots.id', 'schedules.time_slot_id'),
            )
            ->get();
    }

    /**
     * Unfinished assignments for the signed-in teacher, with the nearest deadline
     * shown first on the daily dashboard.
     *
     * @return Collection<int, TaskAssignee>
     */
    public function getMyOpenTasks(): Collection
    {
        return TaskAssignee::query()
            ->where('user_id', auth()->id())
            ->where('is_completed', false)
            ->with('task')
            ->get()
            ->sortBy(fn (TaskAssignee $assignee): string => $assignee->task->due_on->toDateString())
            ->take(3)
            ->values();
    }
}
