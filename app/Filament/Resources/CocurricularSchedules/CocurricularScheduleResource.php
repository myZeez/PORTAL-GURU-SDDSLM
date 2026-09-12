<?php

namespace App\Filament\Resources\CocurricularSchedules;

use App\Filament\Resources\CocurricularSchedules\Pages\ManageCocurricularSchedules;
use App\Models\Classroom;
use App\Models\CocurricularSchedule;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CocurricularScheduleResource extends Resource
{
    protected static ?string $model = CocurricularSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Program & Layanan';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Jadwal Kokurikuler';

    protected static ?string $modelLabel = 'jadwal kokurikuler';

    protected static ?string $pluralModelLabel = 'jadwal kokurikuler';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now()),
                TextInput::make('theme')
                    ->label('Tema')
                    ->required()
                    ->columnSpanFull(),
                Select::make('classrooms')
                    ->label('Rombel Sasaran')
                    ->relationship('classrooms', 'code')
                    ->getOptionLabelFromRecordUsing(fn (Classroom $record): string => $record->label)
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('classrooms'))
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('theme')
                    ->label('Tema'),
                TextColumn::make('classrooms.code')
                    ->label('Rombel Sasaran')
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCocurricularSchedules::route('/'),
        ];
    }
}
