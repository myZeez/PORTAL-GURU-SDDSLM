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
                        ->url(),
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

        // While editing, `date` and `schedule_id` are disabled (never change which slot
        // a journal belongs to) and therefore absent from the dehydrated state — reuse
        // the already-owner-verified record's values instead of trusting the submission.
        if ($this->editingId) {
            $journal = Journal::query()
                ->where('teacher_id', auth()->id())
                ->find($this->editingId);

            if (! $journal) {
                Notification::make()->danger()->title('Jurnal tidak ditemukan.')->send();

                return;
            }

            if (! Journal::isFillableDate($journal->date)) {
                Notification::make()
                    ->warning()
                    ->title('Tanggal ini bukan hari efektif, tidak perlu mengisi jurnal.')
                    ->send();

                return;
            }

            $journal->update(collect($data)->except(['schedule_id', 'date'])->all());
        } else {
            if (! Journal::isFillableDate(Carbon::parse($data['date']))) {
                Notification::make()
                    ->warning()
                    ->title('Tanggal ini bukan hari efektif, tidak perlu mengisi jurnal.')
                    ->send();

                return;
            }

            Journal::updateOrCreate(
                ['schedule_id' => $data['schedule_id'], 'date' => $data['date']],
                [...$data, 'teacher_id' => auth()->id()],
            );
        }

        $this->cancelEdit();

        Notification::make()
            ->success()
            ->title('Jurnal tersimpan.')
            ->send();
    }

    public function edit(int $journalId): void
    {
        $journal = Journal::query()
            ->where('teacher_id', auth()->id())
            ->find($journalId);

        if (! $journal) {
            return;
        }

        $this->editingId = $journal->id;
        $this->form->fill($journal->attributesToArray());
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->form->fill(['date' => Carbon::today()->toDateString()]);
    }

    public function delete(int $journalId): void
    {
        $journal = Journal::query()
            ->where('teacher_id', auth()->id())
            ->find($journalId);

        if (! $journal) {
            return;
        }

        $journal->delete();

        if ($this->editingId === $journalId) {
            $this->cancelEdit();
        }

        Notification::make()->success()->title('Jurnal dihapus.')->send();
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
