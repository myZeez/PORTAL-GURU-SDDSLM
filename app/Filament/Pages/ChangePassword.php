<?php

namespace App\Filament\Pages;

use Filament\Auth\Pages\EditProfile;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use SensitiveParameter;

/**
 * Self-service password change. Inherits Filament's profile-page behaviour (current
 * password check, rate limiting, re-syncing the session hash so the user stays signed in)
 * but exposes only the password fields: name and email stay admin-managed in Guru & Staf.
 */
class ChangePassword extends EditProfile
{
    protected static ?string $title = 'Ubah Password';

    public static function getLabel(): string
    {
        return 'Ubah Password';
    }

    public function form(Schema $schema): Schema
    {
        $password = $this->getPasswordFormComponent();
        $password->required();

        return $schema
            ->components([
                $this->getCurrentPasswordFormComponent(),
                $password,
                $this->getPasswordConfirmationFormComponent(),
            ]);
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Password diperbarui.';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        $record->update([...$data, 'must_change_password' => false]);

        return $record;
    }

    protected function getRedirectUrl(): ?string
    {
        return Dashboard::getUrl();
    }
}
