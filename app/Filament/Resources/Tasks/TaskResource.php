<?php

namespace App\Filament\Resources\Tasks;

use App\Filament\Resources\Tasks\Pages\ManageTasks;
use App\Models\Task;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi Harian';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Manajemen Tugas';

    protected static ?string $modelLabel = 'tugas';

    protected static ?string $pluralModelLabel = 'tugas';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Judul')
                    ->required()
                    ->columnSpanFull(),
                Select::make('assignees')
                    ->label('Penerima')
                    ->relationship('assignees', 'name')
                    ->required()
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->columnSpanFull(),
                DatePicker::make('starts_on')
                    ->label('Tanggal Mulai'),
                DatePicker::make('due_on')
                    ->label('Tenggat')
                    ->required()
                    ->afterOrEqual('starts_on'),
                TextInput::make('link')
                    ->label('Link')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_on')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'assignees',
                'assignees as completed_assignees_count' => fn (Builder $query) => $query->where('task_assignees.is_completed', true),
            ]))
            ->columns([
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable(),
                TextColumn::make('due_on')
                    ->label('Tenggat')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('completion')
                    ->label('Selesai')
                    ->getStateUsing(fn (Task $record): string => "{$record->completed_assignees_count}/{$record->assignees_count}"),
                TextColumn::make('createdBy.name')
                    ->label('Dibuat oleh')
                    ->placeholder('—')
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
            'index' => ManageTasks::route('/'),
        ];
    }
}
