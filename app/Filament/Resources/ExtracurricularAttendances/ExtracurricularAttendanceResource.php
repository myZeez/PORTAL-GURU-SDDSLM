<?php

namespace App\Filament\Resources\ExtracurricularAttendances;

use App\Enums\ExtracurricularAttendanceStatus;
use App\Filament\Resources\ExtracurricularAttendances\Pages\ManageExtracurricularAttendances;
use App\Models\Extracurricular;
use App\Models\ExtracurricularAttendance;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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

class ExtracurricularAttendanceResource extends Resource
{
    protected static ?string $model = ExtracurricularAttendance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Program & Layanan';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Absensi Ekstrakurikuler';

    protected static ?string $modelLabel = 'absensi ekstrakurikuler';

    protected static ?string $pluralModelLabel = 'absensi ekstrakurikuler';

    /**
     * Every guru pendamping only ever logs their own attendance ("Input" applies even to
     * administrators) — the principal and ekskul coordinator are the exception, reading
     * everyone's.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()->isPrincipal() && ! auth()->user()->isEkskulCoordinator()) {
            $query->where('teacher_id', auth()->id());
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now())
                    ->live(),
                Select::make('extracurricular_id')
                    ->label('Ekstrakurikuler')
                    ->options(fn (Get $get): array => self::extracurricularOptions($get('date')))
                    ->required()
                    ->searchable(),
                Select::make('status')
                    ->label('Status')
                    ->options(ExtracurricularAttendanceStatus::class)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['extracurricular', 'teacher']))
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('extracurricular.name')
                    ->label('Ekstrakurikuler'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('teacher.name')
                    ->label('Guru')
                    ->searchable()
                    ->visible(fn (): bool => auth()->user()->isPrincipal() || auth()->user()->isEkskulCoordinator()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ExtracurricularAttendanceStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExtracurricularAttendances::route('/'),
        ];
    }

    /**
     * Extracurriculars meeting on the given date's day of week, restricted to the ones
     * the signed-in teacher accompanies (unless they administer the module).
     *
     * @return array<int, string>
     */
    public static function extracurricularOptions(?string $date): array
    {
        if (! $date) {
            return [];
        }

        $day = Carbon::parse($date)->dayOfWeekIso;

        return Extracurricular::query()
            ->where('day', $day)
            ->when(
                ! auth()->user()->isAdministrator() && ! auth()->user()->isEkskulCoordinator(),
                fn (Builder $query): Builder => $query->whereRelation('assistantTeachers', 'users.id', auth()->id()),
            )
            ->get()
            ->mapWithKeys(fn (Extracurricular $extracurricular): array => [$extracurricular->id => $extracurricular->name])
            ->all();
    }
}
