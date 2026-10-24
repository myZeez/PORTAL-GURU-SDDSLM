<?php

namespace App\Filament\Resources\TeacherAttendances;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Filament\Resources\TeacherAttendances\Pages\ManageTeacherAttendances;
use App\Models\TeacherAttendance;
use App\Models\TeacherAttendanceLog;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use UnitEnum;

class TeacherAttendanceResource extends Resource
{
    protected static ?string $model = TeacherAttendance::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Administrasi Harian';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Absensi Guru';

    protected static ?string $modelLabel = 'absensi';

    protected static ?string $pluralModelLabel = 'absensi guru';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('teacher_id')
                    ->label('Guru')
                    ->relationship(
                        name: 'teacher',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->where(
                            fn (Builder $query): Builder => $query->whereNull('role')->orWhere('role', '!=', Role::KepalaSekolah->value)
                        ),
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->disabled(fn (?TeacherAttendance $record): bool => $record !== null),
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now())
                    ->maxDate(now())
                    ->live()
                    ->disabled(fn (?TeacherAttendance $record): bool => $record !== null)
                    ->rules([
                        fn (?TeacherAttendance $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                            if ($record === null && ! TeacherAttendance::isFillableDate(Carbon::parse($value))) {
                                $fail('Tanggal ini bukan hari efektif atau kegiatan resmi, jadi tidak perlu absensi.');
                            }
                        },
                    ]),
                Select::make('status')
                    ->label('Status')
                    ->options(self::statusOptions())
                    ->native(false)
                    ->required()
                    ->live(),
                DatePicker::make('leave_starts_on')
                    ->label('Cuti mulai')
                    ->visible(fn (Get $get): bool => $get('status') === AttendanceStatus::Cuti->value)
                    ->required(fn (Get $get): bool => $get('status') === AttendanceStatus::Cuti->value),
                DatePicker::make('leave_ends_on')
                    ->label('Cuti selesai')
                    ->visible(fn (Get $get): bool => $get('status') === AttendanceStatus::Cuti->value)
                    ->required(fn (Get $get): bool => $get('status') === AttendanceStatus::Cuti->value)
                    ->afterOrEqual('leave_starts_on'),
                Textarea::make('early_leave_reason')
                    ->label('Alasan pulang awal')
                    ->placeholder('Contoh: kontrol dokter, urusan keluarga mendesak')
                    ->rows(2)
                    ->visible(fn (Get $get): bool => $get('status') === AttendanceStatus::PulangCepat->value)
                    ->required(fn (Get $get): bool => $get('status') === AttendanceStatus::PulangCepat->value),
                Textarea::make('notes')
                    ->label('Keterangan')
                    ->rows(2)
                    ->columnSpanFull(),
                Textarea::make('change_reason')
                    ->label('Alasan pengisian / koreksi')
                    ->helperText('Wajib. Pengisian atas nama guru lain dan koreksi dicatat di riwayat bersama alasan ini.')
                    ->rows(2)
                    ->required()
                    ->maxLength(500)
                    ->columnSpanFull(),
                Callout::make('Catatan absensi sudah ada')
                    ->description(fn (Get $get, ?TeacherAttendance $record): string => self::existingNotice($get, $record))
                    ->color('warning')
                    ->visible(fn (Get $get, ?TeacherAttendance $record): bool => self::existingFor($get, $record) !== null)
                    ->columnSpanFull(),
                Toggle::make('confirm_change')
                    ->label('Saya paham catatan lama akan diganti dan perubahan ini tercatat di riwayat')
                    ->visible(fn (Get $get, ?TeacherAttendance $record): bool => self::existingFor($get, $record) !== null)
                    ->accepted(fn (Get $get, ?TeacherAttendance $record): bool => self::existingFor($get, $record) !== null)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('teacher'))
            ->columns([
                TextColumn::make('teacher.name')
                    ->label('Guru')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('leave_starts_on')
                    ->label('Periode cuti')
                    ->formatStateUsing(fn (TeacherAttendance $record): ?string => $record->leave_starts_on
                        ? $record->leave_starts_on->format('d/m/Y').' – '.$record->leave_ends_on->format('d/m/Y')
                        : null)
                    ->placeholder('—'),
                TextColumn::make('early_leave_reason')
                    ->label('Alasan pulang awal')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_late')
                    ->label('Terlambat')
                    ->boolean(),
                TextColumn::make('notes')
                    ->label('Keterangan')
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('teacher_id')
                    ->label('Guru')
                    ->relationship('teacher', 'name')
                    ->searchable(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(self::statusOptions()),
            ])
            ->recordActions([
                Action::make('history')
                    ->label('Riwayat')
                    ->icon(Heroicon::OutlinedClock)
                    ->color('gray')
                    ->visible(fn (): bool => auth()->user()->can('viewAny', TeacherAttendanceLog::class))
                    ->modalHeading('Riwayat perubahan absensi')
                    ->modalContent(fn (TeacherAttendance $record) => view('filament.resources.teacher-attendances.history', [
                        'logs' => $record->logs()->with('actor')->get(),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup'),
                EditAction::make()
                    ->label('Koreksi')
                    ->using(fn (array $data, TeacherAttendance $record): TeacherAttendance => self::saveWithAudit($data, $record)),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTeacherAttendances::route('/'),
        ];
    }

    /**
     * Save a record created or corrected from this page: always through the audited path,
     * with the reason the person typed.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveWithAudit(array $data, ?TeacherAttendance $record = null): TeacherAttendance
    {
        return TeacherAttendance::saveOnBehalf(
            actor: auth()->user(),
            teacherId: $record?->teacher_id ?? (int) $data['teacher_id'],
            date: $record?->date->toDateString() ?? Carbon::parse($data['date'])->toDateString(),
            attributes: [
                'status' => $data['status'],
                'leave_starts_on' => $data['leave_starts_on'] ?? null,
                'leave_ends_on' => $data['leave_ends_on'] ?? null,
                'early_leave_reason' => $data['early_leave_reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ],
            reason: $data['change_reason'],
        );
    }

    /**
     * The attendance this form would overwrite: the record being corrected, or whatever
     * is already saved for the chosen teacher and date.
     */
    private static function existingFor(Get $get, ?TeacherAttendance $record): ?TeacherAttendance
    {
        if ($record) {
            return $record;
        }

        $teacherId = $get('teacher_id');
        $date = $get('date');

        if (! $teacherId || ! $date) {
            return null;
        }

        return TeacherAttendance::query()
            ->where('teacher_id', $teacherId)
            ->whereDate('date', $date)
            ->first();
    }

    private static function existingNotice(Get $get, ?TeacherAttendance $record): string
    {
        $existing = self::existingFor($get, $record);

        if (! $existing) {
            return '';
        }

        return "{$existing->teacher->name} sudah tercatat \"{$existing->status->getLabel()}\" pada {$existing->date->translatedFormat('d F Y')}. "
            .'Menyimpan akan mengganti catatan itu; status lama tetap tersimpan di riwayat.';
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        return collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }
}
