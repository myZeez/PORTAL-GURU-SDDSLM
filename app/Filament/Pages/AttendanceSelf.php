<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
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

    public function mount(): void
    {
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
        $today = Carbon::today();

        if (! TeacherAttendance::isFillableDate($today)) {
            Notification::make()
                ->warning()
                ->title('Hari ini bukan hari efektif, tidak perlu mengisi absensi.')
                ->send();

            return;
        }

        $data = $this->form->getState();

        $record = $this->getRecord() ?? new TeacherAttendance([
            'teacher_id' => auth()->id(),
            'date' => $today,
        ]);

        $record->fill($data);
        $record->save();

        $this->form->fill($record->attributesToArray());

        Notification::make()
            ->success()
            ->title('Absensi hari ini tersimpan.')
            ->send();
    }

    public function getRecord(): ?TeacherAttendance
    {
        return TeacherAttendance::query()
            ->where('teacher_id', auth()->id())
            ->whereDate('date', Carbon::today())
            ->first();
    }

    /**
     * Get the signed-in teacher's own attendance for the last 14 days, most recent first.
     *
     * @return Collection<int, TeacherAttendance>
     */
    public function getHistory(): Collection
    {
        return TeacherAttendance::query()
            ->where('teacher_id', auth()->id())
            ->where('date', '>=', Carbon::today()->subDays(14))
            ->orderByDesc('date')
            ->get();
    }

    public function isTodayFillable(): bool
    {
        return TeacherAttendance::isFillableDate(Carbon::today());
    }
}
