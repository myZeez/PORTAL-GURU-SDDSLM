<?php

namespace App\Filament\Resources\TimeSlots;

use App\Filament\Resources\TimeSlots\Pages\ManageTimeSlots;
use App\Models\TimeSlot;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class TimeSlotResource extends Resource
{
    protected static ?string $model = TimeSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Jam Pelajaran';

    protected static ?string $modelLabel = 'jam pelajaran';

    protected static ?string $pluralModelLabel = 'jam pelajaran';

    protected static ?string $recordTitleAttribute = 'starts_at';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TimePicker::make('starts_at')
                    ->label('Jam mulai')
                    ->seconds(false)
                    ->required()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, callable $get): Unique => $rule->where('ends_at', $get('ends_at')))
                    ->validationMessages([
                        'unique' => 'Jam pelajaran ini sudah ada.',
                    ]),
                TimePicker::make('ends_at')
                    ->label('Jam selesai')
                    ->seconds(false)
                    ->required()
                    ->after('starts_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('starts_at')
            ->defaultSort('starts_at')
            ->columns([
                TextColumn::make('starts_at')
                    ->label('Jam')
                    ->formatStateUsing(fn (TimeSlot $record): string => $record->label)
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTimeSlots::route('/'),
        ];
    }
}
