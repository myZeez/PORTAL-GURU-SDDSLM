<?php

namespace App\Filament\Actions;

use App\Models\Semester;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * A "download PDF" header action that asks which semester the report is for, and then which
 * month or day inside it, instead of always exporting today or the current month. Semesters
 * without a start date are left out, since there is nothing to pick a period from.
 */
class SemesterReportAction
{
    /**
     * A report for one month of a semester.
     *
     * @param  (Closure(): (Carbon|string|null))|null  $initialDate  the date the form opens on, when it is not today
     */
    public static function forMonth(string $routeName, string $label = 'Unduh PDF', ?Closure $initialDate = null): Action
    {
        return self::build($routeName, $label, 'month', $initialDate)
            ->schema(fn (): array => [
                self::semesterField('month'),
                Select::make('month')
                    ->label('Bulan')
                    ->options(fn (Get $get): array => self::monthOptions(self::semester($get('semester_id'))))
                    ->required()
                    ->native(false),
            ]);
    }

    /**
     * A report for one day of a semester.
     *
     * @param  (Closure(): (Carbon|string|null))|null  $initialDate  the date the form opens on, when it is not today
     */
    public static function forDate(string $routeName, string $label = 'Unduh PDF', ?Closure $initialDate = null): Action
    {
        return self::build($routeName, $label, 'date', $initialDate)
            ->schema(fn (): array => [
                self::semesterField('date'),
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->minDate(fn (Get $get): ?Carbon => self::semester($get('semester_id'))?->starts_on)
                    ->maxDate(fn (Get $get): ?Carbon => self::semester($get('semester_id'))?->effectiveEndsOn()),
            ]);
    }

    private static function build(string $routeName, string $label, string $periodField, ?Closure $initialDate): Action
    {
        return Action::make('downloadPdf')
            ->label($label)
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->modalHeading($label)
            ->modalSubmitActionLabel('Unduh')
            ->modalWidth('md')
            ->fillForm(function () use ($periodField, $initialDate): array {
                $date = Carbon::parse($initialDate ? $initialDate() : today());
                $semester = Semester::containing($date) ?? self::semesters()->first();
                $date = $semester ? self::clamp($date, $semester) : $date;

                return [
                    'semester_id' => $semester?->id,
                    $periodField => $periodField === 'month' ? $date->format('Y-m') : $date->toDateString(),
                ];
            })
            ->action(fn (array $data, Component $livewire) => $livewire->redirect(route($routeName, [
                'semester' => $data['semester_id'],
                $periodField => $data[$periodField],
            ])));
    }

    private static function semesterField(string $periodField): Select
    {
        return Select::make('semester_id')
            ->label('Semester')
            ->options(fn (): array => self::semesters()
                ->mapWithKeys(fn (Semester $semester): array => [$semester->id => $semester->name.($semester->is_active ? ' (aktif)' : '')])
                ->all())
            ->required()
            ->native(false)
            ->live()
            ->afterStateUpdated(function (Set $set, mixed $state) use ($periodField): void {
                $semester = self::semester($state);

                if (! $semester) {
                    return;
                }

                $date = self::clamp(today(), $semester);
                $set($periodField, $periodField === 'month' ? $date->format('Y-m') : $date->toDateString());
            });
    }

    /**
     * @return Collection<int, Semester>
     */
    private static function semesters(): Collection
    {
        return Semester::query()
            ->whereNotNull('starts_on')
            ->orderByDesc('starts_on')
            ->get();
    }

    private static function semester(mixed $id): ?Semester
    {
        return filled($id) ? self::semesters()->firstWhere('id', (int) $id) : null;
    }

    /**
     * Pull a date into the semester: the day itself when it falls inside, otherwise the
     * nearest edge of the semester.
     */
    private static function clamp(Carbon $date, Semester $semester): Carbon
    {
        $endsOn = $semester->effectiveEndsOn();

        return match (true) {
            $date->lt($semester->starts_on) => $semester->starts_on->copy(),
            $date->gt($endsOn) => $endsOn,
            default => $date->copy(),
        };
    }

    /**
     * @return array<string, string>
     */
    private static function monthOptions(?Semester $semester): array
    {
        if (! $semester) {
            return [];
        }

        $options = [];

        $endsOn = $semester->effectiveEndsOn();

        for ($month = $semester->starts_on->copy()->startOfMonth(); $month->lte($endsOn); $month->addMonthNoOverflow()) {
            $options[$month->format('Y-m')] = $month->translatedFormat('F Y');
        }

        return $options;
    }
}
