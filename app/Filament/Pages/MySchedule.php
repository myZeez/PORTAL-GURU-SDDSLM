<?php

namespace App\Filament\Pages;

use App\Enums\SchoolDay;
use App\Models\DailyRoutine;
use App\Models\Schedule;
use App\Models\Semester;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * One teacher's own timetable for a day: whole-school routines plus their own teaching
 * slots, merged into a single time-ordered agenda. Every teacher sees only themselves —
 * there is nothing here to restrict access to.
 */
class MySchedule extends Page
{
    protected string $view = 'filament.pages.my-schedule';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Jadwal Saya';

    protected static ?string $title = 'Jadwal Saya';

    protected static ?int $navigationSort = 2;

    public int $day;

    public function mount(): void
    {
        $this->day = SchoolDay::tryFrom(now()->dayOfWeekIso)?->value ?? SchoolDay::Senin->value;
    }

    public function selectDay(int $day): void
    {
        $this->day = $day;
    }

    /**
     * Routines and this teacher's own schedule slots for the selected day, merged into
     * one list ordered by time.
     *
     * @return list<array{timeSlot: string, label: string, type: 'routine'|'teaching', subtitle: ?string}>
     */
    public function getEntries(): array
    {
        $day = SchoolDay::from($this->day);
        $semester = Semester::current();

        $routines = DailyRoutine::query()
            ->where('day_of_week', $day->value)
            ->with('timeSlot')
            ->get()
            ->map(fn (DailyRoutine $routine): array => [
                'starts_at' => $routine->timeSlot->starts_at,
                'timeSlot' => $routine->timeSlot->label,
                'label' => $routine->label,
                'type' => 'routine',
                'subtitle' => 'Rutinitas · Semua Kelas',
            ]);

        $schedules = $semester
            ? Schedule::query()
                ->where('semester_id', $semester->id)
                ->where('day_of_week', $day->value)
                ->where('teacher_id', auth()->id())
                ->with(['timeSlot', 'classroom', 'subject'])
                ->get()
                ->map(fn (Schedule $schedule): array => [
                    'starts_at' => $schedule->timeSlot->starts_at,
                    'timeSlot' => $schedule->timeSlot->label,
                    'label' => $schedule->subject->name,
                    'type' => 'teaching',
                    'subtitle' => $schedule->classroom->label.($schedule->jp === 0 ? ' · 0 JP' : ''),
                ])
            : collect();

        return $routines->concat($schedules)->sortBy('starts_at')->values()->all();
    }

    /**
     * Total teaching hours (JP) for the selected day.
     */
    public function getTotalJp(): int
    {
        $semester = Semester::current();

        if (! $semester) {
            return 0;
        }

        return Schedule::query()
            ->where('semester_id', $semester->id)
            ->where('day_of_week', $this->day)
            ->where('teacher_id', auth()->id())
            ->get()
            ->sum('jp');
    }
}
