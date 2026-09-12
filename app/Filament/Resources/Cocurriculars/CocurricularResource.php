<?php

namespace App\Filament\Resources\Cocurriculars;

use App\Enums\CocurricularActivityType;
use App\Enums\GraduateDimension;
use App\Filament\Resources\Cocurriculars\Pages\ManageCocurriculars;
use App\Models\Classroom;
use App\Models\Cocurricular;
use App\Models\CocurricularSchedule;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class CocurricularResource extends Resource
{
    protected static ?string $model = Cocurricular::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|UnitEnum|null $navigationGroup = 'Program & Layanan';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Kokurikuler';

    protected static ?string $modelLabel = 'kokurikuler';

    protected static ?string $pluralModelLabel = 'kokurikuler';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('cocurricular_schedule_id')
                    ->label('Jadwal Kokurikuler')
                    ->relationship('schedule', 'theme')
                    ->getOptionLabelFromRecordUsing(fn (CocurricularSchedule $record): string => "{$record->date->translatedFormat('d M Y')} — {$record->theme}")
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('classroom_id')
                    ->label('Rombel')
                    ->relationship(
                        'classroom',
                        'code',
                        modifyQueryUsing: fn (Builder $query): Builder => auth()->user()->isAdministrator()
                            ? $query
                            : $query->where(fn (Builder $query): Builder => $query
                                ->where('homeroom_teacher_id', auth()->id())
                                ->orWhere('assistant_teacher_id', auth()->id())),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Classroom $record): string => $record->label)
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('activity_type')
                    ->label('Jenis Kegiatan')
                    ->options(CocurricularActivityType::class)
                    ->required(),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->columnSpanFull(),
                CheckboxList::make('dimensions')
                    ->label('Dimensi Profil Lulusan')
                    ->options(GraduateDimension::class)
                    ->columns(2)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['schedule', 'classroom', 'creator']))
            ->columns([
                TextColumn::make('schedule.date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('schedule.theme')
                    ->label('Tema'),
                TextColumn::make('classroom.code')
                    ->label('Rombel')
                    ->formatStateUsing(fn (Cocurricular $record): string => $record->classroom->label),
                TextColumn::make('activity_type')
                    ->label('Jenis Kegiatan')
                    ->badge(),
                TextColumn::make('creator.name')
                    ->label('Diisi oleh')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('cocurricular_schedule_id')
                    ->label('Jadwal')
                    ->relationship('schedule', 'theme'),
                SelectFilter::make('activity_type')
                    ->label('Jenis Kegiatan')
                    ->options(CocurricularActivityType::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCocurriculars::route('/'),
        ];
    }
}
