<?php

namespace App\Filament\Resources\Curricula;

use App\Filament\Resources\Curricula\Pages\ManageCurricula;
use App\Models\Curriculum;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CurriculumResource extends Resource
{
    protected static ?string $model = Curriculum::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|UnitEnum|null $navigationGroup = 'Program & Layanan';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Kurikulum';

    protected static ?string $modelLabel = 'dokumen kurikulum';

    protected static ?string $pluralModelLabel = 'kurikulum';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Judul')
                    ->required(),
                TextInput::make('drive_url')
                    ->label('Link Google Drive')
                    ->url()
                    ->rule('url:http,https')
                    ->maxLength(255)
                    ->required()
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (! Curriculum::extractDriveFileId($value)) {
                                $fail('Link ini tidak dikenali sebagai link Google Drive/Docs yang valid.');
                            }
                        },
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('thumbnail_url')
                    ->label('')
                    ->square(),
                TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('createdBy.name')
                    ->label('Diunggah oleh')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Pratinjau')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (Curriculum $record): ?string => $record->preview_url)
                    ->openUrlInNewTab(),
                Action::make('download')
                    ->label('Unduh')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->url(fn (Curriculum $record): ?string => $record->download_url)
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCurricula::route('/'),
        ];
    }
}
