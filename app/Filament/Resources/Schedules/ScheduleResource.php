<?php

namespace App\Filament\Resources\Schedules;

use App\Enums\SchoolDay;
use App\Filament\Actions\AuditedBulkDeleteAction;
use App\Filament\Resources\Schedules\Pages\ManageSchedules;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimeSlot;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class ScheduleResource extends Resource
{
    protected static ?string $model = Schedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Jadwal Pelajaran';

    protected static ?string $modelLabel = 'jadwal';

    protected static ?string $pluralModelLabel = 'jadwal pelajaran';

    /**
     * Creating takes several time slots and several classes in one go (every combination
     * becomes its own schedule row); editing changes the one row that was opened.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('semester_id')
                    ->label('Semester')
                    ->relationship('semester', 'academic_year')
                    ->getOptionLabelFromRecordUsing(fn (Semester $record): string => $record->name)
                    ->default(fn () => Semester::current()?->id)
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('day_of_week')
                    ->label('Hari')
                    ->options(self::dayOptions())
                    ->required()
                    ->live(),

                Select::make('classroom_ids')
                    ->label('Rombel (boleh lebih dari satu)')
                    ->options(fn (): array => self::classroomOptions())
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->live()
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $problems = self::batchProblems(self::batchState($get, $value))['classroom_ids'];

                            if ($problems !== []) {
                                $fail(self::summarizeProblems($problems));
                            }
                        },
                    ]),

                Select::make('time_slot_ids')
                    ->label('Jam (boleh lebih dari satu)')
                    ->options(fn (): array => self::timeSlotOptions())
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->live()
                    ->visible(fn (string $operation): bool => $operation === 'create')
                    ->helperText(fn (Get $get): ?string => self::batchSummary($get)),
                Select::make('time_slot_id')
                    ->label('Jam')
                    ->relationship(
                        name: 'timeSlot',
                        titleAttribute: 'starts_at',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('starts_at'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (TimeSlot $record): string => $record->label)
                    ->required()
                    ->live()
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
                Select::make('classroom_id')
                    ->label('Rombel')
                    ->relationship('classroom', 'code')
                    ->getOptionLabelFromRecordUsing(fn (Classroom $record): string => $record->label)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->visible(fn (string $operation): bool => $operation === 'edit')
                    ->rules([
                        fn (Get $get, ?Schedule $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $day = SchoolDay::tryFrom((int) $get('day_of_week'));
                            $subject = Subject::find($get('subject_id'));

                            if (! $day || ! $subject || ! $get('semester_id') || ! $get('time_slot_id')) {
                                return;
                            }

                            if (Schedule::hasClassroomConflict(
                                semesterId: (int) $get('semester_id'),
                                day: $day,
                                timeSlotId: (int) $get('time_slot_id'),
                                classroomId: (int) $value,
                                subject: $subject,
                                ignoreId: $record?->id,
                            )) {
                                $fail('Rombel ini sudah punya jadwal lain di hari dan jam yang sama.');
                            }
                        },
                    ]),

                Select::make('subject_id')
                    ->label('Mata Pelajaran')
                    ->relationship('subject', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(),

                Select::make('teacher_id')
                    ->label('Guru')
                    ->relationship('teacher', 'name')
                    ->required(fn (Get $get): bool => ! self::teamNameFor($get('subject_id')))
                    ->visible(fn (Get $get): bool => ! self::teamNameFor($get('subject_id')))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->rules([
                        fn (Get $get, ?Schedule $record, string $operation): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record, $operation): void {
                            if ($operation === 'create') {
                                $problems = self::batchProblems(self::batchState($get, $get('classroom_ids'), $value))['teacher_id'];

                                if ($problems !== []) {
                                    $fail(self::summarizeProblems($problems));
                                }

                                return;
                            }

                            $day = SchoolDay::tryFrom((int) $get('day_of_week'));
                            $subject = Subject::find($get('subject_id'));

                            if (! $day || ! $subject || ! $get('semester_id') || ! $get('time_slot_id')) {
                                return;
                            }

                            if (Schedule::hasTeacherConflict(
                                semesterId: (int) $get('semester_id'),
                                day: $day,
                                timeSlotId: (int) $get('time_slot_id'),
                                teacherId: (int) $value,
                                subject: $subject,
                                ignoreId: $record?->id,
                            )) {
                                $fail('Guru ini sudah punya jadwal mengajar lain di hari dan jam yang sama.');
                            }
                        },
                    ]),
                Text::make(fn (Get $get): string => 'Guru: '.self::teamNameFor($get('subject_id')).' (terisi otomatis, tidak masuk JP guru)')
                    ->badge()
                    ->color('info')
                    ->visible(fn (Get $get): bool => (bool) self::teamNameFor($get('subject_id'))),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['semester', 'timeSlot', 'classroom', 'subject', 'teacher'])
                ->orderBy('day_of_week')
                ->orderBy('time_slot_id'))
            ->columns([
                TextColumn::make('day_of_week')
                    ->label('Hari'),
                TextColumn::make('timeSlot.starts_at')
                    ->label('Jam')
                    ->formatStateUsing(fn (Schedule $record): string => $record->timeSlot->label),
                TextColumn::make('classroom.code')
                    ->label('Rombel')
                    ->formatStateUsing(fn (Schedule $record): string => $record->classroom->label)
                    ->searchable(),
                TextColumn::make('subject.name')
                    ->label('Mata Pelajaran')
                    ->searchable(),
                TextColumn::make('teacher.name')
                    ->label('Guru')
                    ->state(fn (Schedule $record): string => $record->teacher_name)
                    ->searchable(),
                TextColumn::make('jp')
                    ->label('JP')
                    ->alignCenter(),
                TextColumn::make('semester.academic_year')
                    ->label('Semester')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('day_of_week')
                    ->label('Hari')
                    ->options(self::dayOptions()),
                SelectFilter::make('classroom_id')
                    ->label('Rombel')
                    ->relationship('classroom', 'code')
                    ->getOptionLabelFromRecordUsing(fn (Classroom $record): string => $record->label),
                SelectFilter::make('teacher_id')
                    ->label('Guru')
                    ->relationship('teacher', 'name')
                    ->searchable(),
                SelectFilter::make('semester_id')
                    ->label('Semester')
                    ->relationship('semester', 'academic_year'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    AuditedBulkDeleteAction::make(Schedule::class, 'jadwal pelajaran', 'Jurnal dan penggantian guru yang terkait jadwal ini ikut terhapus.', null),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSchedules::route('/'),
        ];
    }

    /**
     * Save one schedule row for every chosen class in every chosen time slot, all or
     * nothing. The conflicts are checked again here, inside the transaction, so two people
     * saving at once cannot double-book a class.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createSchedules(array $data): Schedule
    {
        $slotIds = array_map('intval', $data['time_slot_ids'] ?? []);
        $classroomIds = array_map('intval', $data['classroom_ids'] ?? []);

        return DB::transaction(function () use ($data, $slotIds, $classroomIds): Schedule {
            $problems = array_filter(self::batchProblems($data));

            if ($problems !== []) {
                throw ValidationException::withMessages(array_map(self::summarizeProblems(...), $problems));
            }

            $created = [];

            foreach ($classroomIds as $classroomId) {
                foreach ($slotIds as $slotId) {
                    $created[] = Schedule::query()->create([
                        'semester_id' => $data['semester_id'],
                        'day_of_week' => $data['day_of_week'],
                        'time_slot_id' => $slotId,
                        'classroom_id' => $classroomId,
                        'subject_id' => $data['subject_id'],
                        'teacher_id' => $data['teacher_id'] ?? null,
                    ]);
                }
            }

            return $created[0];
        });
    }

    /**
     * Everything wrong with saving the chosen classes and time slots as schedules, in
     * words: a class already busy in a slot, an identical row that already exists, or one
     * teacher booked for two classes (or two slots) at the same moment. Grouped by the form
     * field they belong to, and both empty when it can be saved.
     *
     * @param  array<string, mixed>  $state
     * @return array{classroom_ids: list<string>, teacher_id: list<string>}
     */
    public static function batchProblems(array $state): array
    {
        $day = SchoolDay::tryFrom((int) ($state['day_of_week'] ?? 0));
        $subject = Subject::query()->find($state['subject_id'] ?? null);
        $semesterId = (int) ($state['semester_id'] ?? 0);
        $slots = TimeSlot::query()->whereKey($state['time_slot_ids'] ?? [])->orderBy('starts_at')->get();
        $classrooms = Classroom::query()->whereKey($state['classroom_ids'] ?? [])->orderBy('grade')->orderBy('code')->get();

        $problems = ['classroom_ids' => [], 'teacher_id' => []];

        if (! $day || ! $subject || $semesterId === 0 || $slots->isEmpty() || $classrooms->isEmpty()) {
            return $problems;
        }

        $teacherId = $subject->isTeamTaught() ? null : (int) ($state['teacher_id'] ?? 0);
        $teacher = $teacherId ? User::query()->find($teacherId) : null;

        foreach ($classrooms as $classroom) {
            foreach ($slots as $slot) {
                $when = "{$day->getLabel()} {$slot->label}";

                if (Schedule::hasClassroomConflict($semesterId, $day, $slot->id, $classroom->id, $subject)) {
                    $problems['classroom_ids'][] = "{$classroom->label} sudah punya jadwal pada {$when}.";
                } elseif (Schedule::existsExactly($semesterId, $day, $slot->id, $classroom->id, $subject->id, $teacherId)) {
                    $problems['classroom_ids'][] = "Jadwal {$subject->name} untuk {$classroom->label} pada {$when} sudah ada.";
                }
            }
        }

        if ($teacher) {
            if ($classrooms->count() > 1 && ! Schedule::isConflictExempt($day, $subject)) {
                $problems['teacher_id'][] = "{$teacher->name} tidak bisa mengajar {$classrooms->count()} rombel sekaligus pada jam yang sama. Pilih satu rombel saja per jadwal.";
            }

            foreach ($slots as $slot) {
                if (Schedule::hasTeacherConflict($semesterId, $day, $slot->id, $teacher->id, $subject)) {
                    $problems['teacher_id'][] = "{$teacher->name} sudah mengajar pada {$day->getLabel()} {$slot->label}.";
                }
            }
        }

        return $problems;
    }

    /**
     * The form's current choices in the shape {@see self::batchProblems()} reads.
     *
     * @return array<string, mixed>
     */
    private static function batchState(Get $get, mixed $classroomIds, mixed $teacherId = null): array
    {
        return [
            'semester_id' => $get('semester_id'),
            'day_of_week' => $get('day_of_week'),
            'subject_id' => $get('subject_id'),
            'teacher_id' => $teacherId ?? $get('teacher_id'),
            'time_slot_ids' => $get('time_slot_ids') ?? [],
            'classroom_ids' => $classroomIds ?? [],
        ];
    }

    /**
     * @param  list<string>  $problems
     */
    private static function summarizeProblems(array $problems): string
    {
        $shown = array_slice($problems, 0, 5);
        $more = count($problems) - count($shown);

        return implode(' ', $shown).($more > 0 ? " (dan {$more} masalah lain)" : '');
    }

    /**
     * A one-line preview under the time slots: how many schedule rows will be saved and
     * what they mean for teaching hours.
     */
    private static function batchSummary(Get $get): ?string
    {
        $slots = count($get('time_slot_ids') ?? []);
        $classrooms = count($get('classroom_ids') ?? []);

        if ($slots === 0) {
            return 'Contoh: pilih 07.30–08.00 dan 08.00–08.30 sekaligus. Tiap jam disimpan sebagai satu jadwal.';
        }

        $subject = Subject::query()->find($get('subject_id'));
        $rooms = max($classrooms, 1);
        $total = $slots * $rooms;

        $jp = match (true) {
            $subject?->isTeamTaught() => "Jadwal {$subject->team_name} tidak dihitung sebagai JP guru.",
            $subject && ! $subject->counts_toward_teaching_load => 'Mata pelajaran ini tidak dihitung sebagai JP.',
            default => "Guru mendapat {$slots} JP per rombel (1 JP untuk setiap jam yang dipilih).",
        };

        return "Akan disimpan {$total} jadwal ({$slots} jam × {$rooms} rombel). {$jp}";
    }

    private static function teamNameFor(mixed $subjectId): ?string
    {
        $subject = filled($subjectId) ? Subject::query()->find($subjectId) : null;

        return $subject?->isTeamTaught() ? $subject->team_name : null;
    }

    /**
     * @return array<int, string>
     */
    private static function timeSlotOptions(): array
    {
        return TimeSlot::query()->orderBy('starts_at')->get()
            ->mapWithKeys(fn (TimeSlot $slot): array => [$slot->id => $slot->label])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function classroomOptions(): array
    {
        return Classroom::query()->orderBy('grade')->orderBy('code')->get()
            ->mapWithKeys(fn (Classroom $classroom): array => [$classroom->id => $classroom->label])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function dayOptions(): array
    {
        return collect(SchoolDay::cases())
            ->mapWithKeys(fn (SchoolDay $day): array => [$day->value => $day->getLabel()])
            ->all();
    }
}
