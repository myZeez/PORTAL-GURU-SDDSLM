<?php

namespace App\Filament\Resources\MonitoringSchedules;

use App\Filament\Pages\ConductMonitoring;
use App\Filament\Resources\MonitoringSchedules\Pages\ManageMonitoringSchedules;
use App\Models\Classroom;
use App\Models\MonitoringResult;
use App\Models\MonitoringSchedule;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class MonitoringScheduleResource extends Resource
{
    protected static ?string $model = MonitoringSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Monitoring';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Monitoring Kelas';

    protected static ?string $modelLabel = 'jadwal monitoring kelas';

    protected static ?string $pluralModelLabel = 'jadwal monitoring kelas';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now()),
                Select::make('classroom_id')
                    ->label('Rombel')
                    ->relationship('classroom', 'code')
                    ->getOptionLabelFromRecordUsing(fn (Classroom $record): string => $record->label)
                    ->required()
                    ->searchable()
                    ->preload(),
                Textarea::make('notes')
                    ->label('Catatan Penjadwalan')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['classroom', 'result']))
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('classroom.code')
                    ->label('Rombel')
                    ->formatStateUsing(fn (MonitoringSchedule $record): string => $record->classroom->label),
                TextColumn::make('result.status')
                    ->label('Status')
                    ->badge()
                    ->default('Belum Dimulai')
                    ->color(fn (MonitoringSchedule $record): string => $record->result?->status?->getColor() ?? 'gray'),
                TextColumn::make('result.score')
                    ->label('Skor')
                    ->formatStateUsing(fn (MonitoringSchedule $record): string => $record->result?->score !== null ? "{$record->result->score}%" : '—'),
            ])
            ->recordActions([
                Action::make('conduct')
                    ->label(fn (MonitoringSchedule $record): string => $record->result ? 'Lanjutkan' : 'Mulai Monitoring')
                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                    ->color('primary')
                    ->visible(fn (): bool => auth()->user()->can('create', MonitoringResult::class))
                    ->url(fn (MonitoringSchedule $record): string => ConductMonitoring::getUrl(['record' => $record->id])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageMonitoringSchedules::route('/'),
        ];
    }
}
