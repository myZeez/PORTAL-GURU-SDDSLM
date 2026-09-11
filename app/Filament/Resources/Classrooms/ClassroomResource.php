<?php

namespace App\Filament\Resources\Classrooms;

use App\Filament\Resources\Classrooms\Pages\ManageClassrooms;
use App\Models\Classroom;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ClassroomResource extends Resource
{
    protected static ?string $model = Classroom::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Rombel';

    protected static ?string $modelLabel = 'rombel';

    protected static ?string $pluralModelLabel = 'rombel';

    protected static ?string $recordTitleAttribute = 'code';

    /**
     * Grades written the way the school writes them.
     *
     * @var array<int, string>
     */
    private const GRADES = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV', 5 => 'V', 6 => 'VI'];

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Kode rombel')
                    ->placeholder('I-A')
                    ->required()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('Nama rombel')
                    ->placeholder('Ibnu Sina')
                    ->required()
                    ->maxLength(255),
                Select::make('grade')
                    ->label('Kelas')
                    ->options(self::GRADES)
                    ->required(),
                Select::make('homeroom_teacher_id')
                    ->label('Wali kelas')
                    ->relationship('homeroomTeacher', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('assistant_teacher_id')
                    ->label('Pendamping wali kelas')
                    ->relationship('assistantTeacher', 'name')
                    ->searchable()
                    ->preload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['homeroomTeacher', 'assistantTeacher'])
                ->orderBy('grade')
                ->orderBy('code'))
            ->columns([
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama rombel')
                    ->searchable(),
                TextColumn::make('homeroomTeacher.name')
                    ->label('Wali kelas')
                    ->placeholder('—'),
                TextColumn::make('assistantTeacher.name')
                    ->label('Pendamping')
                    ->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageClassrooms::route('/'),
        ];
    }
}
