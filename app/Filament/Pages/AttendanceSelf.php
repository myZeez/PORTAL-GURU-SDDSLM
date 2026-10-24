<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
use App\Models\AttendanceReopening;
use App\Models\TeacherAttendance;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Locked;

/**
 * @property-read Schema $form
 */
class AttendanceSelf extends Page
{
    protected string $view = 'filament.pages.attendance-self';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Absensi Saya';

    protected static ?string $title = 'Absensi Saya';

    protected static ?int $navigationSort = 3;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    #[Locked]
    public string $selectedDate;

    /**
     * @var Collection<int, TeacherAttendance>|null
     */
    private ?Collection $attendanceRecords = null;

    /**
     * @var SupportCollection<int, string>|null
     */
    private ?SupportCollection $openDates = null;

    public function mount(): void
    {
        $this->selectedDate = Carbon::today()->toDateString();

        if (! $this->isApplicable()) {
            return;
        }

        $this->form->fill($this->getRecord()?->attributesToArray() ?? [
            'status' => AttendanceStatus::Hadir->value,
        ]);
    }

    /**
     * Kepala Sekolah never fills attendance, so this page shows an explanatory message
     * instead of the form for them (rather than a hard 403), since the mobile dock's
     * Absensi shortcut is shared by every signed-in user.
     */
    public function isApplicable(): bool
    {
        return ! auth()->user()->isPrincipal();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Select::make('status')
                        ->label('Status')
                        ->options(collect(AttendanceStatus::cases())
                            ->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => $status->getLabel()])
                            ->all())
                        ->native(false)
                        ->required()
                        ->live(),
                    DatePicker::make('leave_starts_on')
                        ->label('Cuti mulai')
                        ->visible(fn (Get $get): bool => $get('status') === AttendanceStatus::Cuti->value)
                        ->required(fn (Get $get): bool => $get('status') === AttendanceStatus::Cuti->value),
                    DatePicker::make('leave_ends_on')
                        ->label('Cuti selesai')
                        ->visible(fn (Get $get): bool => $get('status') === AttendanceStatus::Cuti->value)
                        ->required(fn (Get $get): bool => $get('status') === AttendanceStatus::Cuti->value)
                        ->afterOrEqual('leave_starts_on'),
                    Textarea::make('early_leave_reason')
                        ->label('Alasan pulang awal')
                        ->placeholder('Contoh: kontrol dokter, urusan keluarga mendesak')
                        ->rows(2)
                        ->visible(fn (Get $get): bool => $get('status') === AttendanceStatus::PulangCepat->value)
                        ->required(fn (Get $get): bool => $get('status') === AttendanceStatus::PulangCepat->value),
                    Textarea::make('notes')
                        ->label('Keterangan')
                        ->rows(2),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        SchemaActions::make([
                            Action::make('save')
                                ->label(fn (): string => $this->getRecord() ? 'Perbarui' : 'Simpan')
                                ->submit('save'),
                        ]),
                    ]),
            ])
            ->record($this->getRecord())
            ->statePath('data');
    }

    public function save(): void
    {
        if (! $this->isApplicable()) {
            abort(403);
        }

        $fillDate = $this->fillDate();

        if (! TeacherAttendance::isFillableDate($fillDate)) {
            Notification::make()
                ->warning()
                ->title('Tanggal ini bukan hari efektif, tidak perlu mengisi absensi.')
                ->send();

            return;
        }

        $data = $this->form->getState();

        $record = $this->getRecord();

        if ($record && ! auth()->user()->isSuperAdmin()) {
            Notification::make()
                ->warning()
                ->title('Absensi sudah tersimpan dan terkunci.')
                ->body('Hubungi Super Admin bila data ini perlu dikoreksi.')
                ->send();

            return;
        }

        $record ??= new TeacherAttendance([
            'teacher_id' => auth()->id(),
            'date' => $fillDate,
        ]);

        $record->fill($data);
        $record->save();

        $this->form->fill($record->attributesToArray());

        Notification::make()
            ->success()
            ->title('Absensi tersimpan.')
            ->send();
    }

    public function getRecord(): ?TeacherAttendance
    {
        return TeacherAttendance::query()
            ->where('teacher_id', auth()->id())
            ->whereDate('date', $this->fillDate())
            ->first();
    }

    /**
     * Select a day from the current month to inspect its attendance record.
     */
    public function selectDate(string $date): void
    {
        try {
            $selectedDate = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable) {
            return;
        }

        if ($selectedDate->format('Y-m-d') !== $date) {
            return;
        }

        if (! $selectedDate->isSameMonth(Carbon::today()) && ! $this->isReopened($selectedDate)) {
            return;
        }

        $this->selectedDate = $selectedDate->toDateString();
        $this->attendanceRecords = null;
        $this->form->fill($this->getRecord()?->attributesToArray() ?? [
            'status' => AttendanceStatus::Hadir->value,
        ]);
    }

    /**
     * @return Collection<int, TeacherAttendance>
     */
    public function getAttendanceRecords(): Collection
    {
        if ($this->attendanceRecords !== null) {
            return $this->attendanceRecords;
        }

        return $this->attendanceRecords = TeacherAttendance::query()
            ->where('teacher_id', auth()->id())
            ->whereBetween('date', [Carbon::today()->startOfMonth(), Carbon::today()->endOfMonth()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn (TeacherAttendance $attendance): string => $attendance->date->toDateString());
    }

    /**
     * @return list<array{date: Carbon, attendance: ?TeacherAttendance, reopened: bool}>
     */
    public function getAttendanceDays(): array
    {
        $month = Carbon::today()->startOfMonth();
        $attendanceRecords = $this->getAttendanceRecords();

        return collect(range(1, $month->daysInMonth))
            ->map(fn (int $day): array => [
                'date' => $month->copy()->day($day),
                'attendance' => $attendanceRecords->get($month->copy()->day($day)->toDateString()),
                'reopened' => $this->isReopened($month->copy()->day($day)),
            ])
            ->all();
    }

    public function getSelectedAttendance(): ?TeacherAttendance
    {
        return $this->getAttendanceRecords()->get($this->selectedDate);
    }

    /**
     * @return list<array{status: AttendanceStatus, total: int}>
     */
    public function getAttendanceSummary(): array
    {
        return collect(AttendanceStatus::cases())
            ->map(fn (AttendanceStatus $status): array => [
                'status' => $status,
                'total' => $this->getAttendanceRecords()->where('status', $status)->count(),
            ])
            ->all();
    }

    public function isFillDateFillable(): bool
    {
        return TeacherAttendance::isFillableDate($this->fillDate());
    }

    /**
     * Today, or the picked date when Waka Kurikulum has reopened it for catching up.
     */
    public function fillDate(): Carbon
    {
        $selected = Carbon::parse($this->selectedDate);

        return $selected->isToday() || ($selected->isPast() && $this->isReopened($selected))
            ? $selected->startOfDay()
            : Carbon::today();
    }

    public function isReopened(Carbon $date): bool
    {
        return $date->lt(Carbon::today()) && $this->openReopeningDates()->contains($date->toDateString());
    }

    /**
     * The dates currently open for catching up, loaded once per request.
     *
     * @return SupportCollection<int, string>
     */
    private function openReopeningDates(): SupportCollection
    {
        return $this->openDates ??= AttendanceReopening::query()->open()->get()
            ->map(fn (AttendanceReopening $reopening): string => $reopening->date->toDateString());
    }

    /**
     * Whether the picked date is one the teacher may fill in themselves: today, or a
     * reopened past date.
     */
    public function isFillingSelectedDate(): bool
    {
        return $this->fillDate()->toDateString() === $this->selectedDate;
    }

    /**
     * Past dates that were reopened and still lack this teacher's attendance.
     *
     * @return Collection<int, AttendanceReopening>
     */
    public function getOpenReopenings(): Collection
    {
        $filled = TeacherAttendance::query()
            ->where('teacher_id', auth()->id())
            ->pluck('date')
            ->map(fn ($date): string => Carbon::parse($date)->toDateString());

        return AttendanceReopening::query()->open()->orderBy('date')->get()
            ->reject(fn (AttendanceReopening $reopening): bool => $filled->contains($reopening->date->toDateString()))
            ->values();
    }

    /**
     * A saved attendance record is immutable for its owner. Super Admin may make an
     * exceptional correction from this page or the recap resource.
     */
    public function canCorrectTodayAttendance(): bool
    {
        return $this->getRecord() === null || auth()->user()->isSuperAdmin();
    }
}
