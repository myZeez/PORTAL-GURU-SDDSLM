<?php

namespace App\Filament\Pages;

use App\Models\Classroom;
use App\Models\SimpatiCheck;
use BackedEnum;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;

/**
 * The pendamping wali kelas's daily checklist for the SIMPATI portal: one tick per class
 * per required date, saved to the shared database so the dashboards of Waka Kurikulum and
 * Koordinator read exactly what is ticked here. A pendamping sees and changes only the
 * class they assist; Waka Kurikulum and Super Admin may tick any class.
 */
class SimpatiChecklist extends Page
{
    protected string $view = 'filament.pages.simpati-checklist';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Checklist SIMPATI';

    protected static ?string $title = 'Checklist SIMPATI';

    protected static ?int $navigationSort = 4;

    #[Locked]
    public string $month;

    public string $classroomId = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && ($user->canManageAllSimpati() || $user->assistedClassroom()->exists());
    }

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->classroomId = (string) ($this->getClassrooms()->first()?->id ?? '');
    }

    /**
     * The classes the signed-in user may tick: all of them for whoever manages SIMPATI,
     * otherwise only the class they assist.
     *
     * @return Collection<int, Classroom>
     */
    public function getClassrooms(): Collection
    {
        $user = auth()->user();

        $query = Classroom::query()->with('assistantTeacher')->orderBy('grade')->orderBy('code');

        if (! $user->canManageAllSimpati()) {
            $query->where('assistant_teacher_id', $user->id);
        }

        return $query->get();
    }

    public function selectedClassroom(): ?Classroom
    {
        $classrooms = $this->getClassrooms();

        return $classrooms->firstWhere('id', (int) $this->classroomId) ?? $classrooms->first();
    }

    public function getMonthStart(): Carbon
    {
        return Carbon::createFromFormat('!Y-m', $this->month)->startOfMonth();
    }

    public function previousMonth(): void
    {
        $this->month = $this->getMonthStart()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        if ($this->getMonthStart()->lt(now()->startOfMonth())) {
            $this->month = $this->getMonthStart()->addMonth()->format('Y-m');
        }
    }

    public function canGoToNextMonth(): bool
    {
        return $this->getMonthStart()->lt(now()->startOfMonth());
    }

    /**
     * The required dates of the shown month up to today, newest first, each with its
     * saved check (if any).
     *
     * @return Collection<int, array{date: CarbonInterface, check: ?SimpatiCheck}>
     */
    public function getRows(): Collection
    {
        $classroom = $this->selectedClassroom();

        if (! $classroom) {
            return collect();
        }

        $monthStart = $this->getMonthStart();
        $until = $monthStart->copy()->endOfMonth()->min(now()->endOfDay());

        $checks = SimpatiCheck::query()
            ->with('checkedBy')
            ->where('classroom_id', $classroom->id)
            ->whereBetween('date', [$monthStart->toDateString(), $until->toDateString()])
            ->get()
            ->keyBy(fn (SimpatiCheck $check): string => $check->date->toDateString());

        return SimpatiCheck::requiredDatesBetween($monthStart, $until)
            ->sortByDesc(fn (CarbonInterface $date): int => $date->timestamp)
            ->values()
            ->map(fn (CarbonInterface $date): array => ['date' => $date, 'check' => $checks->get($date->toDateString())]);
    }

    /**
     * @return array{filled: int, required: int, percentage: int}
     */
    public function getCompletion(): array
    {
        $rows = $this->getRows();
        $filled = $rows->filter(fn (array $row): bool => (bool) $row['check']?->is_filled)->count();

        return [
            'filled' => $filled,
            'required' => $rows->count(),
            'percentage' => $rows->isEmpty() ? 0 : (int) round($filled / $rows->count() * 100),
        ];
    }

    /**
     * Flip the checklist for one date of the selected class.
     */
    public function toggle(string $date): void
    {
        $classroom = $this->selectedClassroom();

        abort_unless($classroom !== null, 403);

        try {
            $day = Carbon::createFromFormat('!Y-m-d', $date);
        } catch (InvalidFormatException) {
            abort(422);
        }

        abort_unless($day->format('Y-m-d') === $date, 422);

        if ($day->isFuture() || ! SimpatiCheck::isRequiredOn($day)) {
            Notification::make()
                ->warning()
                ->title('Tanggal ini tidak perlu diisi SIMPATI.')
                ->send();

            return;
        }

        $current = SimpatiCheck::query()
            ->where('classroom_id', $classroom->id)
            ->whereDate('date', $date)
            ->value('is_filled');

        SimpatiCheck::record($classroom, $day, ! $current, auth()->user());
    }
}
