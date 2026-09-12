<?php

namespace App\Filament\Pages;

use App\Enums\SchoolDay;
use App\Models\AdditionalDuty;
use App\Models\Schedule;
use App\Models\Semester;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * A read-only summary of the signed-in user's own workload: their weekly teaching
 * schedule (consecutive slots for the same classroom/subject merged), the JP that
 * teaching load carries per subject/classroom, any additional duties, and the combined
 * total — "Beban kerja = JP mengajar + JP tugas tambahan". Every user sees only
 * themselves, so there is nothing here to restrict access to.
 */
class MyProfile extends Page
{
    protected string $view = 'filament.pages.my-profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'Profil';

    protected static ?string $title = 'Profil';

    protected static ?int $navigationSort = 7;

    /**
     * This teacher's schedule for the active semester, grouped by day and with
     * consecutive slots for the same classroom and subject merged into one entry.
     *
     * @return array<int, array{label: string, entries: list<array{classroom: string, subject: string, starts_at: string, ends_at: string, jp: int}>}>
     */
    public function getWeeklySchedule(): array
    {
        $semester = Semester::current();

        $schedulesByDay = $semester
            ? Schedule::query()
                ->where('semester_id', $semester->id)
                ->where('teacher_id', auth()->id())
                ->with(['timeSlot', 'classroom', 'subject'])
                ->get()
                ->groupBy('day_of_week')
            : collect();

        $days = [];

        foreach (SchoolDay::cases() as $day) {
            $daySchedules = ($schedulesByDay->get($day->value) ?? collect())
                ->sortBy(fn (Schedule $schedule): string => $schedule->timeSlot->starts_at)
                ->values();

            $days[$day->value] = [
                'label' => $day->getLabel(),
                'entries' => $this->mergeConsecutiveSlots($daySchedules),
            ];
        }

        return $days;
    }

    /**
     * Merge schedule slots that are back-to-back for the same classroom and subject
     * into a single ranged entry, e.g. 08.30-09.00 + 09.00-09.30 becomes 08.30-09.30.
     *
     * @param  Collection<int, Schedule>  $schedules  Already sorted by start time.
     * @return list<array{classroom: string, subject: string, starts_at: string, ends_at: string, jp: int}>
     */
    private function mergeConsecutiveSlots(Collection $schedules): array
    {
        $merged = [];
        $current = null;

        foreach ($schedules as $schedule) {
            if (
                $current
                && $current['classroom_id'] === $schedule->classroom_id
                && $current['subject_id'] === $schedule->subject_id
                && $current['ends_at'] === $schedule->timeSlot->starts_at
            ) {
                $current['ends_at'] = $schedule->timeSlot->ends_at;
                $current['jp'] += $schedule->jp;

                continue;
            }

            if ($current) {
                $merged[] = $current;
            }

            $current = [
                'classroom_id' => $schedule->classroom_id,
                'subject_id' => $schedule->subject_id,
                'classroom' => $schedule->classroom->label,
                'subject' => $schedule->subject->name,
                'starts_at' => $schedule->timeSlot->starts_at,
                'ends_at' => $schedule->timeSlot->ends_at,
                'jp' => $schedule->jp,
            ];
        }

        if ($current) {
            $merged[] = $current;
        }

        return array_map(
            fn (array $entry): array => [
                'classroom' => $entry['classroom'],
                'subject' => $entry['subject'],
                'starts_at' => $entry['starts_at'],
                'ends_at' => $entry['ends_at'],
                'jp' => $entry['jp'],
            ],
            $merged,
        );
    }

    /**
     * This teacher's teaching load (tugas utama): JP totalled per classroom/subject
     * combination, across the whole week.
     *
     * @return Collection<int, array{classroom: string, subject: string, jp: int}>
     */
    public function getTeachingLoad(): Collection
    {
        $semester = Semester::current();

        if (! $semester) {
            return collect();
        }

        return Schedule::query()
            ->where('semester_id', $semester->id)
            ->where('teacher_id', auth()->id())
            ->with(['classroom', 'subject'])
            ->get()
            ->groupBy(fn (Schedule $schedule): string => "{$schedule->classroom_id}-{$schedule->subject_id}")
            ->map(fn (Collection $group): array => [
                'classroom' => $group->first()->classroom->label,
                'subject' => $group->first()->subject->name,
                'jp' => $group->sum('jp'),
            ])
            ->sortBy('classroom')
            ->values();
    }

    /**
     * This teacher's additional duties (tugas tambahan).
     *
     * @return Collection<int, AdditionalDuty>
     */
    public function getAdditionalDuties(): Collection
    {
        return AdditionalDuty::query()
            ->where('teacher_id', auth()->id())
            ->get();
    }

    /**
     * Total workload: teaching JP plus additional-duty JP.
     */
    public function getTotalJp(): int
    {
        return $this->getTeachingLoad()->sum('jp') + $this->getAdditionalDuties()->sum('jp');
    }
}
