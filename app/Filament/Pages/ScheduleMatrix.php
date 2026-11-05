<?php

namespace App\Filament\Pages;

use App\Enums\SchoolDay;
use App\Models\Classroom;
use App\Models\DailyRoutine;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * The whole-school weekly timetable, read from the same `schedules` rows that Jadwal
 * Pelajaran edits and Jadwal Saya reads — nothing is stored separately, so a change to a
 * schedule shows up here on the next render.
 *
 * Three perspectives: every class on one weekday ("all"), one class across the week
 * ("class"), and one teacher across the week ("teacher", leadership only, since it shows
 * a colleague's personal timetable). Whole-school routines (`daily_routines`) render as
 * bands. Leadership also sees clashes (a teacher or class booked twice in one slot, or a
 * lesson placed on top of a routine) and empty slots flagged.
 */
class ScheduleMatrix extends Page
{
    public const VIEW_ALL = 'all';

    public const VIEW_CLASS = 'class';

    public const VIEW_TEACHER = 'teacher';

    protected string $view = 'filament.pages.schedule-matrix';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Matriks Jadwal';

    protected static ?string $title = 'Matriks Jadwal';

    public string $matrixView = self::VIEW_ALL;

    /**
     * 1–5 for one weekday; 0 means every weekday (class and teacher perspectives only).
     */
    public int $day;

    public string $semesterId = '';

    public string $classroomId = '';

    public string $teacherId = '';

    public string $subjectId = '';

    /**
     * @var Collection<int, Schedule>|null
     */
    private ?Collection $semesterSchedules = null;

    /**
     * @var array<string, list<string>>|null
     */
    private ?array $clashes = null;

    public function mount(): void
    {
        $this->day = SchoolDay::tryFrom(now()->dayOfWeekIso)?->value ?? SchoolDay::Senin->value;
        $this->semesterId = (string) (Semester::current()?->id ?? '');
    }

    public function selectDay(int $day): void
    {
        $this->day = $day === 0 && $this->matrixView !== self::VIEW_ALL
            ? 0
            : (SchoolDay::tryFrom($day)?->value ?? $this->day);
    }

    public function setMatrixView(string $view): void
    {
        $allowed = [self::VIEW_ALL, self::VIEW_CLASS];

        if (auth()->user()->isSchoolLeader()) {
            $allowed[] = self::VIEW_TEACHER;
        }

        $this->matrixView = in_array($view, $allowed, true) ? $view : self::VIEW_ALL;

        if ($this->matrixView === self::VIEW_ALL && $this->day === 0) {
            $this->day = SchoolDay::tryFrom(now()->dayOfWeekIso)?->value ?? SchoolDay::Senin->value;
        }

        if ($this->matrixView === self::VIEW_CLASS && $this->selectedClassroom() === null) {
            $this->classroomId = (string) ($this->getClassrooms()->first()?->id ?? '');
        }

        if ($this->matrixView === self::VIEW_TEACHER && $this->selectedTeacher() === null) {
            $this->teacherId = (string) ($this->getTeachers()->first()?->id ?? '');
        }
    }

    public function resetFilters(): void
    {
        $this->classroomId = '';
        $this->teacherId = '';
        $this->subjectId = '';

        if ($this->matrixView === self::VIEW_CLASS) {
            $this->classroomId = (string) ($this->getClassrooms()->first()?->id ?? '');
        } elseif ($this->matrixView === self::VIEW_TEACHER) {
            $this->teacherId = (string) ($this->getTeachers()->first()?->id ?? '');
        }
    }

    public function canViewTeacherPerspective(): bool
    {
        return auth()->user()->isSchoolLeader();
    }

    public function canSeeClashes(): bool
    {
        return auth()->user()->isSchoolLeader();
    }

    public function getSemester(): ?Semester
    {
        $chosen = ctype_digit($this->semesterId) ? Semester::query()->find((int) $this->semesterId) : null;

        return $chosen
            ?? Semester::current()
            ?? Semester::query()->orderByDesc('academic_year')->orderByDesc('term')->first();
    }

    /**
     * @return Collection<int, Semester>
     */
    public function getSemesters(): Collection
    {
        return Semester::query()->orderByDesc('academic_year')->orderByDesc('term')->get();
    }

    /**
     * @return Collection<int, Classroom>
     */
    public function getClassrooms(): Collection
    {
        return Classroom::query()->orderBy('grade')->orderBy('code')->get();
    }

    /**
     * Teachers who actually teach something in the chosen semester.
     *
     * @return Collection<int, User>
     */
    public function getTeachers(): Collection
    {
        $ids = $this->schedules()->pluck('teacher_id')->filter()->unique();

        return User::query()->whereKey($ids)->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Subject>
     */
    public function getSubjects(): Collection
    {
        return Subject::query()->orderBy('name')->get();
    }

    /**
     * The grid for the current perspective and filters: columns across the top, one row
     * per time slot.
     *
     * @return array{columns: list<array{key: string, label: string, sub: ?string}>, rows: list<array{timeSlot: TimeSlot, band: ?string, cells: array<string, array<string, mixed>|null>}>}
     */
    public function getGrid(): array
    {
        $routines = DailyRoutine::query()->get()->groupBy('day_of_week');
        $timeSlots = TimeSlot::query()->orderBy('starts_at')->get();

        return match ($this->matrixView) {
            self::VIEW_CLASS, self::VIEW_TEACHER => $this->weekGrid($timeSlots, $routines),
            default => $this->dayGrid($timeSlots, $routines),
        };
    }

    /**
     * How many empty lesson slots and clashes the given grid contains.
     *
     * @param  array{columns: list<array{key: string, label: string, sub: ?string}>, rows: list<array{timeSlot: TimeSlot, band: ?string, cells: array<string, array<string, mixed>|null>}>}  $grid
     * @return array{empty: int, clashes: int}
     */
    public function summarize(array $grid): array
    {
        $cells = collect($grid['rows'])->flatMap(fn (array $row): array => $row['cells']);

        return [
            'empty' => $cells->filter(fn (?array $cell): bool => $cell !== null && ($cell['type'] ?? null) === 'empty')->count(),
            'clashes' => $cells->filter(fn (?array $cell): bool => ($cell['clashes'] ?? []) !== [])->count(),
        ];
    }

    /**
     * Every distinct clash in the semester, described in words, for leadership to resolve.
     *
     * @return list<string>
     */
    public function getClashDescriptions(): array
    {
        $descriptions = [];

        foreach ($this->schedules()->groupBy(fn (Schedule $s): string => "{$s->day_of_week->value}|{$s->time_slot_id}") as $slot) {
            $first = $slot->first();
            $when = "{$first->day_of_week->getLabel()} {$first->timeSlot->label}";

            foreach ($slot->whereNotNull('teacher_id')->groupBy('teacher_id') as $sameTeacher) {
                if ($this->isClash($sameTeacher)) {
                    $descriptions[] = "{$sameTeacher->first()->teacher->name} mengajar di {$sameTeacher->count()} kelas sekaligus pada {$when}: ".$sameTeacher->map(fn (Schedule $s): string => $s->classroom->code)->join(', ').'.';
                }
            }

            foreach ($slot->groupBy('classroom_id') as $sameClass) {
                if ($this->isClash($sameClass)) {
                    $descriptions[] = "Kelas {$sameClass->first()->classroom->code} punya {$sameClass->count()} pelajaran sekaligus pada {$when}: ".$sameClass->map(fn (Schedule $s): string => $s->subject->name)->join(', ').'.';
                }
            }
        }

        foreach ($this->schedules() as $schedule) {
            if (in_array('routine', $this->clashesFor($schedule), true)) {
                $descriptions[] = "{$schedule->subject->name} ({$schedule->classroom->code}) berada di jam rutinitas sekolah pada {$schedule->day_of_week->getLabel()} {$schedule->timeSlot->label}.";
            }
        }

        return array_values(array_unique($descriptions));
    }

    public function selectedClassroom(): ?Classroom
    {
        return ctype_digit($this->classroomId) ? Classroom::query()->find((int) $this->classroomId) : null;
    }

    public function selectedTeacher(): ?User
    {
        return ctype_digit($this->teacherId) ? User::query()->find((int) $this->teacherId) : null;
    }

    public function hasCellFilter(): bool
    {
        return $this->matrixView === self::VIEW_ALL && ($this->teacherId !== '' || $this->subjectId !== '');
    }

    /**
     * @param  Collection<int, TimeSlot>  $timeSlots
     * @param  Collection<int|string, Collection<int, DailyRoutine>>  $routines
     * @return array{columns: list<array{key: string, label: string, sub: ?string}>, rows: list<array{timeSlot: TimeSlot, band: ?string, cells: array<string, array<string, mixed>|null>}>}
     */
    private function dayGrid(Collection $timeSlots, Collection $routines): array
    {
        $day = SchoolDay::tryFrom($this->day) ?? SchoolDay::Senin;
        $classrooms = $this->getClassrooms();

        if (ctype_digit($this->classroomId)) {
            $classrooms = $classrooms->where('id', (int) $this->classroomId)->values();
        }

        $routineBySlot = ($routines->get($day->value) ?? collect())->keyBy('time_slot_id');
        $schedules = $this->schedules()
            ->where('day_of_week', $day)
            ->groupBy('time_slot_id');

        $rows = $timeSlots->map(function (TimeSlot $timeSlot) use ($classrooms, $routineBySlot, $schedules): array {
            if ($routine = $routineBySlot->get($timeSlot->id)) {
                return ['timeSlot' => $timeSlot, 'band' => $routine->label, 'cells' => []];
            }

            $inSlot = ($schedules->get($timeSlot->id) ?? collect())->groupBy('classroom_id');

            return [
                'timeSlot' => $timeSlot,
                'band' => null,
                'cells' => $classrooms->mapWithKeys(fn (Classroom $classroom): array => [
                    (string) $classroom->id => $this->cell($inSlot->get($classroom->id)?->first(), 'teacher'),
                ])->all(),
            ];
        })->values()->all();

        return [
            'columns' => $classrooms->map(fn (Classroom $classroom): array => [
                'key' => (string) $classroom->id,
                'label' => $classroom->code,
                'sub' => null,
            ])->all(),
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, TimeSlot>  $timeSlots
     * @param  Collection<int|string, Collection<int, DailyRoutine>>  $routines
     * @return array{columns: list<array{key: string, label: string, sub: ?string}>, rows: list<array{timeSlot: TimeSlot, band: ?string, cells: array<string, array<string, mixed>|null>}>}
     */
    private function weekGrid(Collection $timeSlots, Collection $routines): array
    {
        $days = $this->day === 0
            ? SchoolDay::cases()
            : [SchoolDay::tryFrom($this->day) ?? SchoolDay::Senin];

        $isClassView = $this->matrixView === self::VIEW_CLASS;
        $ownerId = $isClassView ? $this->selectedClassroom()?->id : $this->selectedTeacher()?->id;

        $schedules = $this->schedules()
            ->filter(fn (Schedule $schedule): bool => $ownerId !== null
                && ($isClassView ? $schedule->classroom_id : $schedule->teacher_id) === $ownerId)
            ->groupBy(fn (Schedule $schedule): string => "{$schedule->day_of_week->value}|{$schedule->time_slot_id}");

        $rows = $timeSlots->map(function (TimeSlot $timeSlot) use ($days, $routines, $schedules, $isClassView): array {
            $cells = [];

            foreach ($days as $day) {
                $routine = ($routines->get($day->value) ?? collect())->firstWhere('time_slot_id', $timeSlot->id);

                if ($routine) {
                    $cells[(string) $day->value] = ['type' => 'routine', 'label' => $routine->label];

                    continue;
                }

                $cells[(string) $day->value] = $this->cell(
                    $schedules->get("{$day->value}|{$timeSlot->id}")?->first(),
                    $isClassView ? 'teacher' : 'classroom',
                    flagEmpty: $isClassView,
                );
            }

            return ['timeSlot' => $timeSlot, 'band' => null, 'cells' => $cells];
        })->values()->all();

        return [
            'columns' => collect($days)->map(fn (SchoolDay $day): array => [
                'key' => (string) $day->value,
                'label' => $day->getLabel(),
                'sub' => null,
            ])->all(),
            'rows' => $rows,
        ];
    }

    /**
     * Describe one lesson cell, or an empty/filtered placeholder when there is no lesson
     * to show.
     *
     * @return array<string, mixed>|null
     */
    private function cell(?Schedule $schedule, string $secondary, bool $flagEmpty = true): ?array
    {
        if ($schedule === null) {
            return $flagEmpty && ! $this->hasCellFilter() ? ['type' => 'empty'] : null;
        }

        if ($this->hasCellFilter() && ! $this->matchesCellFilter($schedule)) {
            return ['type' => 'filtered'];
        }

        $secondaryLabel = $secondary === 'classroom'
            ? $schedule->classroom->code
            : ($schedule->teacher?->code ?? Str::limit($schedule->teacher_name, 10));

        return [
            'type' => 'lesson',
            'subject' => $schedule->subject->name,
            'secondary' => $secondaryLabel,
            'mine' => $schedule->teacher_id !== null && $schedule->teacher_id === auth()->id(),
            'clashes' => $this->canSeeClashes() ? $this->clashesFor($schedule) : [],
            'title' => "{$schedule->subject->name} — {$schedule->teacher_name} — {$schedule->classroom->label}",
        ];
    }

    private function matchesCellFilter(Schedule $schedule): bool
    {
        if (ctype_digit($this->teacherId) && $schedule->teacher_id !== (int) $this->teacherId) {
            return false;
        }

        return ! ctype_digit($this->subjectId) || $schedule->subject_id === (int) $this->subjectId;
    }

    /**
     * @return Collection<int, Schedule>
     */
    private function schedules(): Collection
    {
        $semester = $this->getSemester();

        if (! $semester) {
            return collect();
        }

        return $this->semesterSchedules ??= Schedule::query()
            ->where('semester_id', $semester->id)
            ->with(['classroom', 'subject', 'teacher', 'timeSlot'])
            ->get();
    }

    /**
     * The kinds of clash ("teacher", "classroom", "routine") a lesson is part of.
     *
     * @return list<string>
     */
    private function clashesFor(Schedule $schedule): array
    {
        if ($this->clashes === null) {
            $this->clashes = $this->detectClashes();
        }

        return $this->clashes[(string) $schedule->id] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    private function detectClashes(): array
    {
        $clashes = [];
        $routineSlots = DailyRoutine::query()->get()->map(fn (DailyRoutine $r): string => "{$r->day_of_week->value}|{$r->time_slot_id}")->flip();

        foreach ($this->schedules()->groupBy(fn (Schedule $s): string => "{$s->day_of_week->value}|{$s->time_slot_id}") as $slotKey => $slot) {
            foreach (['teacher' => 'teacher_id', 'classroom' => 'classroom_id'] as $kind => $column) {
                // Lessons taught by a team have no teacher, so they never clash on that account.
                foreach ($slot->whereNotNull($column)->groupBy($column) as $group) {
                    if ($this->isClash($group)) {
                        foreach ($group as $schedule) {
                            $clashes[(string) $schedule->id][] = $kind;
                        }
                    }
                }
            }

            if ($routineSlots->has($slotKey)) {
                foreach ($slot as $schedule) {
                    $clashes[(string) $schedule->id][] = 'routine';
                }
            }
        }

        return $clashes;
    }

    /**
     * Two or more lessons in one slot for the same teacher/class only clash when at least
     * one of them is not a subject the school deliberately runs in parallel on Kamis.
     *
     * @param  Collection<int, Schedule>  $group
     */
    private function isClash(Collection $group): bool
    {
        return $group->count() > 1
            && $group->contains(fn (Schedule $schedule): bool => ! Schedule::isConflictExempt($schedule->day_of_week, $schedule->subject));
    }
}
