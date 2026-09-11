<?php

namespace App\Filament\Resources\Semesters;

use App\Enums\Term;
use App\Filament\Resources\Semesters\Pages\ManageSemesters;
use App\Models\Semester;
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
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class SemesterResource extends Resource
{
    protected static ?string $model = Semester::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Tahun Ajaran & Semester';

    protected static ?string $modelLabel = 'semester';

    protected static ?string $pluralModelLabel = 'semester';

    protected static ?string $recordTitleAttribute = 'academic_year';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('academic_year')
                    ->label('Tahun ajaran')
                    ->placeholder('2026/2027')
                    ->required()
                    ->regex('/^\d{4}\/\d{4}$/')
                    ->unique(ignoreRecord: true, modifyRuleUsing: function (Unique $rule, callable $get): Unique {
                        $term = $get('term');

                        return $rule->where('term', $term instanceof Term ? $term->value : $term);
                    })
                    ->validationMessages([
                        'regex' => 'Tulis tahun ajaran seperti 2026/2027.',
                        'unique' => 'Semester ini sudah ada.',
                    ]),
                Select::make('term')
                    ->label('Semester')
                    ->options(Term::class)
                    ->required(),
                DatePicker::make('starts_on')
                    ->label('Tanggal mulai'),
                DatePicker::make('ends_on')
                    ->label('Tanggal selesai')
                    ->afterOrEqual('starts_on'),
                Toggle::make('is_active')
                    ->label('Semester aktif')
                    ->helperText('Mengaktifkan semester ini otomatis menonaktifkan semester lain. Data semester lain tetap tersimpan sebagai arsip.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('academic_year')
            ->defaultSort('academic_year', 'desc')
            ->columns([
                TextColumn::make('academic_year')
                    ->label('Tahun ajaran')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('term')
                    ->label('Semester')
                    ->badge(),
                TextColumn::make('starts_on')
                    ->label('Mulai')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                TextColumn::make('ends_on')
                    ->label('Selesai')
                    ->date('d/m/Y')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSemesters::route('/'),
        ];
    }
}
