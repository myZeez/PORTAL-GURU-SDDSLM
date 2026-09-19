<?php

namespace App\Filament\Pages;

use App\Enums\SchoolDay;
use App\Models\Classroom;
use App\Models\DailyRoutine;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\TimeSlot;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * The whole-school weekly timetable: every rombel across every time slot, for one day at
 * a time. Whole-school routines (from `daily_routines`) render as a single band across
 * every classroom column; teaching slots come from `schedules`.
 */
class ScheduleMatrix extends Page
{
    protected string $view = 'filament.pages.schedule-matrix';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Matriks Jadwal';

    protected static ?string $title = 'Matriks Jadwal';

    public int $day;

    public function mount(): void
    {
        $this->day = SchoolDay::tryFrom(now()->dayOfWeekIso)?->value ?? SchoolDay::Senin->value;
    }

    public function selectDay(int $day): void
    {
        $this->day = SchoolDay::tryFrom($day)?->value ?? $this->day;
    }

    /**
     * @return Collection<int, Classroom>
     */
    public function getClassrooms(): Collection
    {
        return Classroom::query()->orderBy('grade')->orderBy('code')->get();
    }

    /**
     * One row per time slot for the selected day: either a whole-school routine band, or
     * a per-classroom lookup of that slot's schedule (if any).
     *
     * @return list<array{type: 'routine'|'classes', timeSlot: TimeSlot, label?: string, schedules?: Collection<int, Schedule>}>
     */
    public function getRows(): array
    {
        $day = SchoolDay::from($this->day);
        $semester = Semester::current();

        $routinesBySlot = DailyRoutine::query()
            ->where('day_of_week', $day->value)
            ->get()
            ->keyBy('time_slot_id');

        $schedulesBySlot = $semester
            ? Schedule::query()
                ->where('semester_id', $semester->id)
                ->where('day_of_week', $day->value)
                ->with(['classroom', 'subject', 'teacher'])
                ->get()
                ->groupBy('time_slot_id')
            : collect();

        return TimeSlot::query()
            ->orderBy('starts_at')
            ->get()
            ->map(function (TimeSlot $timeSlot) use ($routinesBySlot, $schedulesBySlot): array {
                if ($routine = $routinesBySlot->get($timeSlot->id)) {
                    return ['type' => 'routine', 'timeSlot' => $timeSlot, 'label' => $routine->label];
                }

                return [
                    'type' => 'classes',
                    'timeSlot' => $timeSlot,
                    'schedules' => ($schedulesBySlot->get($timeSlot->id) ?? collect())->keyBy('classroom_id'),
                ];
            })
            ->all();
    }
}
