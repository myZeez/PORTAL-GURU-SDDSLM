<?php

namespace App\Filament\Pages;

use App\Enums\CalendarDayStatus;
use App\Enums\SchoolDay;
use App\Models\CalendarDay;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Substitution;
use App\Models\TimeSlot;
use BackedEnum;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * One teacher's own timetable: only the lessons they are assigned to teach or cover, in a
 * single time-ordered agenda, viewable per weekday, for the whole week, or for a specific
 * date. School-wide routines (breakfast, ceremony, break) are deliberately left out; they
 * live on "Matriks Jadwal". Every query is scoped to the signed-in teacher, so nobody can
 * see a colleague's personal schedule here.
 */
class MySchedule extends Page
{
    public const MODE_DAY = 'day';

    public const MODE_WEEK = 'week';

    public const MODE_DATE = 'date';

    protected string $view = 'filament.pages.my-schedule';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Jadwal Saya';

    protected static ?string $title = 'Jadwal Saya';

    protected static ?int $navigationSort = 2;

    public string $mode = self::MODE_DAY;

    public int $day;

    public string $date;

    public function mount(): void
    {
        $this->day = SchoolDay::tryFrom(now()->dayOfWeekIso)?->value ?? SchoolDay::Senin->value;
        $this->date = now()->toDateString();
    }

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, [self::MODE_DAY, self::MODE_WEEK, self::MODE_DATE], true) ? $mode : self::MODE_DAY;
    }

    public function selectDay(int $day): void
    {
        $this->day = SchoolDay::tryFrom($day)?->value ?? $this->day;
        $this->mode = self::MODE_DAY;
    }

    public function selectToday(): void
    {
        $this->day = SchoolDay::tryFrom(now()->dayOfWeekIso)?->value ?? SchoolDay::Senin->value;
        $this->date = now()->toDateString();
        $this->mode = self::MODE_DAY;
    }

    public function updatedDate(): void
    {
        if ($this->selectedDate() === null) {
            $this->date = now()->toDateString();
        }
    }

    public function isToday(): bool
    {
        return $this->mode === self::MODE_DAY && $this->day === now()->dayOfWeekIso;
    }

    /**
     * The date the "Tanggal" view points at, or null when the stored value is not a real
     * date (the property is client-editable, so it is never trusted blindly).
     */
    public function selectedDate(): ?Carbon
    {
        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', $this->date);
        } catch (InvalidFormatException) {
            return null;
        }

        return $parsed->format('Y-m-d') === $this->date ? $parsed : null;
    }

    /**
     * The calendar status of the picked date, so a holiday is explained instead of
     * simply looking like an empty day.
     */
    public function getSelectedDateStatus(): ?CalendarDayStatus
    {
        $date = $this->selectedDate();

        if (! $date) {
            return null;
        }

        if (SchoolDay::tryFrom($date->dayOfWeekIso) === null) {
            return CalendarDayStatus::Libur;
        }

        return CalendarDay::statusFor($date);
    }

    /**
     * Routines and this teacher's own schedule slots for the selected weekday (or the
     * picked date), merged into one list ordered by time.
     *
     * @return list<array{starts_at: string, start: string, end: string, label: string, type: 'teaching'|'covering', subtitle: ?string, note: ?string}>
     */
    public function getEntries(): array
    {
        if ($this->mode === self::MODE_DATE) {
            $date = $this->selectedDate();

            return $date && ($day = SchoolDay::tryFrom($date->dayOfWeekIso)) ? $this->entriesFor($day, $date) : [];
        }

        return $this->entriesFor(SchoolDay::from($this->day));
    }

    /**
     * Every weekday's entries and JP, for the week view.
     *
     * @return list<array{day: SchoolDay, entries: list<array<string, mixed>>, jp: int}>
     */
    public function getWeek(): array
    {
        return collect(SchoolDay::cases())
            ->map(fn (SchoolDay $day): array => [
                'day' => $day,
                'entries' => $this->entriesFor($day),
                'jp' => $this->jpFor($day),
            ])
            ->all();
    }

    /**
     * Total teaching hours (JP) for what is currently shown: the selected weekday, the
     * picked date, or the whole week.
     */
    public function getTotalJp(): int
    {
        if ($this->mode === self::MODE_WEEK) {
            return collect(SchoolDay::cases())->sum(fn (SchoolDay $day): int => $this->jpFor($day));
        }

        if ($this->mode === self::MODE_DATE) {
            $date = $this->selectedDate();

            return $date && ($day = SchoolDay::tryFrom($date->dayOfWeekIso)) ? $this->jpFor($day, $date) : 0;
        }

        return $this->jpFor(SchoolDay::from($this->day));
    }

    public function getJpLabel(): string
    {
        return match ($this->mode) {
            self::MODE_WEEK => 'JP Mengajar Minggu Ini',
            self::MODE_DATE => 'JP Mengajar pada Tanggal Ini',
            default => $this->isToday() ? 'JP Mengajar Hari Ini' : 'JP Mengajar Hari '.SchoolDay::from($this->day)->getLabel(),
        };
    }

    /**
     * @return list<array{starts_at: string, start: string, end: string, label: string, type: 'teaching'|'covering', subtitle: ?string, note: ?string}>
     */
    private function entriesFor(SchoolDay $day, ?CarbonInterface $date = null): array
    {
        $own = $this->ownSchedules($day, $date);
        $substitutedAway = $date ? $this->substitutionsOn($date, $own->pluck('id')->all()) : collect();

        $teaching = $own->map(function (Schedule $schedule) use ($substitutedAway): array {
            $replacement = $substitutedAway->get($schedule->id);

            return $this->entry(
                $schedule->timeSlot,
                $schedule->subject->name,
                'teaching',
                $schedule->classroom->label.($schedule->jp === 0 ? ' · 0 JP' : ''),
                $replacement ? "Digantikan oleh {$replacement->substituteTeacher->name}" : null,
            );
        });

        $covering = $date ? $this->coveringSlots($date) : collect();

        return $teaching->concat($covering)->sortBy('starts_at')->values()->all();
    }

    /**
     * @return Collection<int, Schedule>
     */
    private function ownSchedules(SchoolDay $day, ?CarbonInterface $date = null): Collection
    {
        $semester = $this->semesterFor($date);

        if (! $semester) {
            return collect();
        }

        return Schedule::query()
            ->where('semester_id', $semester->id)
            ->where('day_of_week', $day->value)
            ->where('teacher_id', auth()->id())
            ->with(['timeSlot', 'classroom', 'subject'])
            ->get();
    }

    /**
     * The semester a date belongs to, falling back to the active one.
     */
    private function semesterFor(?CarbonInterface $date): ?Semester
    {
        if ($date) {
            $containing = Semester::query()
                ->whereDate('starts_on', '<=', $date->toDateString())
                ->whereDate('ends_on', '>=', $date->toDateString())
                ->first();

            if ($containing) {
                return $containing;
            }
        }

        return Semester::current();
    }

    /**
     * @param  list<int>  $scheduleIds
     * @return Collection<int, Substitution>
     */
    private function substitutionsOn(CarbonInterface $date, array $scheduleIds): Collection
    {
        return Substitution::query()
            ->whereDate('date', $date->toDateString())
            ->whereIn('schedule_id', $scheduleIds)
            ->with('substituteTeacher')
            ->get()
            ->keyBy('schedule_id');
    }

    /**
     * Slots of other teachers this teacher is covering as a substitute on the given date.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function coveringSlots(CarbonInterface $date): Collection
    {
        return Substitution::query()
            ->whereDate('date', $date->toDateString())
            ->where('substitute_teacher_id', auth()->id())
            ->with(['schedule.timeSlot', 'schedule.classroom', 'schedule.subject', 'schedule.teacher'])
            ->get()
            ->map(fn (Substitution $substitution): array => $this->entry(
                $substitution->schedule->timeSlot,
                $substitution->schedule->subject->name,
                'covering',
                $substitution->schedule->classroom->label,
                "Menggantikan {$substitution->schedule->teacher_name}",
            ));
    }

    private function jpFor(SchoolDay $day, ?CarbonInterface $date = null): int
    {
        return $this->ownSchedules($day, $date)->sum('jp');
    }

    /**
     * @return array{starts_at: string, start: string, end: string, label: string, type: string, subtitle: ?string, note: ?string}
     */
    private function entry(TimeSlot $timeSlot, string $label, string $type, ?string $subtitle, ?string $note = null): array
    {
        return [
            'starts_at' => $timeSlot->starts_at,
            'start' => str_replace(':', '.', Str::substr($timeSlot->starts_at, 0, 5)),
            'end' => str_replace(':', '.', Str::substr($timeSlot->ends_at, 0, 5)),
            'label' => $label,
            'type' => $type,
            'subtitle' => $subtitle,
            'note' => $note,
        ];
    }
}
