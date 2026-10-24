<?php

namespace App\Filament\AvatarProviders;

use App\Models\User;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Draws the fallback avatar locally as an inline SVG, so it never depends on an external
 * image service being reachable and cannot fail to load like a remote URL can.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = $record instanceof User
            ? $record->initials
            : User::initialsFor((string) ($record->getAttribute('name') ?? ''));

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#9562e3"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" fill="#ffffff" '
            .'font-family="-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Arial,sans-serif" '
            .'font-size="26" font-weight="700">'.e($initials).'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
