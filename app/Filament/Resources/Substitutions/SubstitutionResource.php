<?php

namespace App\Filament\Resources\Substitutions;

use App\Filament\Resources\Substitutions\Pages\ManageSubstitutions;
use App\Models\Schedule;
use App\Models\Substitution;
use BackedEnum;
use Carbon\Carbon;
use Closure;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class SubstitutionResource extends Resource
{
    protected static ?string $model = Substitution::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi Harian';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Penggantian Guru';

    protected static ?string $modelLabel = 'penggantian';

    protected static ?string $pluralModelLabel = 'penggantian guru';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now())
                    ->live(),
                Select::make('schedule_id')
                    ->label('Jadwal yang digantikan')
                    ->options(fn (Get $get): array => self::scheduleOptions($get('date')))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('date', $get('date')))
                    ->validationMessages([
                        'unique' => 'Jadwal ini sudah punya penggantian pada tanggal ini.',
                    ]),
                Select::make('substitute_teacher_id')
                    ->label('Guru pengganti')
                    ->relationship('substituteTeacher', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $schedule = Schedule::find($get('schedule_id'));

                            if ($schedule && (int) $value === $schedule->teacher_id) {
                                $fail('Guru pengganti tidak boleh sama dengan guru yang digantikan.');
                            }
                        },
                    ]),
                Textarea::make('reason')
                    ->label('Alasan')
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'schedule.classroom', 'schedule.subject', 'schedule.teacher', 'schedule.timeSlot', 'substituteTeacher',
            ]))
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('schedule.classroom.code')
                    ->label('Rombel')
                    ->formatStateUsing(fn (Substitution $record): string => $record->schedule->classroom->label),
                TextColumn::make('schedule.subject.name')
                    ->label('Mata Pelajaran'),
                TextColumn::make('schedule.timeSlot.starts_at')
                    ->label('Jam')
                    ->formatStateUsing(fn (Substitution $record): string => $record->schedule->timeSlot->label),
                TextColumn::make('schedule.teacher.name')
                    ->label('Guru digantikan')
                    ->searchable(),
                TextColumn::make('substituteTeacher.name')
                    ->label('Guru pengganti')
                    ->searchable(),
                TextColumn::make('reason')
                    ->label('Alasan')
                    ->limit(40),
            ])
            ->filters([
                SelectFilter::make('schedule.teacher_id')
                    ->label('Guru digantikan')
                    ->relationship('schedule.teacher', 'name')
                    ->searchable(),
                SelectFilter::make('substitute_teacher_id')
                    ->label('Guru pengganti')
                    ->relationship('substituteTeacher', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubstitutions::route('/'),
        ];
    }

    /**
     * Schedule options for the given date, labelled with classroom, subject, time and the
     * original teacher so an admin can tell slots apart when picking on someone's behalf.
     *
     * @return array<int, string>
     */
    public static function scheduleOptions(?string $date): array
    {
        if (! $date) {
            return [];
        }

        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;

        return Schedule::query()
            ->where('day_of_week', $dayOfWeek)
            ->with(['classroom', 'subject', 'teacher', 'timeSlot'])
            ->get()
            ->mapWithKeys(fn (Schedule $schedule): array => [
                $schedule->id => "{$schedule->classroom->label} — {$schedule->subject->name} ({$schedule->timeSlot->label}) · {$schedule->teacher->name}",
            ])
            ->all();
    }
}
