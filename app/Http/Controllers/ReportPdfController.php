<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\PerformanceIndicator;
use App\Enums\PerformancePeriod;
use App\Filament\Performance\PerformanceCalculator;
use App\Models\Assessment;
use App\Models\Journal;
use App\Models\MonitoringSchedule;
use App\Models\PerformanceFollowUp;
use App\Models\PerformanceSetting;
use App\Models\PerformanceSnapshot;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Substitution;
use App\Models\Supervision;
use App\Models\TeacherAttendance;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReportPdfController extends Controller
{
    public function attendances(Request $request): Response
    {
        Gate::authorize('viewAny', TeacherAttendance::class);

        $month = $this->month($request);
        $days = collect(range(1, $month->daysInMonth))
            ->map(fn (int $day): Carbon => $month->copy()->day($day));
        $nonEffectiveDateStrings = $days
            ->filter(fn (Carbon $date): bool => ! TeacherAttendance::isFillableDate($date))
            ->map(fn (Carbon $date): string => $date->toDateString());
        [$attendanceStart, $attendanceEnd] = $this->attendanceRange($month);
        $fillableDates = $this->fillableAttendanceDates($attendanceStart, $attendanceEnd);
        $attendances = TeacherAttendance::query()
            ->with('teacher')
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('teacher_id')
            ->get();
        $attendancesByTeacher = $attendances
            ->groupBy('teacher_id')
            ->map(fn (Collection $teacherAttendances): Collection => $teacherAttendances->keyBy(fn (TeacherAttendance $attendance): string => $attendance->date->toDateString()));
        $teachers = User::query()
            ->where('is_active', true)
            ->get()
            ->reject(fn (User $teacher): bool => $teacher->isPrincipal())
            ->values();
        $rows = $teachers->map(function (User $teacher) use ($attendancesByTeacher, $days, $fillableDates): array {
            /** @var Collection<string, TeacherAttendance> $teacherAttendances */
            $teacherAttendances = $attendancesByTeacher->get($teacher->id, collect());
            $summary = collect(AttendanceStatus::cases())
                ->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => 0])
                ->all();
            $attendanceScore = 0;

            foreach ($fillableDates as $date) {
                $attendance = $teacherAttendances->get($date->toDateString());

                if (! $attendance) {
                    continue;
                }

                $summary[$attendance->status->value]++;
                $attendanceScore += $attendance->status->attendanceWeight();
            }

            return [
                'teacher' => $teacher,
                'dailyStatuses' => $days->mapWithKeys(function (Carbon $date) use ($teacherAttendances): array {
                    $dateString = $date->toDateString();

                    return [$dateString => $teacherAttendances->get($dateString)?->status];
                }),
                'summary' => $summary,
                'attendance_percentage' => $fillableDates->isEmpty() ? 100 : (int) round($attendanceScore / $fillableDates->count()),
            ];
        });

        return Pdf::loadView('reports.attendances', [
            'month' => $month,
            'semester' => $this->semester($request, $month, $month->copy()->endOfMonth(), 'month'),
            'days' => $days,
            'rows' => $rows,
            'fillableDateStrings' => $fillableDates->map(fn (Carbon $date): string => $date->toDateString()),
            'nonEffectiveDateStrings' => $nonEffectiveDateStrings,
        ])->setPaper('a3', 'landscape')->download("rekap-absensi-{$month->format('Y-m')}.pdf");
    }

    public function journals(Request $request): Response
    {
        Gate::authorize('viewAny', Journal::class);

        $date = $this->date($request);
        $journals = Journal::query()
            ->with(['teacher', 'schedule.classroom', 'schedule.subject', 'schedule.timeSlot'])
            ->whereDate('date', $date)
            ->orderBy('schedule_id')
            ->get();

        $semester = $this->semester($request, $date, $date, 'date');

        return Pdf::loadView('reports.journals', compact('journals', 'date', 'semester'))
            ->setPaper('a4', 'landscape')
            ->download("rekap-jurnal-{$date->toDateString()}.pdf");
    }

    public function substitutions(Request $request): Response
    {
        Gate::authorize('viewAny', Substitution::class);

        $date = $this->date($request);
        $substitutions = Substitution::query()
            ->with(['schedule.classroom', 'schedule.subject', 'schedule.teacher', 'schedule.timeSlot', 'substituteTeacher'])
            ->whereDate('date', $date)
            ->orderBy('schedule_id')
            ->get();

        $semester = $this->semester($request, $date, $date, 'date');

        return Pdf::loadView('reports.substitutions', compact('substitutions', 'date', 'semester'))
            ->setPaper('a4', 'landscape')
            ->download("penggantian-guru-{$date->toDateString()}.pdf");
    }

    public function monitoring(Request $request): Response
    {
        Gate::authorize('viewAny', MonitoringSchedule::class);

        $month = $this->month($request);
        $schedules = MonitoringSchedule::query()
            ->with(['classroom', 'result'])
            ->whereBetween('date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->orderBy('date')
            ->get();

        $semester = $this->semester($request, $month, $month->copy()->endOfMonth(), 'month');

        return Pdf::loadView('reports.monitoring', compact('schedules', 'month', 'semester'))
            ->setPaper('a4', 'landscape')
            ->download("monitoring-kelas-{$month->format('Y-m')}.pdf");
    }

    public function monev(Request $request): Response
    {
        abort_unless($request->user()?->canReviewMonitoring(), 403);

        $month = $this->month($request);
        $teachers = User::query()
            ->where('is_active', true)
            ->get()
            ->reject(fn (User $teacher): bool => $teacher->isPrincipal())
            ->values();
        [$attendanceStart, $attendanceEnd] = $this->attendanceRange($month);
        $fillableDates = $this->fillableAttendanceDates($attendanceStart, $attendanceEnd);

        $rows = $teachers->map(fn (User $teacher): array => $this->monevSummary($teacher, $month, $fillableDates));

        $semester = $this->semester($request, $month, $month->copy()->endOfMonth(), 'month');

        return Pdf::loadView('reports.monev', compact('month', 'rows', 'semester'))
            ->setPaper('a4', 'landscape')
            ->download("monev-{$month->format('Y-m')}.pdf");
    }

    /**
     * Teacher performance as a PDF: one teacher in full (their own, or anyone's for those
     * who review performance), or a comparison table of everyone the viewer may see.
     */
    public function performance(Request $request): Response
    {
        $viewer = $request->user();

        $validated = $request->validate([
            'period' => ['nullable', Rule::enum(PerformancePeriod::class)],
            'anchor' => ['nullable', 'date_format:Y-m-d'],
            'month' => ['nullable', 'regex:/^\\d{4}-(0[1-9]|1[0-2])$/'],
            'semester' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'teacher' => ['nullable', 'integer'],
            'classroom' => ['nullable', 'integer'],
            'subject' => ['nullable', 'integer'],
            'responsibility' => ['nullable', 'in:homeroom,assistant,subject'],
            'indicator' => ['nullable', Rule::enum(PerformanceIndicator::class)],
            'status' => ['nullable', 'in:below,met,unavailable'],
        ]);

        $type = PerformancePeriod::tryFrom($validated['period'] ?? '') ?? PerformancePeriod::Month;
        [$from, $to] = PerformanceCalculator::periodFor(
            $type,
            $validated['anchor'] ?? null,
            $validated['month'] ?? null,
            isset($validated['semester']) ? (string) $validated['semester'] : null,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
        );

        $settings = PerformanceSetting::current();
        $calculator = new PerformanceCalculator($from, $to, $settings);
        $teachers = PerformanceCalculator::visibleTeachers($viewer);
        $teacherId = isset($validated['teacher']) ? (int) $validated['teacher'] : null;

        abort_if($teacherId !== null && ! $teachers->contains('id', $teacherId), 403);

        $results = $calculator->calculate($teachers);
        $period = $from->translatedFormat('d F Y').' – '.$to->translatedFormat('d F Y');

        if ($teacherId !== null) {
            $result = $results[$teacherId];

            return Pdf::loadView('reports.performance-teacher', [
                'result' => $result,
                'settings' => $settings,
                'periodLabel' => $period,
                'periodType' => $type,
                'from' => $from,
                'to' => $to,
                'followUps' => PerformanceFollowUp::query()->with('responsible')->where('teacher_id', $teacherId)->orderBy('target_date')->get(),
                'history' => PerformanceSnapshot::query()->where('teacher_id', $teacherId)->orderByDesc('period_end')->limit(12)->get(),
                'note' => PerformanceSnapshot::query()->where('teacher_id', $teacherId)->whereDate('period_start', $from->toDateString())->whereDate('period_end', $to->toDateString())->value('notes'),
            ])->setPaper('a4', 'portrait')->download("performance-{$result['teacher']->code}-{$from->toDateString()}.pdf");
        }

        $rows = $calculator->filter($results, [
            'classroom' => $validated['classroom'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'responsibility' => $validated['responsibility'] ?? null,
            'indicator' => $validated['indicator'] ?? null,
            'status' => $validated['status'] ?? null,
        ]);

        return Pdf::loadView('reports.performance-summary', [
            'rows' => collect($rows)->sortBy(fn (array $row): string => $row['teacher']->name)->values(),
            'settings' => $settings,
            'periodLabel' => $period,
        ])->setPaper('a4', 'landscape')->download("performance-guru-{$from->toDateString()}.pdf");
    }

    /**
     * The semester a report belongs to: the one asked for, which the period must overlap, or
     * else whichever semester the period starts in (none when the school has not defined one).
     */
    private function semester(Request $request, Carbon $from, Carbon $to, string $periodField): ?Semester
    {
        $validated = $request->validate([
            'semester' => ['nullable', 'integer', Rule::exists('semesters', 'id')],
        ]);

        if (! isset($validated['semester'])) {
            return Semester::containing($from);
        }

        $semester = Semester::query()->findOrFail($validated['semester']);

        if (! $semester->starts_on || $semester->starts_on->gt($to) || $semester->effectiveEndsOn()->lt($from)) {
            throw ValidationException::withMessages([$periodField => 'Periode yang dipilih berada di luar rentang semester.']);
        }

        return $semester;
    }

    private function date(Request $request): Carbon
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return Carbon::createFromFormat('Y-m-d', $validated['date'] ?? now()->toDateString());
    }

    private function month(Request $request): Carbon
    {
        $validated = $request->validate([
            'month' => ['nullable', 'regex:/^\\d{4}-(0[1-9]|1[0-2])$/'],
        ]);

        return Carbon::createFromFormat('Y-m', $validated['month'] ?? now()->format('Y-m'))->startOfMonth();
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function attendanceRange(Carbon $month): array
    {
        $today = Carbon::today();
        $monthEnd = $month->copy()->endOfMonth();

        if ($month->gt($today)) {
            return [$month, $month->copy()->subDay()];
        }

        return [$month, $monthEnd->gt($today) ? $today : $monthEnd];
    }

    /**
     * @return Collection<int, Carbon>
     */
    private function fillableAttendanceDates(Carbon $start, Carbon $end): Collection
    {
        $dates = collect();

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if (TeacherAttendance::isFillableDate($date)) {
                $dates->push($date->copy());
            }
        }

        return $dates;
    }

    /**
     * @param  Collection<int, Carbon>  $fillableDates
     * @return array{teacher: User, attendance_percentage: int, has_assessment: bool, supervision_count: int, journal_missing_today: bool, status: string}
     */
    private function monevSummary(User $teacher, Carbon $month, Collection $fillableDates): array
    {
        $dateStrings = $fillableDates->map(fn (Carbon $date): string => $date->toDateString());
        $attendanceScore = TeacherAttendance::query()
            ->where('teacher_id', $teacher->id)
            ->whereIn('date', $dateStrings)
            ->get(['status'])
            ->sum(fn (TeacherAttendance $attendance): int => $attendance->status->attendanceWeight());
        $attendancePercentage = $fillableDates->isEmpty() ? 100 : (int) round($attendanceScore / $fillableDates->count());
        $monthEnd = $month->copy()->endOfMonth();
        $hasAssessment = Assessment::query()
            ->where('teacher_id', $teacher->id)
            ->whereBetween('date', [$month->toDateString(), $monthEnd->toDateString()])
            ->exists();
        $supervisionCount = Supervision::query()
            ->where('teacher_id', $teacher->id)
            ->whereBetween('date', [$month->toDateString(), $monthEnd->toDateString()])
            ->count();
        $journalMissingToday = $this->journalMissingToday($teacher);

        return [
            'teacher' => $teacher,
            'attendance_percentage' => $attendancePercentage,
            'has_assessment' => $hasAssessment,
            'supervision_count' => $supervisionCount,
            'journal_missing_today' => $journalMissingToday,
            'status' => match (true) {
                $journalMissingToday || ! $hasAssessment => 'Perlu perhatian',
                $attendancePercentage < 80 => 'Perlu perhatian',
                default => 'Baik',
            },
        ];
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

        return Journal::query()
            ->whereIn('schedule_id', $slots->pluck('id'))
            ->whereDate('date', $today)
            ->count() < $slots->count();
    }
}
