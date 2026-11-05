<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\Schedule;
use App\Models\Substitution;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Closure;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Unique;

/**
 * @property-read Schema $form
 */
class SubstitutionSelf extends Page
{
    protected string $view = 'filament.pages.substitution-self';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Penggantian Saya';

    protected static ?string $title = 'Penggantian Saya';

    protected static ?int $navigationSort = 4;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

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
     * Kepala Sekolah never submits a substitution for their own class, so this page shows
     * an explanatory message instead of the form for them, rather than a hard 403.
     */
    public function isApplicable(): bool
    {
        return ! auth()->user()->isPrincipal();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make($this->fields())
                    ->livewireSubmitHandler('save')
                    ->footer([
                        SchemaActions::make([
                            Action::make('save')
                                ->label('Simpan')
                                ->submit('save'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * The fields of a substitution, as filed on this page and as changed afterwards.
     *
     * @param  int|null  $ignoreId  the substitution being changed, which may keep its own schedule and date
     * @return list<DatePicker|Select|Textarea>
     */
    private function fields(?int $ignoreId = null): array
    {
        return [
            DatePicker::make('date')
                ->label('Tanggal')
                ->required()
                ->minDate(fn (): ?Carbon => $ignoreId && ! auth()->user()->canCorrectDailyRecords() ? today() : null)
                ->live(),
            Select::make('schedule_id')
                ->label('Jadwal saya yang digantikan')
                ->options(fn (Get $get): array => $this->scheduleOptions($get('date')))
                ->required()
                ->searchable()
                ->rules([
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        $schedule = Schedule::find($value);

                        if (! $schedule || $schedule->teacher_id !== auth()->id()) {
                            $fail('Jadwal ini bukan jadwal mengajar Anda.');
                        }
                    },
                ])
                ->unique(
                    table: Substitution::class,
                    column: 'schedule_id',
                    ignorable: fn (): ?Substitution => $ignoreId ? Substitution::query()->find($ignoreId) : null,
                    modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('date', $get('date')),
                )
                ->validationMessages([
                    'unique' => 'Jadwal ini sudah punya penggantian pada tanggal ini.',
                ]),
            Select::make('substitute_teacher_id')
                ->label('Guru pengganti')
                ->options(fn (): array => User::query()
                    ->whereKeyNot(auth()->id())
                    ->where(fn (Builder $query): Builder => $query->whereNull('role')->orWhere('role', '!=', Role::KepalaSekolah->value))
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->required()
                ->searchable(),
            Textarea::make('reason')
                ->label('Alasan')
                ->required()
                ->rows(2),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Substitution::create($data);

        $this->form->fill([
            'date' => $data['date'],
        ]);

        Notification::make()
            ->success()
            ->title('Penggantian tersimpan.')
            ->send();
    }

    /**
     * Change a substitution this teacher filed. The record is looked up and authorised again
     * on the server, whatever id the button sent.
     */
    public function editSubstitutionAction(): Action
    {
        return Action::make('editSubstitution')
            ->modalHeading('Ubah penggantian')
            ->modalSubmitActionLabel('Simpan perubahan')
            ->fillForm(fn (array $arguments): array => $this->changeable($arguments)->only(['date', 'schedule_id', 'substitute_teacher_id', 'reason']))
            ->schema(fn (array $arguments): array => $this->fields((int) ($arguments['substitution'] ?? 0)))
            ->action(function (array $data, array $arguments): void {
                $this->changeable($arguments)->updateWithAudit(auth()->user(), $data);

                Notification::make()->success()->title('Penggantian diubah.')->send();
            });
    }

    /**
     * Remove a substitution this teacher filed, after a confirmation that asks why.
     */
    public function deleteSubstitutionAction(): Action
    {
        return Action::make('deleteSubstitution')
            ->color('danger')
            ->modalHeading('Hapus penggantian ini?')
            ->modalDescription('Penggantian yang dihapus tidak bisa dikembalikan. Penghapusan dicatat di riwayat perubahan beserta alasannya.')
            ->modalSubmitActionLabel('Ya, hapus')
            ->schema([
                Textarea::make('reason')
                    ->label('Alasan menghapus')
                    ->required()
                    ->maxLength(500)
                    ->rows(2),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->changeable($arguments)->deleteWithAudit(auth()->user(), $data['reason']);

                Notification::make()->success()->title('Penggantian dihapus.')->send();
            });
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function changeable(array $arguments): Substitution
    {
        $substitution = Substitution::query()->findOrFail((int) ($arguments['substitution'] ?? 0));

        Gate::authorize('update', $substitution);

        return $substitution;
    }

    /**
     * Get the signed-in teacher's own schedule slots on the given date's day of week,
     * labelled with classroom, subject and time so they can tell slots apart.
     *
     * @return array<int, string>
     */
    private function scheduleOptions(?string $date): array
    {
        if (! $date) {
            return [];
        }

        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;

        return Schedule::query()
            ->where('teacher_id', auth()->id())
            ->where('day_of_week', $dayOfWeek)
            ->with(['classroom', 'subject', 'timeSlot'])
            ->get()
            ->mapWithKeys(fn (Schedule $schedule): array => [
                $schedule->id => "{$schedule->classroom->label} — {$schedule->subject->name} ({$schedule->timeSlot->label})",
            ])
            ->all();
    }

    /**
     * Get substitutions the signed-in teacher submitted for their own class, or where
     * they are the one covering, most recent first.
     *
     * @return Collection<int, Substitution>
     */
    public function getHistory(): Collection
    {
        return Substitution::query()
            ->where(fn (Builder $query) => $query
                ->whereHas('schedule', fn (Builder $query) => $query->where('teacher_id', auth()->id()))
                ->orWhere('substitute_teacher_id', auth()->id()))
            ->with(['schedule.classroom', 'schedule.subject', 'schedule.timeSlot', 'schedule.teacher', 'substituteTeacher'])
            ->orderByDesc('date')
            ->limit(20)
            ->get();
    }
}
