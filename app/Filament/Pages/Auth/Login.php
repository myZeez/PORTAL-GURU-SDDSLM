<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The panel's sign-in page: Filament's own form and authentication, inside a split layout
 * with the school's illustration beside it.
 */
class Login extends BaseLogin
{
    protected string $view = 'filament.pages.auth.login';

    protected static string $layout = 'filament.layouts.login';

    public function getHeading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return parent::getHeading();
        }

        return 'Selamat datang kembali';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return parent::getSubheading() ?? 'Masuk dengan email dan kata sandi dari sekolah.';
    }
}
