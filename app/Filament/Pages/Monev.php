<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
use App\Models\Assessment;
use App\Models\Journal;
use App\Models\Schedule;
use App\Models\Supervision;
use App\Models\TeacherAttendance;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use UnitEnum;

/**
 * Monitoring & evaluasi: a read-only rollup of journal, attendance, assessment, and
 * supervision compliance. Leadership (administrators + principal) see every teacher;
 * everyone else sees only their own row — see {@see self::isLeadership()}.
 */
class Monev extends Page
{
    protected string $view = 'filament.pages.monev';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Monev';

    protected static ?string $title = 'Monitoring & Evaluasi';

    public string $month;

    #[Locked]
    public ?int $selectedTeacherId = null;

    /**
     * @var Collection<int, Carbon>|null
     */
    private ?Collection $fillableAttendanceDates = null;

    public function mount(): void
    {
        $this->month = now()->format('Y-m');

        if (! $this->isLeadership()) {
            $this->selectedTeacherId = auth()->id();
        }
    }

    public function isLeadership(): bool
    {
        return auth()->user()->isAdministrator() || auth()->user()->isPrincipal();
    }

    public function selectTeacher(int $teacherId): void
    {
        abort_unless($this->isLeadership() || $teacherId === auth()->id(), 403);

        $this->selectedTeacherId = $teacherId;
    }

    public function updatedMonth(): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month)) {
            $this->month = now()->format('Y-m');
        }

        $this->fillableAttendanceDates = null;
    }

    /**
     * Every teacher (leadership view) or just the signed-in user (self view), each with
     * their compliance summary for the selected month.
     *
     * @return Collection<int, array{teacher: User, status: string, attendance_percentage: int, has_assessment: bool, supervision_count: int, journal_missing_today: bool}>
     */
    public function getTeacherRows(): Collection
    {
        $teachers = $this->isLeadership()
            ? User::query()->where('is_active', true)->get()->reject(fn (User $user): bool => $user->isPrincipal())->values()
            : collect([auth()->user()]);

        return $teachers->map(fn (User $teacher): array => $this->summaryFor($teacher))->values();
    }

    public function getSelectedTeacher(): ?User
    {
        if (! $this->selectedTeacherId) {
            return null;
        }

        if (! $this->isLeadership() && $this->selectedTeacherId !== auth()->id()) {
            return null;
        }

        return User::find($this->selectedTeacherId);
    }

    /**
     * The last 4 school weeks' journal completeness for the selected teacher: how many
     * required entries were filed on time, late, or not at all.
     *
     * @return Collection<int, array{label: string, tepat_waktu: int, terlambat: int, kosong: int}>
     */
    public function getJournalWeeks(): Collection
    {
        $teacher = $this->getSelectedTeacher();

        if (! $teacher) {
            return collect();
        }

        $today = Carbon::today();

        return collect(range(3, 0))->map(function (int $weeksAgo) use ($teacher, $today): array {
            $weekStart = $today->copy()->subWeeks($weeksAgo)->startOfWeek(Carbon::MONDAY);
            $weekEnd = $weekStart->copy()->addDays(4); // Friday

            $tepatWaktu = 0;
            $terlambat = 0;
            $kosong = 0;

            for ($day = $weekStart->copy(); $day->lte($weekEnd) && $day->lte($today); $day->addDay()) {
                if (! Journal::isFillableDate($day)) {
                    continue;
                }

                foreach (Schedule::effectiveForTeacherOnDate($teacher->id, $day) as $schedule) {
                    $journal = Journal::where('schedule_id', $schedule->id)->whereDate('date', $day)->first();

                    if (! $journal) {
                        $kosong++;
                    } elseif ($journal->created_at->toDateString() > $day->toDateString()) {
                        $terlambat++;
                    } else {
                        $tepatWaktu++;
                    }
                }
            }

            return [
                'label' => "{$weekStart->translatedFormat('d M')}–{$weekEnd->translatedFormat('d M')}",
                'tepat_waktu' => $tepatWaktu,
                'terlambat' => $terlambat,
                'kosong' => $kosong,
            ];
        })->values();
    }

    /**
     * @return array{teacher: User, status: string, attendance_percentage: int, has_assessment: bool, supervision_count: int, journal_missing_today: bool}
     */
    private function summaryFor(User $teacher): array
    {
        $monthStart = Carbon::parse("{$this->month}-01")->startOfMonth();
        $monthEnd = $monthStart->copy()->endOfMonth();
        [$attendanceStart, $attendanceEnd] = $this->attendanceRange($monthStart, $monthEnd);

        $attendancePercentage = $this->attendancePercentage($teacher, $attendanceStart, $attendanceEnd);
        $hasAssessment = Assessment::query()
            ->where('teacher_id', $teacher->id)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->exists();
        $supervisionCount = Supervision::query()
            ->where('teacher_id', $teacher->id)
            ->whereBetween('date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->count();
        $journalMissingToday = $this->journalMissingToday($teacher);

        $status = match (true) {
            $journalMissingToday || ! $hasAssessment => 'merah',
            $attendancePercentage < 80 => 'kuning',
            default => 'hijau',
        };

        return [
            'teacher' => $teacher,
            'status' => $status,
            'attendance_percentage' => $attendancePercentage,
            'has_assessment' => $hasAssessment,
            'supervision_count' => $supervisionCount,
            'journal_missing_today' => $journalMissingToday,
        ];
    }

    private function attendancePercentage(User $teacher, Carbon $monthStart, Carbon $monthEnd): int
    {
        $fillableDates = $this->fillableAttendanceDates($monthStart, $monthEnd);

        if ($fillableDates->isEmpty()) {
            return 100;
        }

        $hadirCount = TeacherAttendance::query()
            ->where('teacher_id', $teacher->id)
            ->whereIn('date', $fillableDates->map(fn (Carbon $date): string => $date->toDateString()))
            ->where('status', AttendanceStatus::Hadir)
            ->count();

        return (int) round($hadirCount / $fillableDates->count() * 100);
    }

    private function journalMissingToday(User $teacher): bool
    {
        $today = Carbon::today();

        if (! Journal::isFillableDate($today)) {
            return false;
        }

        $slots = Schedule::effectiveForTeacherOnDate($teacher->id, $today);

        if ($slots->isEmpty()) {
            return false;
        }

        $filled = Journal::whereIn('schedule_id', $slots->pluck('id'))->whereDate('date', $today)->count();

        return $filled < $slots->count();
    }

    /**
     * Clamp the selected month to "elapsed days only": attendance can't yet exist for
     * days still in the future, so a month that hasn't started (or hasn't finished) only
     * counts the portion up to today.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function attendanceRange(Carbon $monthStart, Carbon $monthEnd): array
    {
        $today = Carbon::today();

        if ($monthStart->gt($today)) {
            return [$monthStart, $monthStart->copy()->subDay()];
        }

        return [$monthStart, $monthEnd->gt($today) ? $today->copy() : $monthEnd];
    }

    /**
     * The days within the range that require attendance, computed once per request and
     * shared across every teacher's summary.
     *
     * @return Collection<int, Carbon>
     */
    private function fillableAttendanceDates(Carbon $start, Carbon $end): Collection
    {
        return $this->fillableAttendanceDates ??= $this->datesInRange($start, $end, fn (Carbon $date): bool => TeacherAttendance::isFillableDate($date));
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function datesInRange(Carbon $start, Carbon $end, Closure $isFillable): Collection
    {
        $dates = collect();

        if ($start->gt($end)) {
            return $dates;
        }

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($isFillable($day)) {
                $dates->push($day->copy());
            }
        }

        return $dates;
    }
}
