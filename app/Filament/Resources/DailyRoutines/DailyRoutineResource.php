<?php

namespace App\Filament\Resources\DailyRoutines;

use App\Enums\SchoolDay;
use App\Filament\Resources\DailyRoutines\Pages\ManageDailyRoutines;
use App\Models\DailyRoutine;
use App\Models\TimeSlot;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class DailyRoutineResource extends Resource
{
    protected static ?string $model = DailyRoutine::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Rutinitas Harian';

    protected static ?string $modelLabel = 'rutinitas';

    protected static ?string $pluralModelLabel = 'rutinitas harian';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('day_of_week')
                    ->label('Hari')
                    ->options(SchoolDay::class)
                    ->required(),
                Select::make('time_slot_id')
                    ->label('Jam')
                    ->relationship(
                        name: 'timeSlot',
                        titleAttribute: 'starts_at',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->orderBy('starts_at'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (TimeSlot $record): string => $record->label)
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule, callable $get): Unique {
                        $day = $get('day_of_week');

                        return $rule->where('day_of_week', $day instanceof SchoolDay ? $day->value : $day);
                    })
                    ->validationMessages([
                        'unique' => 'Sudah ada rutinitas di hari dan jam ini.',
                    ]),
                TextInput::make('label')
                    ->label('Kegiatan')
                    ->placeholder('Salat Dhuha')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('label')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with('timeSlot')
                ->orderBy('day_of_week')
                ->orderBy('time_slot_id'))
            ->columns([
                TextColumn::make('day_of_week')
                    ->label('Hari'),
                TextColumn::make('timeSlot.starts_at')
                    ->label('Jam')
                    ->formatStateUsing(fn (DailyRoutine $record): string => $record->timeSlot->label),
                TextColumn::make('label')
                    ->label('Kegiatan')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('day_of_week')
                    ->label('Hari')
                    ->options(SchoolDay::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDailyRoutines::route('/'),
        ];
    }
}
