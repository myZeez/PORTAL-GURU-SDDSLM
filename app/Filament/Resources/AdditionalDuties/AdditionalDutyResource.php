<?php

namespace App\Filament\Resources\AdditionalDuties;

use App\Enums\DutyType;
use App\Filament\Resources\AdditionalDuties\Pages\ManageAdditionalDuties;
use App\Models\AdditionalDuty;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AdditionalDutyResource extends Resource
{
    protected static ?string $model = AdditionalDuty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi Harian';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Tugas Tambahan';

    protected static ?string $modelLabel = 'tugas tambahan';

    protected static ?string $pluralModelLabel = 'tugas tambahan';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('teacher_id')
                    ->label('Guru')
                    ->relationship('teacher', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                TextInput::make('title')
                    ->label('Nama Tugas')
                    ->required()
                    ->placeholder('mis. Kepala Perpustakaan'),
                Select::make('type')
                    ->label('Jenis')
                    ->options(self::typeOptions())
                    ->default(DutyType::Tambahan->value)
                    ->required(),
                TextInput::make('jp')
                    ->label('JP')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('teacher_id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('teacher'))
            ->columns([
                TextColumn::make('teacher.name')
                    ->label('Guru')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Nama Tugas')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Jenis')
                    ->badge(),
                TextColumn::make('jp')
                    ->label('JP')
                    ->alignCenter(),
            ])
            ->filters([
                SelectFilter::make('teacher_id')
                    ->label('Guru')
                    ->relationship('teacher', 'name')
                    ->searchable(),
                SelectFilter::make('type')
                    ->label('Jenis')
                    ->options(self::typeOptions()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAdditionalDuties::route('/'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        return collect(DutyType::cases())
            ->mapWithKeys(fn (DutyType $type): array => [$type->value => $type->getLabel()])
            ->all();
    }
}
