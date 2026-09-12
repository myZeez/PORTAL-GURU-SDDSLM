<?php

namespace App\Filament\Resources\Assessments;

use App\Filament\Resources\Assessments\Pages\ManageAssessments;
use App\Models\Assessment;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AssessmentResource extends Resource
{
    protected static ?string $model = Assessment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Program & Layanan';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Penilaian Sumatif';

    protected static ?string $modelLabel = 'penilaian';

    protected static ?string $pluralModelLabel = 'penilaian sumatif';

    /**
     * Every role only ever manages their own records here ("Input" applies even to
     * administrators) — the principal is the one exception, reading everyone's.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()->isPrincipal()) {
            $query->where('teacher_id', auth()->id());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('classroom_id')
                    ->label('Rombel')
                    ->relationship('classroom', 'code')
                    ->getOptionLabelFromRecordUsing(fn ($record): string => $record->label)
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('subject_id')
                    ->label('Mata Pelajaran')
                    ->relationship('subject', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now()),
                TextInput::make('chapter')
                    ->label('Bab')
                    ->required(),
                Toggle::make('is_completed')
                    ->label('Selesai'),
                TextInput::make('question_link')
                    ->label('Link Soal')
                    ->url(),
                TextInput::make('result_link')
                    ->label('Link Hasil')
                    ->url(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['classroom', 'subject', 'teacher']))
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('classroom.code')
                    ->label('Rombel')
                    ->formatStateUsing(fn (Assessment $record): string => $record->classroom->label),
                TextColumn::make('subject.name')
                    ->label('Mata Pelajaran'),
                TextColumn::make('chapter')
                    ->label('Bab'),
                IconColumn::make('is_completed')
                    ->label('Selesai')
                    ->boolean(),
                TextColumn::make('teacher.name')
                    ->label('Guru')
                    ->searchable()
                    ->visible(fn (): bool => auth()->user()->isPrincipal()),
            ])
            ->filters([
                SelectFilter::make('classroom_id')
                    ->label('Rombel')
                    ->relationship('classroom', 'code'),
                SelectFilter::make('subject_id')
                    ->label('Mata Pelajaran')
                    ->relationship('subject', 'name'),
                TernaryFilter::make('is_completed')
                    ->label('Selesai'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAssessments::route('/'),
        ];
    }
}
