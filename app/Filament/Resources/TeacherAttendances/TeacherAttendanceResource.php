<?php

namespace App\Filament\Resources\TeacherAttendances;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Filament\Resources\TeacherAttendances\Pages\ManageTeacherAttendances;
use App\Models\TeacherAttendance;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                    ->preload(),
                DatePicker::make('date')
                    ->label('Tanggal')
                    ->required()
                    ->default(now()),
                Select::make('status')
                    ->label('Status')
                    ->options(self::statusOptions())
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
                Textarea::make('notes')
                    ->label('Keterangan')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('teacher'))
            ->columns([
                TextColumn::make('date')
                    ->label('Tanggal')
                    ->date('d/m/Y')
                    ->sortable(),
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
                EditAction::make(),
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
     * @return array<string, string>
     */
    private static function statusOptions(): array
    {
        return collect(AttendanceStatus::cases())
            ->mapWithKeys(fn (AttendanceStatus $status): array => [$status->value => $status->getLabel()])
            ->all();
    }
}
