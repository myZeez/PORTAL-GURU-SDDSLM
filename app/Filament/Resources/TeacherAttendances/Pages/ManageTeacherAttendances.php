<?php

namespace App\Filament\Resources\TeacherAttendances\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\CalendarDayStatus;
use App\Filament\Actions\SemesterReportAction;
use App\Filament\Pages\AttendanceSelf;
use App\Filament\Resources\TeacherAttendances\TeacherAttendanceResource;
use App\Models\AttendanceReopening;
use App\Models\CalendarDay;
use App\Models\CalendarDayLog;
use App\Models\TeacherAttendance;
use App\Models\TeacherAttendanceLog;
use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class ManageTeacherAttendances extends ManageRecords
{
    protected static string $resource = TeacherAttendanceResource::class;

    public string $selectedDate;

    public function mount(): void
    {
        $this->selectedDate = now()->toDateString();

        parent::mount();
    }

    public function selectDate(string $date): void
    {
        Validator::validate(['date' => $date], [
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $selectedDate = Carbon::createFromFormat('Y-m-d', $date);

        if (! $selectedDate->isSameMonth(now())) {
            return;
        }

        $this->selectedDate = $selectedDate->toDateString();
        $this->resetPage();
    }

    /**
     * @return Collection<int, array{date: string, day: string, dayName: string, total: int, isToday: bool}>
     */
    public function getAttendanceDays(): Collection
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $totals = TeacherAttendance::query()
            ->whereBetween('date', [$monthStart, $monthEnd])
            ->selectRaw('date, count(*) as total')
            ->groupBy('date')
            ->pluck('total', 'date');

        return collect(range(1, now()->daysInMonth))
            ->map(function (int $day) use ($totals): array {
                $date = now()->startOfMonth()->addDays($day - 1);

                return [
                    'date' => $date->toDateString(),
                    'day' => $date->format('d'),
                    'dayName' => $date->translatedFormat('D'),
                    'total' => (int) ($totals[$date->toDateString()] ?? 0),
                    'isToday' => $date->isToday(),
                ];
            });
    }

    /**
     * @return Collection<int, array{label: string, color: string, total: int}>
     */
    public function getSelectedDateSummary(): Collection
    {
        $totals = TeacherAttendance::query()
            ->whereDate('date', $this->selectedDate)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(AttendanceStatus::cases())
            ->map(fn (AttendanceStatus $status): array => [
                'label' => $status->getLabel(),
                'color' => $status->getColor(),
                'total' => (int) ($totals[$status->value] ?? 0),
            ]);
    }

    public function getSelectedDateLabel(): string
    {
        return Carbon::parse($this->selectedDate)->translatedFormat('l, d F Y');
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return parent::getTableQuery()
            ?->whereDate('date', $this->selectedDate);
    }

    protected function getHeaderActions(): array
    {
        return [
            SemesterReportAction::forMonth('reports.attendances.pdf', 'Unduh Rekap Bulanan', fn (): string => $this->selectedDate),
            $this->manageDateAction(),
            $this->reopenDateAction(),
            $this->closeReopenedAction(),
            $this->changeHistoryAction(),
            CreateAction::make()
                ->label('Isi Absensi Guru')
                ->using(fn (array $data): TeacherAttendance => TeacherAttendanceResource::saveWithAudit($data)),
        ];
    }

    /**
     * Mark a date as a holiday, a non-effective day or an official activity — or return it
     * to a plain effective day — with the reason that explains the change.
     */
    private function manageDateAction(): Action
    {
        return Action::make('manageDate')
            ->label('Atur Hari Libur / Kegiatan')
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()->canManageAttendance())
            ->modalHeading('Atur status tanggal')
            ->modalDescription('Libur dan non-efektif tidak dihitung sebagai ketidakhadiran. Kegiatan dan Kegiatan Khusus tetap mewajibkan absensi, termasuk di akhir pekan.')
            ->fillForm(fn (): array => [
                'date' => $this->selectedDate,
                'status' => CalendarDay::statusFor($this->selectedDate)->value,
            ])
            ->schema([
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('status', $state ? CalendarDay::statusFor($state)->value : CalendarDayStatus::Efektif->value)),
                Select::make('status')
                    ->label('Status')
                    ->options(self::calendarStatusOptions())
                    ->required()
                    ->native(false),
                Textarea::make('notes')
                    ->label('Keterangan')
                    ->helperText('Wajib. Dicatat di riwayat perubahan kalender.')
                    ->rows(2)
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (array $data): void {
                abort_unless(auth()->user()->canManageAttendance(), 403);

                CalendarDay::markDate($data['date'], CalendarDayStatus::from($data['status']), $data['notes']);

                Notification::make()->success()->title('Status tanggal diperbarui.')->send();
            });
    }

    /**
     * Let teachers fill in a missed day themselves, from "Absensi Saya", until it is closed.
     */
    private function reopenDateAction(): Action
    {
        return Action::make('reopenDate')
            ->label('Buka Kembali Absensi')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()->canManageAttendance())
            ->modalHeading('Buka kembali pengisian absensi')
            ->modalDescription('Guru yang belum mengisi pada tanggal ini bisa mengisinya sendiri lewat Absensi Saya, sampai tanggalnya ditutup lagi.')
            ->schema([
                DatePicker::make('date')
                    ->label('Tanggal yang dibuka')
                    ->required()
                    ->minDate(now()->subDays(60))
                    ->maxDate(now()->subDay())
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (! TeacherAttendance::isFillableDate(Carbon::parse($value))) {
                                $fail('Tanggal ini bukan hari efektif atau kegiatan resmi, jadi tidak ada absensi yang perlu dibuka.');
                            }
                        },
                    ]),
                Textarea::make('reason')
                    ->label('Alasan')
                    ->rows(2)
                    ->required()
                    ->maxLength(500),
            ])
            ->action(function (array $data): void {
                abort_unless(auth()->user()->canManageAttendance(), 403);

                $date = Carbon::parse($data['date']);

                AttendanceReopening::query()->updateOrCreate(
                    ['date' => $date->toDateString()],
                    ['reason' => $data['reason'], 'opened_by' => auth()->id(), 'closed_at' => null, 'closed_by' => null],
                );

                $teachers = User::query()->where('is_active', true)->get()
                    ->reject(fn (User $user): bool => $user->isPrincipal() || $user->is(auth()->user()));

                Notification::make()
                    ->title('Absensi dibuka kembali')
                    ->body("Absensi {$date->translatedFormat('l, d F Y')} dibuka kembali. Isi sekarang bila belum.")
                    ->warning()
                    ->actions([
                        Action::make('open')
                            ->label('Isi absensi')
                            ->url(AttendanceSelf::getUrl())
                            ->markAsRead(),
                    ])
                    ->sendToDatabase($teachers);

                Notification::make()->success()->title('Absensi tanggal tersebut dibuka kembali.')->send();
            });
    }

    private function closeReopenedAction(): Action
    {
        return Action::make('closeReopened')
            ->label('Tutup Tanggal yang Dibuka')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()->canManageAttendance() && AttendanceReopening::query()->open()->exists())
            ->modalHeading('Tutup kembali pengisian absensi')
            ->schema([
                Select::make('dates')
                    ->label('Tanggal yang masih dibuka')
                    ->multiple()
                    ->options(fn (): array => AttendanceReopening::query()->open()->orderBy('date')->get()
                        ->mapWithKeys(fn (AttendanceReopening $reopening): array => [$reopening->date->toDateString() => $reopening->date->translatedFormat('l, d F Y')])
                        ->all())
                    ->required(),
            ])
            ->action(function (array $data): void {
                abort_unless(auth()->user()->canManageAttendance(), 403);

                AttendanceReopening::query()->open()
                    ->whereIn('date', $data['dates'])
                    ->update(['closed_at' => now(), 'closed_by' => auth()->id()]);

                Notification::make()->success()->title('Tanggal ditutup kembali.')->send();
            });
    }

    /**
     * Everything done on teachers' behalf, and every calendar change, for the shown month.
     */
    private function changeHistoryAction(): Action
    {
        return Action::make('changeHistory')
            ->label('Riwayat Perubahan')
            ->icon(Heroicon::OutlinedClock)
            ->color('gray')
            ->visible(fn (): bool => auth()->user()->can('viewAny', TeacherAttendanceLog::class))
            ->modalHeading('Riwayat perubahan absensi dan kalender')
            ->modalContent(function () {
                $month = Carbon::parse($this->selectedDate);

                return view('filament.resources.teacher-attendances.history-all', [
                    'month' => $month,
                    'attendanceLogs' => TeacherAttendanceLog::query()
                        ->with(['teacher', 'actor'])
                        ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                        ->latest('id')
                        ->limit(100)
                        ->get(),
                    'calendarLogs' => CalendarDayLog::query()
                        ->with('actor')
                        ->whereBetween('date', [$month->copy()->startOfMonth()->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                        ->latest('id')
                        ->limit(100)
                        ->get(),
                ]);
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup');
    }

    /**
     * @return array<string, string>
     */
    private static function calendarStatusOptions(): array
    {
        return [
            CalendarDayStatus::Efektif->value => 'Efektif — hari belajar biasa (hapus tanda khusus)',
            CalendarDayStatus::Libur->value => 'Libur — tidak ada absensi',
            CalendarDayStatus::NonEfektif->value => 'Non-Efektif — tidak ada absensi',
            CalendarDayStatus::Kegiatan->value => 'Kegiatan — absensi tetap diisi',
            CalendarDayStatus::KegiatanKhusus->value => 'Kegiatan Khusus — absensi tetap diisi, termasuk akhir pekan',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                View::make('filament.resources.teacher-attendances.date-overview'),
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }
}
