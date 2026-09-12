<?php

namespace App\Filament\Resources\OutingClasses;

use App\Enums\OutingStatus;
use App\Filament\Resources\OutingClasses\Pages\ManageOutingClasses;
use App\Models\OutingClass;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OutingClassResource extends Resource
{
    protected static ?string $model = OutingClass::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Program & Layanan';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Outing Class';

    protected static ?string $modelLabel = 'pengajuan outing';

    protected static ?string $pluralModelLabel = 'outing class';

    /**
     * Everyone submits their own requests; administrators and the principal see every
     * request, per the project scope's "Input" (self-only) versus "Lihat/setujui" split.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (! auth()->user()->isAdministrator() && ! auth()->user()->isPrincipal()) {
            $query->where('requested_by', auth()->id());
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
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now()),
                TextInput::make('destination')
                    ->label('Lokasi Tujuan')
                    ->required(),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['classroom', 'requestedBy']))
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('classroom.code')
                    ->label('Rombel')
                    ->formatStateUsing(fn (OutingClass $record): string => $record->classroom->label),
                TextColumn::make('destination')
                    ->label('Lokasi Tujuan'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('requestedBy.name')
                    ->label('Diajukan oleh')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(collect(OutingStatus::cases())->mapWithKeys(fn (OutingStatus $s): array => [$s->value => $s->getLabel()])),
            ])
            ->recordActions([
                Action::make('approve')
                    ->label('Setujui')
                    ->icon(Heroicon::OutlinedCheck)
                    ->color('success')
                    ->visible(fn (OutingClass $record): bool => auth()->user()->isAdministrator() && $record->status === OutingStatus::Menunggu)
                    ->requiresConfirmation()
                    ->action(function (OutingClass $record): void {
                        $record->update(['status' => OutingStatus::Disetujui]);

                        Notification::make()->success()->title('Outing class disetujui.')->send();

                        Notification::make()
                            ->title('Pengajuan outing class disetujui')
                            ->body("{$record->classroom->label} — {$record->destination}")
                            ->success()
                            ->sendToDatabase($record->requestedBy);
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOutingClasses::route('/'),
        ];
    }
}
