<?php

namespace App\Filament\Resources\CalendarDays;

use App\Enums\CalendarDayStatus;
use App\Filament\Resources\CalendarDays\Pages\ManageCalendarDays;
use App\Models\CalendarDay;
use App\Models\Semester;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
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

class CalendarDayResource extends Resource
{
    protected static ?string $model = CalendarDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Akademik';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Kalender Akademik';

    protected static ?string $modelLabel = 'tanggal khusus';

    protected static ?string $pluralModelLabel = 'kalender akademik';

    protected static ?string $recordTitleAttribute = 'date';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'Tanggal ini sudah punya status khusus.',
                    ]),
                Select::make('status')
                    ->label('Jenis')
                    ->options(self::statusOptions())
                    ->required()
                    ->helperText('Senin–Jumat otomatis Efektif. Tandai di sini kalau tanggalnya berbeda.'),
                Select::make('semester_id')
                    ->label('Semester')
                    ->relationship('semester', 'academic_year')
                    ->getOptionLabelFromRecordUsing(fn (Semester $record): string => $record->name)
                    ->default(fn () => Semester::current()?->id)
                    ->required()
                    ->searchable()
                    ->preload(),
                Textarea::make('notes')
                    ->label('Keterangan')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('date')
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['semester', 'createdBy']))
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('notes')
                    ->label('Keterangan')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('semester.academic_year')
                    ->label('Semester')
                    ->toggleable(),
                TextColumn::make('createdBy.name')
                    ->label('Ditetapkan oleh')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Jenis')
                    ->options(self::statusOptions()),
                SelectFilter::make('semester_id')
                    ->label('Semester')
                    ->relationship('semester', 'academic_year'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCalendarDays::route('/'),
        ];
    }

    /**
     * The statuses an administrator may pick, as select options. Efektif is excluded:
     * it is never stored, only ever the default a date falls back to.
     *
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        return collect(CalendarDayStatus::exceptions())
            ->mapWithKeys(fn (CalendarDayStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }
}
