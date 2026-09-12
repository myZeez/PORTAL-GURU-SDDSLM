<?php

namespace App\Filament\Resources\PidReservations;

use App\Enums\OutingStatus;
use App\Filament\Resources\PidReservations\Pages\ManagePidReservations;
use App\Models\PidReservation;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class PidReservationResource extends Resource
{
    protected static ?string $model = PidReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static string|UnitEnum|null $navigationGroup = 'Program & Layanan';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'PID';

    protected static ?string $modelLabel = 'reservasi PID';

    protected static ?string $pluralModelLabel = 'reservasi PID';

    /**
     * Everyone submits their own reservations; administrators and the principal see
     * every reservation, per the project scope's "Input" (self-only) versus
     * "Lihat/setujui" split.
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
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now())
                    ->live(),
                TextInput::make('location')
                    ->label('Tempat')
                    ->required()
                    ->live(),
                TimePicker::make('starts_at')
                    ->label('Jam Mulai')
                    ->seconds(false)
                    ->required()
                    ->live(),
                TimePicker::make('ends_at')
                    ->label('Jam Selesai')
                    ->seconds(false)
                    ->required()
                    ->after('starts_at')
                    ->live()
                    ->rules([
                        fn (Get $get, ?PidReservation $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            if (! $get('date') || ! $get('location') || ! $get('starts_at')) {
                                return;
                            }

                            if (PidReservation::hasConflict(
                                date: $get('date'),
                                location: $get('location'),
                                startsAt: $get('starts_at'),
                                endsAt: $value,
                                ignoreId: $record?->id,
                            )) {
                                $fail('Tempat ini sudah dipesan pada tanggal dan jam yang sama.');
                            }
                        },
                    ]),
                Select::make('subject_id')
                    ->label('Mata Pelajaran')
                    ->relationship('subject', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Textarea::make('purpose')
                    ->label('Keperluan')
                    ->required()
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['subject', 'requestedBy']))
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Jam')
                    ->formatStateUsing(fn (PidReservation $record): string => substr($record->starts_at, 0, 5).'–'.substr($record->ends_at, 0, 5)),
                TextColumn::make('location')
                    ->label('Tempat'),
                TextColumn::make('subject.name')
                    ->label('Mata Pelajaran'),
                TextColumn::make('purpose')
                    ->label('Keperluan')
                    ->limit(30)
                    ->toggleable(),
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
                    ->visible(fn (PidReservation $record): bool => auth()->user()->isAdministrator() && $record->status === OutingStatus::Menunggu)
                    ->requiresConfirmation()
                    ->action(function (PidReservation $record): void {
                        $record->update(['status' => OutingStatus::Disetujui]);

                        Notification::make()->success()->title('Reservasi PID disetujui.')->send();
                    }),
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('gray')
                    ->visible(fn (PidReservation $record): bool => $record->status === OutingStatus::Disetujui)
                    ->url(fn (PidReservation $record): string => self::whatsappUrl($record))
                    ->openUrlInNewTab(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePidReservations::route('/'),
        ];
    }

    /**
     * Build a WhatsApp deep link to the PID manager, with the loan message already
     * filled in, once a reservation has been approved.
     */
    private static function whatsappUrl(PidReservation $record): string
    {
        $phone = config('portal.pid_manager_phone');

        $message = "Peminjaman PID:\nTanggal: {$record->date->translatedFormat('d F Y')}\nJam: ".
            substr($record->starts_at, 0, 5).'–'.substr($record->ends_at, 0, 5).
            "\nTempat: {$record->location}\nMata Pelajaran: {$record->subject->name}\n".
            "Keperluan: {$record->purpose}\nDiajukan oleh: {$record->requestedBy->name}";

        return "https://wa.me/{$phone}?text=".rawurlencode($message);
    }
}
