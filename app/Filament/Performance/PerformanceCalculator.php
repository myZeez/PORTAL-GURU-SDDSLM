<?php

namespace App\Filament\Performance;

use App\Enums\AttendanceStatus;
use App\Enums\CalendarDayStatus;
use App\Enums\PerformanceFollowUpStatus;
use App\Enums\PerformanceIndicator;
use App\Enums\PerformancePeriod;
use App\Enums\SchoolDay;
use App\Models\AdditionalDuty;
use App\Models\CalendarDay;
use App\Models\Classroom;
use App\Models\Journal;
use App\Models\MonitoringSchedule;
use App\Models\PerformanceFollowUp;
use App\Models\PerformanceSetting;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\SimpatiCheck;
use App\Models\Substitution;
use App\Models\Supervision;
use App\Models\TaskAssignee;
use App\Models\TeacherAttendance;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Works out teacher performance for one evaluation period, only from records that exist in
 * the system. Every indicator is a list of "items" (one attendance day, one journal slot,
 * one task, ...) each carrying a credit between 0 and 1; the percentage is the credit earned
 * over the items that are due. Anything not yet due (today's entry still being filled, a
 * task due next week) is listed but not counted, and an indicator with nothing to count is
 * null — "Tidak Berlaku" — rather than zero.
 *
 * All data for all teachers is loaded in a handful of queries up front, so comparing the
 * whole staff does not cost a query per teacher per day.
 *
 * @phpstan-type Item array{date: ?string, label: string, credit: float, counts: bool, state: string, note: ?string}
 * @phpstan-type IndicatorResult array{indicator: PerformanceIndicator, value: ?float, counted: int, full: int, items: list<Item>}
 */
class PerformanceCalculator
{
    private CarbonInterface $from;

    private CarbonInterface $to;

    private CarbonInterface $evalEnd;

    private CarbonInterface $today;

    /** @var Collection<int, Semester> */
    private Collection $semesters;

    public function __construct(CarbonInterface $from, CarbonInterface $to, private readonly PerformanceSetting $settings)
    {
        $this->from = Carbon::parse($from->toDateString());
        $this->to = Carbon::parse($to->toDateString());
        $this->today = Carbon::today();
        $this->evalEnd = $this->to->lt($this->today) ? $this->to : $this->today;
        $this->semesters = Semester::query()->get();
    }

    /**
     * Turn the period the user picked into a concrete start and end date. Anything missing
     * or malformed falls back to the current week, month or semester, so a tampered request
     * can never break the page.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function periodFor(PerformancePeriod $type, ?string $anchor, ?string $month, ?string $semesterId, ?string $from, ?string $to): array
    {
        $today = Carbon::today();

        switch ($type) {
            case PerformancePeriod::Week:
                $day = self::parseDate($anchor) ?? $today;

                return [$day->copy()->startOfWeek(CarbonInterface::MONDAY), $day->copy()->startOfWeek(CarbonInterface::MONDAY)->addDays(6)];

            case PerformancePeriod::Month:
                $start = ($month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? Carbon::createFromFormat('!Y-m', $month) : $today)->startOfMonth();

                return [$start->copy(), $start->copy()->endOfMonth()->startOfDay()];

            case PerformancePeriod::Semester:
                $semester = ($semesterId !== null && ctype_digit($semesterId) ? Semester::query()->find((int) $semesterId) : null) ?? Semester::current();

                if ($semester?->starts_on && $semester->ends_on) {
                    return [Carbon::parse($semester->starts_on->toDateString()), Carbon::parse($semester->ends_on->toDateString())];
                }

                return [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()->startOfDay()];

            case PerformancePeriod::Range:
                $start = self::parseDate($from) ?? $today->copy()->startOfMonth();
                $end = self::parseDate($to) ?? $today;

                return $end->lt($start) ? [$end, $start] : [$start, $end];
        }
    }

    private static function parseDate(?string $value): ?Carbon
    {
        if ($value === null || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $date = Carbon::createFromFormat('!Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $date : null;
    }

    /**
     * @param  Collection<int, User>  $teachers
     * @return array<int, array<string, mixed>> keyed by teacher id
     */
    public function calculate(Collection $teachers): array
    {
        $ids = $teachers->pluck('id')->all();
        $range = [$this->from->toDateString(), $this->evalEnd->toDateString()];
        $hasEvaluableDays = $this->from->lte($this->evalEnd);

        $statuses = CalendarDay::query()
            ->whereBetween('date', $range)
            ->get()
            ->mapWithKeys(fn (CalendarDay $day): array => [$day->date->toDateString() => $day->status]);

        $days = $hasEvaluableDays ? $this->daysWithStatus($statuses) : collect();

        $attendances = TeacherAttendance::query()
            ->whereIn('teacher_id', $ids)
            ->whereBetween('date', $range)
            ->get()
            ->groupBy('teacher_id')
            ->map(fn (Collection $rows): Collection => $rows->keyBy(fn (TeacherAttendance $a): string => $a->date->toDateString()));

        $schedules = Schedule::query()
            ->with(['subject', 'classroom', 'timeSlot'])
            ->whereIn('semester_id', $this->semesters->pluck('id'))
            ->get();
        $schedulesById = $schedules->keyBy('id');
        $schedulesByTeacher = $schedules->groupBy('teacher_id');

        $substitutions = Substitution::query()->whereBetween('date', $range)->get()
            ->groupBy(fn (Substitution $s): string => $s->date->toDateString());

        $journals = Journal::query()
            ->whereIn('teacher_id', $ids)
            ->whereBetween('date', $range)
            ->get()
            ->groupBy('teacher_id')
            ->map(fn (Collection $rows): Collection => $rows->keyBy(fn (Journal $j): string => $j->schedule_id.'|'.$j->date->toDateString()));

        $classrooms = Classroom::query()
            ->whereIn('homeroom_teacher_id', $ids)->orWhereIn('assistant_teacher_id', $ids)
            ->get();

        $simpatiChecks = SimpatiCheck::query()
            ->whereIn('classroom_id', $classrooms->pluck('id'))
            ->whereBetween('date', $range)
            ->get()
            ->groupBy('classroom_id')
            ->map(fn (Collection $rows): Collection => $rows->keyBy(fn (SimpatiCheck $c): string => $c->date->toDateString()));

        $monitorings = MonitoringSchedule::query()
            ->with(['result', 'classroom'])
            ->whereIn('classroom_id', $classrooms->pluck('id'))
            ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->get()
            ->groupBy('classroom_id');

        $assignments = TaskAssignee::query()
            ->with('task')
            ->whereIn('user_id', $ids)
            ->whereHas('task', fn ($query) => $query->whereBetween('due_on', [$this->from->toDateString(), $this->to->toDateString()]))
            ->get()
            ->groupBy('user_id');

        $followUps = PerformanceFollowUp::query()
            ->whereIn('responsible_id', $ids)
            ->whereBetween('target_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->get()
            ->groupBy('responsible_id');

        $supervisionCounts = Supervision::query()
            ->whereIn('teacher_id', $ids)
            ->whereBetween('date', [$this->from->toDateString(), $this->to->toDateString()])
            ->selectRaw('teacher_id, count(*) as total')
            ->groupBy('teacher_id')
            ->pluck('total', 'teacher_id');

        $dutyJp = AdditionalDuty::query()->whereIn('teacher_id', $ids)->get()->groupBy('teacher_id')
            ->map(fn (Collection $rows): int => (int) $rows->sum('jp'));

        $jpSemester = $this->semesterFor($this->evalEnd->lt($this->from) ? $this->from : $this->evalEnd);

        $results = [];

        foreach ($teachers as $teacher) {
            $id = $teacher->id;
            $homeroom = $classrooms->firstWhere('homeroom_teacher_id', $id);
            $assistant = $classrooms->firstWhere('assistant_teacher_id', $id);
            $taught = $schedulesByTeacher->get($id, collect())->where('semester_id', $jpSemester?->id);

            $indicators = [
                PerformanceIndicator::Attendance->value => $this->attendance($days, $attendances->get($id, collect())),
                PerformanceIndicator::Journal->value => $this->journal($days, $id, $schedulesByTeacher->get($id, collect()), $schedulesById, $substitutions, $journals->get($id, collect())),
                PerformanceIndicator::Tasks->value => $this->tasks($assignments->get($id, collect())),
                PerformanceIndicator::Simpati->value => $this->simpati($days, $assistant, $simpatiChecks),
                PerformanceIndicator::ClassAdministration->value => $this->classAdministration(
                    collect([$homeroom, $assistant])->filter()->unique('id'),
                    $monitorings,
                ),
                PerformanceIndicator::FollowUp->value => $this->followUps($followUps->get($id, collect())),
            ];

            $teaching = (int) $taught->sum('jp');
            $duties = (int) $dutyJp->get($id, 0);

            $results[$id] = [
                'teacher' => $teacher,
                'responsibilities' => [
                    'homeroom' => $homeroom,
                    'assistant' => $assistant,
                    'teaches' => $taught->isNotEmpty(),
                    'classroom_ids' => $taught->pluck('classroom_id')->merge([$homeroom?->id, $assistant?->id])->filter()->unique()->values()->all(),
                    'subject_ids' => $taught->pluck('subject_id')->unique()->values()->all(),
                ],
                'indicators' => $indicators,
                'jp' => ['teaching' => $teaching, 'duties' => $duties, 'total' => $teaching + $duties],
                'supervisions' => (int) ($supervisionCounts->get($id, 0)),
                'score' => $this->score($indicators),
            ];

            $results[$id]['label'] = $this->settings->labelFor($results[$id]['score']['value'] ?? null);
        }

        return $results;
    }

    /**
     * The teachers a user may look at: everyone active (except the principal) for those who
     * review performance, otherwise only themselves.
     *
     * @return Collection<int, User>
     */
    public static function visibleTeachers(User $viewer): Collection
    {
        if (! $viewer->canReviewPerformance()) {
            return collect([$viewer]);
        }

        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->reject(fn (User $user): bool => $user->isPrincipal())
            ->values();
    }

    /**
     * Narrow calculated results by teacher, class, subject, kind of responsibility, or by
     * whether an indicator reaches the target.
     *
     * @param  array<int, array<string, mixed>>  $results
     * @param  array{teacher?: ?int, classroom?: ?int, subject?: ?int, responsibility?: ?string, indicator?: ?string, status?: ?string}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function filter(array $results, array $filters): array
    {
        $target = $this->settings->target_percentage;
        $indicator = PerformanceIndicator::tryFrom((string) ($filters['indicator'] ?? ''));

        return array_filter($results, function (array $result) use ($filters, $indicator, $target): bool {
            $responsibilities = $result['responsibilities'];

            if (($filters['teacher'] ?? null) && $result['teacher']->id !== $filters['teacher']) {
                return false;
            }

            if (($filters['classroom'] ?? null) && ! in_array($filters['classroom'], $responsibilities['classroom_ids'], true)) {
                return false;
            }

            if (($filters['subject'] ?? null) && ! in_array($filters['subject'], $responsibilities['subject_ids'], true)) {
                return false;
            }

            $matchesResponsibility = match ($filters['responsibility'] ?? '') {
                'homeroom' => $responsibilities['homeroom'] !== null,
                'assistant' => $responsibilities['assistant'] !== null,
                'subject' => $responsibilities['teaches'],
                default => true,
            };

            if (! $matchesResponsibility) {
                return false;
            }

            if ($indicator === null || ($filters['status'] ?? '') === '') {
                return true;
            }

            $value = $result['indicators'][$indicator->value]['value'];

            return match ($filters['status']) {
                'below' => $value !== null && $value < $target,
                'met' => $value !== null && $value >= $target,
                'unavailable' => $value === null,
                default => true,
            };
        });
    }

    /**
     * One entry per day of the evaluated range with its calendar status, so each rule can
     * be applied without another query.
     *
     * @param  Collection<string, CalendarDayStatus>  $statuses
     * @return Collection<int, array{date: Carbon, key: string, status: CalendarDayStatus}>
     */
    private function daysWithStatus(Collection $statuses): Collection
    {
        $days = collect();

        for ($day = $this->from->copy(); $day->lte($this->evalEnd); $day->addDay()) {
            $key = $day->toDateString();
            $days->push(['date' => $day->copy(), 'key' => $key, 'status' => $statuses->get($key, CalendarDayStatus::Efektif)]);
        }

        return $days;
    }

    /**
     * @param  Collection<int, array{date: Carbon, key: string, status: CalendarDayStatus}>  $days
     * @param  Collection<string, TeacherAttendance>  $records
     * @return IndicatorResult
     */
    private function attendance(Collection $days, Collection $records): array
    {
        $items = [];

        foreach ($days as $day) {
            if (! TeacherAttendance::isFillableOn($day['date'], $day['status'])) {
                continue;
            }

            $record = $records->get($day['key']);

            if (! $record) {
                $items[] = $this->item($day['key'], 'Belum diisi', 0.0, ! $day['date']->isToday(), $day['date']->isToday() ? 'pending' : 'missing');

                continue;
            }

            $credit = $record->status->attendanceWeight() / 100;
            $items[] = $this->item(
                $day['key'],
                $record->status->getLabel(),
                $credit,
                true,
                $credit >= 1 ? 'done' : ($credit > 0 ? 'partial' : 'missing'),
                $record->status === AttendanceStatus::PulangCepat ? $record->early_leave_reason : null,
            );
        }

        return $this->indicator(PerformanceIndicator::Attendance, $items);
    }

    /**
     * @param  Collection<int, array{date: Carbon, key: string, status: CalendarDayStatus}>  $days
     * @param  Collection<int, Schedule>  $ownSchedules
     * @param  Collection<int, Schedule>  $schedulesById
     * @param  Collection<string, Collection<int, Substitution>>  $substitutions
     * @param  Collection<string, Journal>  $journals
     * @return IndicatorResult
     */
    private function journal(Collection $days, int $teacherId, Collection $ownSchedules, Collection $schedulesById, Collection $substitutions, Collection $journals): array
    {
        $items = [];

        foreach ($days as $day) {
            if (! Journal::isFillableOn($day['date'], $day['status'])) {
                continue;
            }

            $semester = $this->semesterFor($day['date']);
            $subsToday = $substitutions->get($day['key'], collect());
            $awayIds = $subsToday->pluck('schedule_id');

            $slots = $ownSchedules
                ->where('semester_id', $semester?->id)
                ->where('day_of_week', SchoolDay::tryFrom($day['date']->dayOfWeekIso))
                ->reject(fn (Schedule $schedule): bool => $awayIds->contains($schedule->id));

            $covering = $subsToday
                ->where('substitute_teacher_id', $teacherId)
                ->map(fn (Substitution $substitution): ?Schedule => $schedulesById->get($substitution->schedule_id))
                ->filter();

            foreach ($slots->concat($covering) as $schedule) {
                $journal = $journals->get($schedule->id.'|'.$day['key']);
                $label = "{$schedule->subject->name} — {$schedule->classroom->code} ({$schedule->timeSlot->label})";

                if ($journal) {
                    $late = $journal->created_at->toDateString() > $day['key'];
                    $items[] = $this->item($day['key'], $label, 1.0, true, 'done', $late ? 'Terlambat diisi' : null);
                } elseif ($day['date']->isToday()) {
                    $items[] = $this->item($day['key'], $label, 0.0, false, 'pending', 'Hari ini, masih bisa diisi');
                } else {
                    $items[] = $this->item($day['key'], $label, 0.0, true, 'missing', 'Jurnal belum diisi');
                }
            }
        }

        return $this->indicator(PerformanceIndicator::Journal, $items);
    }

    /**
     * @param  Collection<int, TaskAssignee>  $assignments
     * @return IndicatorResult
     */
    private function tasks(Collection $assignments): array
    {
        $items = $assignments
            ->sortBy(fn (TaskAssignee $assignee): string => $assignee->task->due_on->toDateString())
            ->map(function (TaskAssignee $assignee): array {
                $due = $assignee->task->due_on;
                $isDue = $due->lte($this->today) || $assignee->is_completed;

                return $this->item(
                    $due->toDateString(),
                    $assignee->task->title,
                    $assignee->is_completed ? 1.0 : 0.0,
                    $isDue,
                    $assignee->is_completed ? 'done' : ($isDue ? 'missing' : 'pending'),
                    $assignee->is_completed ? 'Selesai '.$assignee->completed_at?->translatedFormat('d M Y') : ($isDue ? 'Belum diselesaikan' : 'Belum jatuh tempo'),
                );
            })
            ->values()
            ->all();

        return $this->indicator(PerformanceIndicator::Tasks, $items);
    }

    /**
     * @param  Collection<int, array{date: Carbon, key: string, status: CalendarDayStatus}>  $days
     * @param  Collection<int, Collection<string, SimpatiCheck>>  $checks
     * @return IndicatorResult
     */
    private function simpati(Collection $days, ?Classroom $assistant, Collection $checks): array
    {
        if (! $assistant) {
            return $this->indicator(PerformanceIndicator::Simpati, []);
        }

        $items = [];

        foreach ($days as $day) {
            if (! SimpatiCheck::isRequiredFor($day['date'], $day['status'])) {
                continue;
            }

            $filled = (bool) $checks->get($assistant->id, collect())->get($day['key'])?->is_filled;

            $items[] = $filled
                ? $this->item($day['key'], "SIMPATI {$assistant->code} sudah diisi", 1.0, true, 'done')
                : $this->item($day['key'], "SIMPATI {$assistant->code} belum diisi", 0.0, ! $day['date']->isToday(), $day['date']->isToday() ? 'pending' : 'missing');
        }

        return $this->indicator(PerformanceIndicator::Simpati, $items);
    }

    /**
     * @param  Collection<int, Classroom>  $classrooms
     * @param  Collection<int, Collection<int, MonitoringSchedule>>  $monitorings
     * @return IndicatorResult
     */
    private function classAdministration(Collection $classrooms, Collection $monitorings): array
    {
        $items = [];

        foreach ($classrooms as $classroom) {
            foreach ($monitorings->get($classroom->id, collect())->sortBy(fn (MonitoringSchedule $m): string => $m->date->toDateString()) as $visit) {
                $result = $visit->result;

                if (! $result || $result->score === null) {
                    $items[] = $this->item($visit->date->toDateString(), "Monitoring {$classroom->code}", 0.0, false, 'pending', 'Hasil kunjungan belum diisi');

                    continue;
                }

                $credit = $result->score / 100;
                $items[] = $this->item(
                    $visit->date->toDateString(),
                    "Monitoring {$classroom->code} — skor {$result->score}%",
                    $credit,
                    true,
                    $credit >= 0.9 ? 'done' : ($credit >= 0.75 ? 'partial' : 'missing'),
                    $result->status?->getLabel(),
                );
            }
        }

        return $this->indicator(PerformanceIndicator::ClassAdministration, $items);
    }

    /**
     * @param  Collection<int, PerformanceFollowUp>  $followUps
     * @return IndicatorResult
     */
    private function followUps(Collection $followUps): array
    {
        $items = $followUps
            ->sortBy(fn (PerformanceFollowUp $f): string => $f->target_date->toDateString())
            ->map(function (PerformanceFollowUp $followUp): array {
                $done = $followUp->status === PerformanceFollowUpStatus::Done;
                $isDue = $followUp->target_date->lte($this->today) || $done;

                return $this->item(
                    $followUp->target_date->toDateString(),
                    Str::limit($followUp->finding, 80),
                    $done ? 1.0 : 0.0,
                    $isDue,
                    $done ? 'done' : ($isDue ? 'missing' : 'pending'),
                    $followUp->status->getLabel(),
                );
            })
            ->values()
            ->all();

        return $this->indicator(PerformanceIndicator::FollowUp, $items);
    }

    /**
     * The combined score, only for the indicators that apply to this teacher. Their
     * weights are rescaled to total 100%, and the breakdown says exactly how.
     *
     * @param  array<string, IndicatorResult>  $indicators
     * @return array{value: int, breakdown: array<string, array{weight: float, value: float}>}|null
     */
    private function score(array $indicators): ?array
    {
        if (! $this->settings->canScore()) {
            return null;
        }

        $weights = $this->settings->weightsByIndicator();

        $applicable = collect($indicators)
            ->filter(fn (array $result, string $key): bool => $result['value'] !== null && ($weights[$key] ?? 0) > 0);

        $totalWeight = $applicable->sum(fn (array $result, string $key): int => $weights[$key]);

        if ($totalWeight === 0) {
            return null;
        }

        return [
            'value' => (int) round($applicable->sum(fn (array $result, string $key): float => $result['value'] * $weights[$key]) / $totalWeight),
            'breakdown' => $applicable->map(fn (array $result, string $key): array => [
                'weight' => round($weights[$key] / $totalWeight * 100, 1),
                'value' => $result['value'],
            ])->all(),
        ];
    }

    /**
     * @param  list<Item>  $items
     * @return IndicatorResult
     */
    private function indicator(PerformanceIndicator $indicator, array $items): array
    {
        $counted = collect($items)->where('counts', true);

        return [
            'indicator' => $indicator,
            'value' => $counted->isEmpty() ? null : round($counted->sum('credit') / $counted->count() * 100, 1),
            'counted' => $counted->count(),
            'full' => $counted->where('credit', '>=', 1.0)->count(),
            'items' => $items,
        ];
    }

    /**
     * @return Item
     */
    private function item(?string $date, string $label, float $credit, bool $counts, string $state, ?string $note = null): array
    {
        return ['date' => $date, 'label' => $label, 'credit' => $credit, 'counts' => $counts, 'state' => $state, 'note' => $note];
    }

    /**
     * The semester a date falls in, falling back to the active one when semesters have no
     * dates filled in.
     */
    private function semesterFor(CarbonInterface $date): ?Semester
    {
        return $this->semesters->first(fn (Semester $semester): bool => $semester->starts_on !== null
            && $semester->ends_on !== null
            && $date->betweenIncluded($semester->starts_on, $semester->ends_on))
            ?? $this->semesters->firstWhere('is_active', true);
    }
}
