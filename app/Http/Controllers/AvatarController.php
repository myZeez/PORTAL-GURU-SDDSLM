<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarController extends Controller
{
    /**
     * Serve a user's profile photo through the application, so it keeps loading on hosts
     * where the public/storage symlink is missing or blocked.
     */
    public function show(User $user): StreamedResponse
    {
        abort_unless($user->photo_path && Storage::disk('public')->exists($user->photo_path), 404);

        return Storage::disk('public')->response($user->photo_path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Serve an uploaded photo by its `/media/avatars/...` address, which the upload form
     * previews with. Where the public/storage symlink exists the web server answers first;
     * where it does not (shared hosting), this keeps the address working.
     */
    public function file(string $file): StreamedResponse
    {
        $path = "avatars/{$file}";

        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
