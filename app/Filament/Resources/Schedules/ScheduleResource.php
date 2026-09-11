<?php

namespace App\Filament\Resources\Schedules;

use App\Enums\SchoolDay;
use App\Filament\Resources\Schedules\Pages\ManageSchedules;
use App\Models\Classroom;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimeSlot;
use BackedEnum;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                Select::make('time_slot_id')
                    ->label('Jam')
                    ->relationship(
                        name: 'timeSlot',
                        titleAttribute: 'starts_at',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('starts_at'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (TimeSlot $record): string => $record->label)
                    ->required()
                    ->live(),
                Select::make('classroom_id')
                    ->label('Rombel')
                    ->relationship('classroom', 'code')
                    ->getOptionLabelFromRecordUsing(fn (Classroom $record): string => $record->label)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
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
                    ->required()
                    ->searchable()
                    ->preload()
                    ->rules([
                        fn (Get $get, ?Schedule $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
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
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSchedules::route('/'),
        ];
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
