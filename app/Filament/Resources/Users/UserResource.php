<?php

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\RecordAudit;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Guru & Staf';

    protected static ?string $modelLabel = 'guru/staf';

    protected static ?string $pluralModelLabel = 'guru & staf';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Akun & Peran')
                    ->columns(2)
                    ->components([
                        TextInput::make('code')
                            ->label('Kode guru')
                            ->maxLength(10)
                            ->unique(ignoreRecord: true)
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Str::upper($state) : null),
                        TextInput::make('name')
                            ->label('Nama lengkap')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('position')
                            ->label('Jabatan')
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText('Saat mengedit, kosongkan kalau password tidak diganti.'),
                        Select::make('role')
                            ->label('Peran')
                            ->options(fn (): array => self::assignableRoles())
                            ->disabled(fn (?User $record): bool => self::isOwnAccount($record))
                            ->placeholder('Guru (tidak ada peran khusus)')
                            ->helperText('Wali kelas dan pendamping diatur dari data rombel.'),
                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->disabled(fn (?User $record): bool => self::isOwnAccount($record))
                            ->helperText('Akun nonaktif tidak bisa masuk, tapi riwayat datanya tetap tersimpan.')
                            ->default(true),
                    ]),
                Section::make('Data Pribadi')
                    ->columns(2)
                    ->components(PersonalDataFields::make()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('name')
            ->columns([
                ImageColumn::make('photo_path')
                    ->label('Foto')
                    ->getStateUsing(fn (User $record): ?string => $record->getFilamentAvatarUrl())
                    ->circular(),
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('position')
                    ->label('Jabatan')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (?Role $state): string => $state?->getLabel() ?? 'Guru'),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status aktif'),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(fn (User $record, array $data): User => self::saveWithAudit($record, $data)),
                DeleteAction::make(),
            ]);
    }

    /**
     * Roles the signed-in user may hand out. Only a Super Admin can make another Super Admin.
     *
     * @return array<string, string>
     */
    public static function assignableRoles(): array
    {
        return collect(Role::cases())
            ->reject(fn (Role $role): bool => $role === Role::SuperAdmin && ! auth()->user()->isSuperAdmin())
            ->mapWithKeys(fn (Role $role): array => [$role->value => $role->getLabel()])
            ->all();
    }

    /**
     * Nobody switches off or demotes their own account, which would lock them out.
     */
    public static function isOwnAccount(?User $record): bool
    {
        return $record?->is(auth()->user()) ?? false;
    }

    /**
     * Save an account edit and write what changed to its history. A password set by someone
     * else is a temporary one, so the owner must choose their own at the next sign-in.
     *
     * @param  array<string, mixed>  $data
     */
    public static function saveWithAudit(User $record, array $data): User
    {
        $actor = auth()->user();

        if (self::isOwnAccount($record)) {
            $data = Arr::except($data, ['role', 'is_active']);
        }

        if (! $actor->isSuperAdmin() && ($data['role'] ?? null) === Role::SuperAdmin->value) {
            $data = Arr::except($data, ['role']);
        }

        $passwordChanged = filled($data['password'] ?? null);

        if ($passwordChanged && ! self::isOwnAccount($record)) {
            $data['must_change_password'] = true;
        }

        DB::transaction(function () use ($record, $data, $actor, $passwordChanged): void {
            $before = $record->auditSnapshot();

            $record->update($data);

            $after = $record->fresh()->auditSnapshot();

            if ($passwordChanged) {
                $before['Password'] = '—';
                $after['Password'] = 'Diganti';
            }

            RecordAudit::record($record, RecordAudit::ACTION_UPDATED, $record->auditLabel(), $before, $after, null, $actor);
        });

        return $record;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}
