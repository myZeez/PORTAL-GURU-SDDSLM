<?php

namespace App\Filament\Resources\Subjects;

use App\Filament\Resources\Subjects\Pages\ManageSubjects;
use App\Models\Subject;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SubjectResource extends Resource
{
    protected static ?string $model = Subject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Mata Pelajaran';

    protected static ?string $modelLabel = 'mata pelajaran';

    protected static ?string $pluralModelLabel = 'mata pelajaran';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nama mata pelajaran')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Toggle::make('counts_toward_teaching_load')
                    ->label('Dihitung sebagai JP')
                    ->helperText('Matikan untuk kegiatan yang tidak masuk beban mengajar, seperti Penguatan Hafalan dan PRAMUKA.')
                    ->default(true),
                Toggle::make('allows_concurrent_scheduling')
                    ->label('Boleh dijadwalkan bareng, khusus hari Kamis')
                    ->helperText('Nyalakan untuk mapel yang wajar diajar ke beberapa kelas sekaligus di hari Kamis (mis. PJOK, PRAMUKA), supaya tidak dianggap bentrok jadwal.')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Mata pelajaran')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('counts_toward_teaching_load')
                    ->label('Dihitung JP')
                    ->boolean(),
                IconColumn::make('allows_concurrent_scheduling')
                    ->label('Boleh bareng (Kamis)')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSubjects::route('/'),
        ];
    }
}
