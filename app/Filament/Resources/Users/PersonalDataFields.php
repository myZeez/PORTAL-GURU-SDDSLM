<?php

namespace App\Filament\Resources\Users;

use App\Enums\Education;
use App\Enums\Gender;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * The personal-data form fields shared by Guru & Staf (admin edits anyone) and Profil
 * (a user edits themselves), kept in one place so both enforce the same validation.
 */
class PersonalDataFields
{
    /**
     * @return list<Component>
     */
    public static function make(): array
    {
        return [
            FileUpload::make('photo_path')
                ->label('Foto profil')
                ->image()
                ->avatar()
                ->disk('public')
                ->directory('avatars')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(2048)
                ->columnSpanFull(),
            TextInput::make('nik')
                ->label('NIK')
                ->rule('digits:16')
                ->inputMode('numeric'),
            TextInput::make('nuptk')
                ->label('NUPTK')
                ->rule('digits:16')
                ->inputMode('numeric'),
            TextInput::make('birthplace')
                ->label('Tempat lahir')
                ->maxLength(100),
            DatePicker::make('birthdate')
                ->label('Tanggal lahir')
                ->maxDate(now()),
            Select::make('gender')
                ->label('Jenis kelamin')
                ->options(Gender::class),
            Select::make('last_education')
                ->label('Pendidikan terakhir')
                ->options(Education::class),
            TextInput::make('phone')
                ->label('No. HP/WhatsApp')
                ->tel()
                ->maxLength(20),
            Textarea::make('address')
                ->label('Alamat')
                ->maxLength(500)
                ->columnSpanFull(),
        ];
    }
}
