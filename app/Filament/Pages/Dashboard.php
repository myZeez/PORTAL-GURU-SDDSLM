<?php

namespace App\Filament\Pages;

use App\Enums\CalendarDayStatus;
use App\Enums\Role;
use App\Enums\SchoolDay;
use App\Models\AdditionalDuty;
use App\Models\CalendarDay;
use App\Models\Journal;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Substitution;
use App\Models\TeacherAttendance;
use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Replaces Filament's default dashboard with a role-aware daily briefing: a leadership
 * view (Kepala Sekolah, Waka Kurikulum, Admin Kurikulum, Koordinator Ekskul) summarising
 * today's attendance/journal/substitution activity and workload, or a teacher view
 * reminding them what's still outstanding today. The full interactive weekly matrix
 * already lives on its own "Matriks Jadwal" page, so this links to it rather than
 * duplicating it.
 */
class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public function isPimpinanView(): bool
    {
        $user = auth()->user();

        return $user->isPrincipal() || $user->isAdministrator() || $user->hasRole(Role::KoordinatorEkskul);
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

        if (SchoolDay::tryFrom($today->dayOfWeekIso) === null) {
            return CalendarDayStatus::Libur;
        }

        return CalendarDay::statusFor($today);
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
            ->whereJsonDoesntContain('roles', Role::KepalaSekolah->value)
            ->count();

        return [
            'hadir' => $counts->get('hadir', 0),
            'izin' => $counts->get('izin', 0),
            'sakit' => $counts->get('sakit', 0),
            'cuti' => $counts->get('cuti', 0),
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

        $expected = Schedule::query()
            ->where('semester_id', $semester->id)
            ->where('day_of_week', $today->dayOfWeekIso)
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
     * @return list<array{teacher: string, jp: int}>
     */
    public function getWorkloadAnalysis(): array
    {
        $semester = Semester::current();

        $teachingJpByTeacher = $semester
            ? Schedule::query()
                ->where('semester_id', $semester->id)
                ->with('subject')
                ->get()
                ->groupBy('teacher_id')
                ->map(fn (Collection $schedules): int => $schedules->sum('jp'))
            : collect();

        $dutyJpByTeacher = AdditionalDuty::query()->get()->groupBy('teacher_id')->map->sum('jp');

        $teacherIds = $teachingJpByTeacher->keys()->merge($dutyJpByTeacher->keys())->unique();

        $teachers = User::query()->whereKey($teacherIds)->get()->keyBy('id');

        return $teacherIds
            ->map(fn (int $teacherId): array => [
                'teacher' => $teachers->get($teacherId)?->name ?? '—',
                'jp' => $teachingJpByTeacher->get($teacherId, 0) + $dutyJpByTeacher->get($teacherId, 0),
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
}
