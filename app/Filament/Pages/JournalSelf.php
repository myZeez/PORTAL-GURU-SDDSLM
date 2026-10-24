<?php

namespace App\Filament\Pages;

use App\Models\Journal;
use App\Models\Schedule;
use BackedEnum;
use Carbon\Carbon;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property-read Schema $form
 */
class JournalSelf extends Page
{
    protected string $view = 'filament.pages.journal-self';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Jurnal Saya';

    protected static ?string $title = 'Jurnal Saya';

    protected static ?int $navigationSort = 5;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public ?int $editingId = null;

    public ?int $detailId = null;

    public function mount(): void
    {
        if (! $this->isApplicable()) {
            return;
        }

        $this->form->fill([
            'date' => Carbon::today()->toDateString(),
        ]);
    }

    /**
     * Kepala Sekolah never fills a teaching journal, so this page shows an explanatory
     * message instead of the form for them, rather than a hard 403.
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
                    DatePicker::make('date')
                        ->label('Tanggal')
                        ->required()
                        ->live()
                        ->disabled(fn (): bool => $this->editingId !== null),
                    Select::make('schedule_id')
                        ->label('Kelas & Jam')
                        ->options(fn (Get $get): array => $this->scheduleOptions($get('date')))
                        ->required()
                        ->searchable()
                        ->disabled(fn (): bool => $this->editingId !== null)
                        ->rules([
                            fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                if ($this->editingId !== null) {
                                    return;
                                }

                                $date = $get('date');
                                $isEffective = $date && Schedule::effectiveForTeacherOnDate(auth()->id(), Carbon::parse($date))
                                    ->pluck('id')
                                    ->contains((int) $value);

                                if (! $isEffective) {
                                    $fail('Jadwal ini bukan jadwal mengajar Anda pada tanggal tersebut.');
                                }
                            },
                        ]),
                    TextInput::make('topic')
                        ->label('Materi')
                        ->required(),
                    TextInput::make('pages')
                        ->label('Halaman'),
                    TextInput::make('resource_url')
                        ->label('Link Modul/RPP')
                        ->url()
                        ->rule('url:http,https')
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('Deskripsi')
                        ->rows(2),
                    Grid::make(4)
                        ->schema([
                            TextInput::make('students_present')->label('Hadir')->numeric()->minValue(0)->default(0),
                            TextInput::make('students_permitted')->label('Izin')->numeric()->minValue(0)->default(0),
                            TextInput::make('students_sick')->label('Sakit')->numeric()->minValue(0)->default(0),
                            TextInput::make('students_absent')->label('Alpa')->numeric()->minValue(0)->default(0),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        SchemaActions::make([
                            Action::make('save')
                                ->label(fn (): string => $this->editingId ? 'Perbarui' : 'Simpan')
                                ->submit('save'),
                            Action::make('cancelEdit')
                                ->label('Batal ubah')
                                ->color('gray')
                                ->visible(fn (): bool => $this->editingId !== null)
                                ->action('cancelEdit'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach (['students_present', 'students_permitted', 'students_sick', 'students_absent'] as $count) {
            $data[$count] ??= 0;
        }

        if ($this->editingId) {
            Notification::make()
                ->warning()
                ->title('Jurnal yang sudah tersimpan tidak dapat diubah.')
                ->body('Hubungi Super Admin bila data ini perlu dikoreksi.')
                ->send();

            $this->cancelEdit();

            return;
        }

        if (! Journal::isFillableDate(Carbon::parse($data['date']))) {
            Notification::make()
                ->warning()
                ->title('Tanggal ini bukan hari efektif, tidak perlu mengisi jurnal.')
                ->send();

            return;
        }

        if (Journal::query()->where('schedule_id', $data['schedule_id'])->whereDate('date', $data['date'])->exists()) {
            Notification::make()
                ->warning()
                ->title('Jurnal untuk jadwal ini sudah tersimpan dan terkunci.')
                ->body('Hubungi Super Admin bila data ini perlu dikoreksi.')
                ->send();

            return;
        }

        Journal::create([...$data, 'teacher_id' => auth()->id()]);

        $this->cancelEdit();

        Notification::make()
            ->success()
            ->title('Jurnal tersimpan.')
            ->send();
    }

    public function edit(int $journalId): void
    {
        Notification::make()
            ->warning()
            ->title('Jurnal yang sudah tersimpan tidak dapat diubah.')
            ->body('Hubungi Super Admin bila data ini perlu dikoreksi.')
            ->send();
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->form->fill(['date' => Carbon::today()->toDateString()]);
    }

    public function delete(int $journalId): void
    {
        Notification::make()
            ->warning()
            ->title('Jurnal yang sudah tersimpan tidak dapat dihapus.')
            ->body('Hubungi Super Admin bila data ini perlu dikoreksi.')
            ->send();
    }

    /**
     * Open a read-only detail view for one of the signed-in teacher's locked journals.
     */
    public function showDetail(int $journalId): void
    {
        $journalExists = Journal::query()
            ->where('teacher_id', auth()->id())
            ->whereKey($journalId)
            ->exists();

        if (! $journalExists) {
            return;
        }

        $this->detailId = $journalId;
    }

    public function closeDetail(): void
    {
        $this->detailId = null;
    }

    /**
     * The selected journal is always scoped to the signed-in teacher, so a manipulated
     * Livewire property cannot reveal another teacher's operational record.
     */
    public function getDetail(): ?Journal
    {
        if ($this->detailId === null) {
            return null;
        }

        return Journal::query()
            ->where('teacher_id', auth()->id())
            ->whereKey($this->detailId)
            ->with(['schedule.classroom', 'schedule.subject', 'schedule.timeSlot'])
            ->first();
    }

    /**
     * Get the signed-in teacher's own schedule slots effective on the given date (their
     * own, or ones they are covering as a substitute), labelled with classroom, subject
     * and time so they can tell slots apart.
     *
     * @return array<int, string>
     */
    private function scheduleOptions(?string $date): array
    {
        if (! $date) {
            return [];
        }

        return Schedule::effectiveForTeacherOnDate(auth()->id(), Carbon::parse($date))
            ->load(['classroom', 'subject', 'timeSlot'])
            ->mapWithKeys(fn (Schedule $schedule): array => [
                $schedule->id => "{$schedule->classroom->label} — {$schedule->subject->name} ({$schedule->timeSlot->label})",
            ])
            ->all();
    }

    /**
     * Get the signed-in teacher's own journal entries from the last 14 days, most recent
     * first.
     *
     * @return Collection<int, Journal>
     */
    public function getHistory(): Collection
    {
        return Journal::query()
            ->where('teacher_id', auth()->id())
            ->where('date', '>=', Carbon::today()->subDays(14))
            ->with(['schedule.classroom', 'schedule.subject', 'schedule.timeSlot'])
            ->orderByDesc('date')
            ->get();
    }
}
