<?php

namespace App\Models;

use Database\Factories\ExtracurricularAnnouncementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'body', 'image_drive_url', 'created_by'])]
class ExtracurricularAnnouncement extends Model
{
    /** @use HasFactory<ExtracurricularAnnouncementFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $announcement): void {
            $announcement->created_by ??= auth()->id();
        });
    }

    /**
     * Get the Google Drive image, resized for display on the announcement wall.
     *
     * @return Attribute<?string, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->image_drive_url) {
                return null;
            }

            $fileId = Curriculum::extractDriveFileId($this->image_drive_url);

            return $fileId ? "https://drive.google.com/thumbnail?id={$fileId}&sz=w600" : null;
        });
    }

    /**
     * Get the comments left on this announcement, oldest first.
     *
     * @return HasMany<ExtracurricularAnnouncementComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(ExtracurricularAnnouncementComment::class)->oldest();
    }

    /**
     * Get the user who posted this announcement.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
