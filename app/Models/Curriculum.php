<?php

namespace App\Models;

use Database\Factories\CurriculumFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'drive_url', 'created_by'])]
class Curriculum extends Model
{
    /** @use HasFactory<CurriculumFactory> */
    use HasFactory;

    /**
     * Record who uploaded it, unless the caller already set one.
     */
    protected static function booted(): void
    {
        static::creating(function (self $curriculum): void {
            $curriculum->created_by ??= auth()->id();
        });
    }

    /**
     * Get the administrator who added this document.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Extract the file ID from a Google Drive or Docs/Sheets/Slides share link. Covers
     * the common share-link shapes: `/file/d/{id}/...`, `/document|spreadsheets|
     * presentation/d/{id}/...`, and the older `?id={id}` query-string form.
     */
    public static function extractDriveFileId(string $url): ?string
    {
        if (preg_match('#/d/([a-zA-Z0-9_-]+)#', $url, $matches)) {
            return $matches[1];
        }

        if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Get an auto-generated thumbnail for the document.
     *
     * @return Attribute<?string, never>
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $fileId = self::extractDriveFileId($this->drive_url);

            return $fileId ? "https://drive.google.com/thumbnail?id={$fileId}&sz=w400" : null;
        });
    }

    /**
     * Get an embeddable preview link for the document.
     *
     * @return Attribute<?string, never>
     */
    protected function previewUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $fileId = self::extractDriveFileId($this->drive_url);

            return $fileId ? "https://drive.google.com/file/d/{$fileId}/preview" : null;
        });
    }

    /**
     * Get a direct download link for the document.
     *
     * @return Attribute<?string, never>
     */
    protected function downloadUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $fileId = self::extractDriveFileId($this->drive_url);

            return $fileId ? "https://drive.google.com/uc?export=download&id={$fileId}" : null;
        });
    }
}
