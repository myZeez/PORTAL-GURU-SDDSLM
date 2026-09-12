<?php

namespace App\Filament\Resources\Journals;

use App\Filament\Resources\Journals\Pages\ManageJournals;
use App\Models\Journal;
use App\Models\Schedule;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use UnitEnum;

class JournalResource extends Resource
{
    protected static ?string $model = Journal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi Harian';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Jurnal Guru';

    protected static ?string $modelLabel = 'jurnal';

    protected static ?string $pluralModelLabel = 'jurnal guru';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now())
                    ->live(),
                Select::make('schedule_id')
                    ->label('Kelas & Jam')
                    ->options(fn (Get $get): array => self::scheduleOptions($get('date')))
                    ->required()
                    ->searchable()
                    ->live()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('date', $get('date')))
                    ->validationMessages([
                        'unique' => 'Jadwal ini sudah punya jurnal pada tanggal ini.',
                    ]),
                Select::make('teacher_id')
                    ->label('Diisi oleh')
                    ->relationship('teacher', 'name')
                    ->default(fn (Get $get): ?int => Schedule::find($get('schedule_id'))?->teacher_id)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('Biasanya guru yang bersangkutan, atau guru pengganti jika ada.'),
                TextInput::make('topic')
                    ->label('Materi')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('pages')
                    ->label('Halaman'),
                TextInput::make('resource_url')
                    ->label('Link Modul/RPP')
                    ->url(),
                Textarea::make('description')
                    ->label('Deskripsi')
                    ->rows(2)
                    ->columnSpanFull(),
                Grid::make(4)
                    ->schema([
                        TextInput::make('students_present')->label('Hadir')->numeric()->minValue(0)->default(0),
                        TextInput::make('students_permitted')->label('Izin')->numeric()->minValue(0)->default(0),
                        TextInput::make('students_sick')->label('Sakit')->numeric()->minValue(0)->default(0),
                        TextInput::make('students_absent')->label('Alpa')->numeric()->minValue(0)->default(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'schedule.classroom', 'schedule.subject', 'schedule.timeSlot', 'teacher',
            ]))
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('schedule.classroom.code')
                    ->label('Rombel')
                    ->formatStateUsing(fn (Journal $record): string => $record->schedule->classroom->label),
                TextColumn::make('schedule.subject.name')
                    ->label('Mata Pelajaran'),
                TextColumn::make('teacher.name')
                    ->label('Diisi oleh')
                    ->searchable(),
                TextColumn::make('topic')
                    ->label('Materi')
                    ->limit(30),
                TextColumn::make('students_present')
                    ->label('Kehadiran')
                    ->formatStateUsing(fn (Journal $record): string => "H:{$record->students_present} I:{$record->students_permitted} S:{$record->students_sick} A:{$record->students_absent}"),
            ])
            ->filters([
                SelectFilter::make('teacher_id')
                    ->label('Diisi oleh')
                    ->relationship('teacher', 'name')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageJournals::route('/'),
        ];
    }

    /**
     * Schedule options for the given date, labelled with classroom, subject, time and the
     * assigned teacher so an admin can tell slots apart when picking on someone's behalf.
     *
     * @return array<int, string>
     */
    public static function scheduleOptions(?string $date): array
    {
        if (! $date) {
            return [];
        }

        $dayOfWeek = Carbon::parse($date)->dayOfWeekIso;

        return Schedule::query()
            ->where('day_of_week', $dayOfWeek)
            ->with(['classroom', 'subject', 'teacher', 'timeSlot'])
            ->get()
            ->mapWithKeys(fn (Schedule $schedule): array => [
                $schedule->id => "{$schedule->classroom->label} — {$schedule->subject->name} ({$schedule->timeSlot->label}) · {$schedule->teacher->name}",
            ])
            ->all();
    }
}
