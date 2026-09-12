<?php

namespace App\Filament\Resources\Extracurriculars;

use App\Enums\SchoolDay;
use App\Filament\Resources\Extracurriculars\Pages\ManageExtracurriculars;
use App\Models\Extracurricular;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ExtracurricularResource extends Resource
{
    protected static ?string $model = Extracurricular::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Program & Layanan';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Jadwal Ekstrakurikuler';

    protected static ?string $modelLabel = 'ekstrakurikuler';

    protected static ?string $pluralModelLabel = 'ekstrakurikuler';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama')
                    ->required(),
                Select::make('day')
                    ->label('Hari')
                    ->options(self::dayOptions())
                    ->required(),
                TimePicker::make('start_time')
                    ->label('Jam Mulai')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end_time')
                    ->label('Jam Selesai')
                    ->seconds(false)
                    ->required()
                    ->after('start_time'),
                TextInput::make('location')
                    ->label('Tempat')
                    ->required(),
                TextInput::make('external_coach')
                    ->label('Pelatih Luar')
                    ->helperText('Kosongkan jika hanya dibina oleh guru pendamping.'),
                Select::make('assistantTeachers')
                    ->label('Guru Pendamping')
                    ->relationship('assistantTeachers', 'name')
                    ->getOptionLabelFromRecordUsing(fn (User $record): string => $record->name)
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('day')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('assistantTeachers'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama')
                    ->weight('bold'),
                TextColumn::make('day')
                    ->label('Hari'),
                TextColumn::make('start_time')
                    ->label('Jam')
                    ->formatStateUsing(fn (Extracurricular $record): string => substr($record->start_time, 0, 5).'–'.substr($record->end_time, 0, 5)),
                TextColumn::make('location')
                    ->label('Tempat'),
                TextColumn::make('external_coach')
                    ->label('Pelatih Luar')
                    ->placeholder('—'),
                TextColumn::make('assistantTeachers.name')
                    ->label('Guru Pendamping')
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
            'index' => ManageExtracurriculars::route('/'),
        ];
    }

    /**
     * Extracurriculars only ever meet Tuesday through Thursday, per the project scope.
     *
     * @return array<int, string>
     */
    private static function dayOptions(): array
    {
        return collect([SchoolDay::Selasa, SchoolDay::Rabu, SchoolDay::Kamis])
            ->mapWithKeys(fn (SchoolDay $day): array => [$day->value => $day->getLabel()])
            ->all();
    }
}
